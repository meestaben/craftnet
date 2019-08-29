<?php

namespace craftnet\controllers\api;

use Craft;
use craft\helpers\Json;

/**
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class ZendeskController extends BaseApiController
{
    public function actionCreateTicket()
    {
        Craft::$app->getMailer()->compose()
            ->setSubject('Zendesk Webhook')
            ->setTextBody(Json::encode($this->getPayload(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))
            ->setTo(explode(',', getenv('TEST_EMAIL')))
            ->send();

        return '';
    }
}
