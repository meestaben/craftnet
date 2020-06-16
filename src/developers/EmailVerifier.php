<?php

namespace craftnet\developers;

use Craft;
use craft\commerce\Plugin as Commerce;
use craft\db\Query;
use craft\elements\User;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use craft\helpers\Template;
use craft\helpers\UrlHelper;
use craftnet\behaviors\UserBehavior;
use craftnet\Module;
use yii\base\BaseObject;
use yii\base\InvalidArgumentException;
use yii\validators\EmailValidator;

class EmailVerifier extends BaseObject
{
    /**
     * @var User|UserBehavior
     */
    public $user;

    /**
     * @inheritdoc
     */
    public function __construct(User $user, array $config = [])
    {
        $this->user = $user;
        parent::__construct($config);
    }

    /**
     * Kicks off the verification process for a new email address.
     *
     * @param string $email
     * @throws InvalidArgumentException if $email isn't a valid email
     */
    public function sendVerificationEmail(string $email)
    {
        // make sure this is a valid email
        if (!(new EmailValidator())->validate($email, $error)) {
            throw new InvalidArgumentException($error);
        }

        // create & save the verification code
        $securityService = Craft::$app->getSecurity();
        $code = $securityService->generateRandomString(32);

        Craft::$app->getDb()->createCommand()
            ->insert('craftnet_emailcodes', [
                'userId' => $this->user->id,
                'email' => $email,
                'code' => $securityService->hashPassword($code),
                'dateIssued' => Db::prepareDateForDb(new \DateTime('now', new \DateTimeZone('UTC'))),
            ], false)
            ->execute();

        // send the verification email
        $path = Craft::$app->getConfig()->getGeneral()->actionTrigger . '/craftnet/id/claim-licenses/verify';
        $params = [
            'id' => $this->user->uid,
            'email' => $email,
            'code' => $code,
        ];
        $scheme = UrlHelper::getSchemeForTokenizedUrl();
        $siteId = Craft::$app->getSites()->getSiteByHandle('craftId')->id;
        $url = UrlHelper::siteUrl($path, $params, $scheme, $siteId);

        Craft::$app->getMailer()
            ->composeFromKey(Module::MESSAGE_KEY_VERIFY, [
                'user' => $this->user,
                'email' => $email,
                'link' => Template::raw($url)
            ])
            ->setTo($email)
            ->send();
    }

    /**
     * Verifies an email with the given verification code, and claims any licenses
     * and guest orders associated with that email.
     *
     * @param string $email
     * @param string $code
     * @return int the total number of claimed licenses
     * @throws InvalidArgumentException if the email can't be verified
     */
    public function verify(string $email, string $code): int
    {
        $db = Craft::$app->getDb();

        // first delete all codes that have expired
        $interval = DateTimeHelper::secondsToInterval(Craft::$app->getConfig()->getGeneral()->verificationCodeDuration);
        $minCodeIssueDate = (new \DateTime('now', new \DateTimeZone('UTC')))->sub($interval);
        $db->createCommand()
            ->delete('craftnet_emailcodes', ['<', 'dateIssued', Db::prepareDateForDb($minCodeIssueDate)])
            ->execute();

        // get all the codes for this user and email
        $condition = ['userId' => $this->user->id, 'email' => $email];
        $codes = (new Query())
            ->select(['code'])
            ->from(['craftnet_emailcodes'])
            ->where($condition)
            ->column();

        // see if any of them are valid
        $valid = false;
        $securityService = Craft::$app->getSecurity();
        foreach ($codes as $hash) {
            try {
                if ($securityService->validatePassword($code, $hash)) {
                    $valid = true;
                    break;
                }
            } catch (InvalidArgumentException $e) {
            }
        }

        if (!$valid) {
            throw new InvalidArgumentException("Unable to verify the email {$email}");
        }

        // claim unowned licenses with that email
        $module = Module::getInstance();
        $num = $module->getCmsLicenseManager()->claimLicenses($this->user, $email);
        $num += $module->getPluginLicenseManager()->claimLicenses($this->user, $email);

        // claim guest orders
        $commerce = Commerce::getInstance();
        if (!empty($orders = $commerce->getOrders()->getOrdersByEmail($email))) {
            $commerce->getCustomers()->consolidateOrdersToUser($this->user, $orders);
        }

        // remove all verification codes for this user + email
        $db->createCommand()
            ->delete('craftnet_emailcodes', $condition)
            ->execute();

        return $num;
    }
}
