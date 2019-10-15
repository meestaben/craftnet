<?php

namespace craftnet\controllers\api;

use Craft;
use craft\helpers\Json;
use craftnet\errors\ValidationException;
use craftnet\events\ZendeskEvent;
use craftnet\helpers\Zendesk;
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
        $email = mb_strtolower($payload->email);
        $plan = Zendesk::plan($email);

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
