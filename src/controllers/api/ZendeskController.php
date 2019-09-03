<?php

namespace craftnet\controllers\api;

use Craft;
use craft\helpers\Json;
use yii\web\BadRequestHttpException;
use Zendesk\API\HttpClient;
use Zendesk\API\Utilities\Auth;

/**
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class ZendeskController extends BaseApiController
{
    public function actionCreateTicket()
    {
        $this->_validateSecret();
        $payload = $this->getPayload('zendesk-create-ticket');

        // See if the the email is on a paid plan
        // ...

        if (true) {
            $tag = 'pro';

            $this->_client()->tickets()->update($payload->id, [
                'priority' => 'normal',
                'tags' => [$tag],
            ]);
        }

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

    /**
     * @return HttpClient
     */
    private function _client(): HttpClient
    {
        $client = new HttpClient('craftcms');
        $client->setAuth(Auth::BASIC, [
            'username' => getenv('ZENDESK_USERNAME'),
            'token' => getenv('ZENDESK_TOKEN'),
        ]);
        return $client;
    }
}
