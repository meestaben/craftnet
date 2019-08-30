<?php

namespace craftnet\controllers\api;

use Craft;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use yii\web\BadRequestHttpException;

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

            $this->_client()->post("tickets/{$payload->id}.json", [
                RequestOptions::JSON => [
                    'ticket' => [
                        'priority' => 'normal',
                        'tags' => [$tag],
                    ],
                ],
            ]);
        }

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
     * @return Client
     */
    private function _client(): Client
    {
        return Craft::createGuzzleClient([
            'base_uri' => 'https://craftcms.zendesk.com/api/v2/',
            'auth' => [getenv('ZENDESK_USERNAME'), getenv('ZENDESK_PASSWORD')],
        ]);
    }
}
