<?php

namespace craftnet\console\controllers;

use Craft;
use craft\commerce\Plugin as Commerce;
use craft\elements\User;
use craft\helpers\ArrayHelper;
use craftnet\behaviors\UserBehavior;
use craftnet\Module;
use yii\console\Controller;
use yii\db\Query;
use yii\helpers\Console;

/**
 * Claims Craft and plugin licenses/orders for a user.
 *
 * @property Module $module
 */
class ClaimLicensesController extends Controller
{
    /**
     * @inheritdoc
     */
    public $defaultAction = 'claim';

    /**
     * @param string|null The domain that licenses should be owned by, if consolidating.
     */
    public $domain;

    /**
     * @inheritdoc
     */
    public function options($actionID)
    {
        $options = parent::options($actionID);
        $options[] = 'domain';
        return $options;
    }

    /**
     * Claims licenses for the user with the given username or email.
     *
     * @param string $username
     * @return int
     */
    public function actionClaim(string $username): int
    {
        /** @var User|UserBehavior|null $user */
        $user = Craft::$app->getUsers()->getUserByUsernameOrEmail($username);

        if (!$user) {
            $this->stderr('Invalid username or email' . PHP_EOL, Console::FG_RED);
            return 1;
        }

        if ($this->domain) {
            $domain = ltrim($this->domain, '@');
            $condition = [
                'and',
                ['like', 'email', "%@{$domain}", false],
                ['ownerId' => null],
            ];
            $cmsLicenses = (new Query())
                ->select(['email', 'count(*) as total'])
                ->from(['craftnet_cmslicenses'])
                ->where($condition)
                ->groupBy('email')
                ->indexBy('email')
                ->all();
            $pluginLicenses = (new Query())
                ->select(['email', 'count(*) as total'])
                ->from(['craftnet_pluginlicenses'])
                ->where($condition)
                ->groupBy('email')
                ->indexBy('email')
                ->all();

            if (empty($cmsLicenses) && empty($pluginLicenses)) {
                $this->stdout("There are no unclaimed Craft or plugin licenses associated with @{$domain} emails." . PHP_EOL, Console::FG_YELLOW);
                return 0;
            }

            $totalCmsLicenses = array_sum(ArrayHelper::getColumn($cmsLicenses, 'total'));
            $totalPluginLicenses = array_sum(ArrayHelper::getColumn($pluginLicenses, 'total'));
            $cmsLicenseEmails = ArrayHelper::getColumn($cmsLicenses, 'email');
            $pluginLicenseEmails = ArrayHelper::getColumn($pluginLicenses, 'email');
            $uniqueEmails = array_unique($cmsLicenseEmails + $pluginLicenseEmails);


            Console::output("{$totalCmsLicenses} unclaimed Craft licenses and {$totalPluginLicenses} unclaimed plugin licenses are associated with @{$domain} emails:");
            foreach ($uniqueEmails as $email) {
                $sum = ($cmsLicenses[$email]['total'] ?? 0) + ($pluginLicenses[$email]['total'] ?? 0);
                Console::output("- {$email} ({$sum})");
            }

            if (!$this->confirm("Are you sure you want to consolidate these licenses to {$username}?")) {
                return 0;
            }

            // claim Craft licenses
            $cmsLicenseManager = $this->module->getCmsLicenseManager();
            foreach ($cmsLicenseEmails as $email) {
                $cmsLicenseManager->claimLicenses($user, $email);
            }

            // claim plugin licenses
            $pluginLicenseManager = $this->module->getPluginLicenseManager();
            foreach ($pluginLicenseEmails as $email) {
                $pluginLicenseManager->claimLicenses($user, $email);
            }

            // claim guest orders
            $commerce = Commerce::getInstance();
            foreach ($uniqueEmails as $email) {
                if (!empty($orders = $commerce->getOrders()->getOrdersByEmail($email))) {
                    $commerce->getCustomers()->consolidateOrdersToUser($user, $orders);
                }
            }
            $num = $totalCmsLicenses + $totalPluginLicenses;
        } else {
            $num = $this->module->getCmsLicenseManager()->claimLicenses($user);
            $num += $this->module->getPluginLicenseManager()->claimLicenses($user);
            Commerce::getInstance()->getCustomers()->consolidateOrdersToUser($user);
        }

        $this->stdout("{$num} licenses claimed" . PHP_EOL, Console::FG_GREEN);
        return 0;
    }
}
