<?php

namespace craftnet\controllers\api;

use Craft;
use craft\commerce\elements\Subscription;
use craft\elements\User;
use craft\helpers\Json;
use craftnet\controllers\id\DeveloperSupportController;
use craftnet\errors\ValidationException;
use craftnet\events\ZendeskEvent;
use craftnet\helpers\Zendesk;
use yii\db\Expression;
use yii\web\BadRequestHttpException;

/**
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class ZendeskController extends BaseApiController
{
    /**
     * @event ZendeskEvent
     */
    const EVENT_UPDATE_TICKET = 'updateTicket';

    /**
     * @return string
     * @throws BadRequestHttpException
     * @throws ValidationException
     */
    public function actionCreateTicket()
    {
        $this->_validateSecret();
        $payload = $this->getPayload('zendesk-create-ticket');
        $email = strtolower($payload->email);

        $userId = User::find()
            ->select(['elements.id'])
            ->andWhere(new Expression('lower([[email]]) = :email', [':email' => $email]))
            ->asArray()
            ->scalar();

        // No user, no plan
        if (!$userId) {
            return '';
        }

        // See if the the user is on a paid plan
        if ($this->_checkPlan($userId, DeveloperSupportController::PLAN_PREMIUM)) {
            $plan = DeveloperSupportController::PLAN_PREMIUM;
        } else if ($this->_checkPlan($userId, DeveloperSupportController::PLAN_PRO)) {
            $plan = DeveloperSupportController::PLAN_PRO;
        }

        if (!isset($plan)) {
            return '';
        }

        $tags = array_filter(explode(' ', $payload->tags));
        $tags[] = $plan;

        $this->trigger(self::EVENT_UPDATE_TICKET, new ZendeskEvent([
            'ticketId' => $payload->id,
            'email' => $email,
            'tags' => $tags,
            'plan' => $plan,
        ]));

        // Add the tag to the ticket
        Zendesk::client()->tickets()->update($payload->id, [
            'priority' => 'normal',
            'tags' => $tags,
        ]);

        return '';
    }

    public function actionTest()
    {
        $this->_validateSecret();

        Craft::$app->getMailer()->compose()
            ->setSubject('Zendesk Test Webhook')
            ->setTextBody(Json::encode($this->getPayload(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))
            ->setTo(explode(',', getenv('TEST_EMAIL')))
            ->send();

        return '';
    }

    /**
     * @param int $userId
     * @param string $plan
     * @return bool
     */
    private function _checkPlan(int $userId, string $plan): bool
    {
        return Subscription::find()
            ->plan($plan)
            ->userId($userId)
            ->isExpired(false)
            ->exists();
    }

    /**
     * @throws BadRequestHttpException
     */
    private function _validateSecret()
    {
        $secret = Craft::$app->getRequest()->getRequiredQueryParam('secret');

        if ($secret !== getenv('ZENDESK_SECRET')) {
            throw new BadRequestHttpException('Invalid request body.');
        }
    }
}
