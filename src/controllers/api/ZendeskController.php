<?php

namespace craftnet\controllers\api;

use Craft;
use craft\commerce\elements\Subscription;
use craft\elements\User;
use craft\helpers\Json;
use craftnet\controllers\id\DeveloperSupportController;
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

        $user = User::find()->email($payload->email)->one();
        $tags = [];

        // See if the the user is on a paid plan
        if ($user) {
            if (Subscription::find()->plan(DeveloperSupportController::PLAN_PREMIUM)->userId($user->id)->isExpired(false)->one()) {
                $tag[] = 'premium';
            } else if (Subscription::find()->plan(DeveloperSupportController::PLAN_PRO)->userId($user->id)->isExpired(false)->one()) {
                $tag[] = 'pro';
            }
        }

        if (!empty($tags)) {
            $this->_client()->tickets()->update($payload->id, [
                'priority' => 'normal',
                'tags' => $tags,
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
