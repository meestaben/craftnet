<?php

namespace craftnet\oauthserver\controllers;

use Craft;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use craftcms\oauth2\client\provider\CraftId;
use craftnet\oauthserver\Module as OauthServer;
use League\OAuth2\Client\Token\AccessToken;

/**
 * Class PlaygroundController
 */
class PlaygroundController extends Controller
{
    // Public Methods
    // =========================================================================

    /**
     * @return \yii\web\Response
     */
    public function actionIndex()
    {
        $variables['clients'] = OauthServer::getInstance()->getClients()->getClients();
        $variables['grants'] = OauthServer::getInstance()->getOauth()->getGrants();
        $variables['scopes'] = OauthServer::getInstance()->getOauth()->getScopes();

        $variables['grantHandle'] = Craft::$app->getSession()->get('oauthServer.playground.grantHandle');
        $variables['token'] = Craft::$app->getSession()->get('oauthServer.playground.token');

        $clientId = Craft::$app->getSession()->get('oauthServer.playground.clientId');
        $variables['client'] = OauthServer::getInstance()->getClients()->getClientById($clientId);

        return $this->renderTemplate('oauth-server/playground/_index', $variables);
    }

    /**
     * @return \yii\web\Response
     * @throws \Exception
     */
    public function actionGenerateAccessToken()
    {
        $grantHandle = Craft::$app->getRequest()->getParam('grantHandle');

        Craft::$app->getSession()->set('oauthServer.playground.grantHandle', $grantHandle);

        switch ($grantHandle) {
            case 'ImplicitGrant':
                return $this->connectImplicit();

            case 'AuthCodeGrant':
                return $this->connectAuthCode();

            case 'ClientCredentialsGrant':
                return $this->connectClientCredentials();

            case 'PasswordGrant':
                return $this->connectPassword();

            default:
                throw new \Exception('Grant “' . $grantHandle . '” not supported.');
        }
    }

    /**
     * @return \yii\web\Response
     */
    public function actionImplicitCallback()
    {
        $access_token = Craft::$app->getRequest()->getParam('access_token');
        $token_type = Craft::$app->getRequest()->getParam('token_type');
        $expires_in = Craft::$app->getRequest()->getParam('expires_in');
        $state = Craft::$app->getRequest()->getParam('state');

        if (!$access_token) {
            return $this->renderTemplate('oauth-server/playground/_implicit-callback');
        } else {
            $token = new AccessToken([
                'access_token' => $access_token,
                'token_type' => $token_type,
                'expires_in' => $expires_in,
                'state' => $state,
            ]);

            Craft::$app->getSession()->set('oauthServer.playground.token', $token);

            Craft::$app->getSession()->setNotice(Craft::t('app', 'Token created.'));

            return $this->asJson(['redirect' => UrlHelper::cpUrl('oauth-server/playground')]);
        }
    }

    /**
     * @return \yii\web\Response
     */
    public function actionAuthCodeCallback()
    {
        try {
            $clientId = Craft::$app->getSession()->get('oauthServer.playground.clientId');
            $client = OauthServer::getInstance()->getClients()->getClientById($clientId);
            $code = Craft::$app->getRequest()->getParam('code');
            $provider = $this->getAuthCodeProvider($client);

            if ($code) {
                $token = $provider->getAccessToken('authorization_code', [
                    'code' => $code,
                ]);

                Craft::$app->getSession()->set('oauthServer.playground.token', $token);

                Craft::$app->getSession()->setNotice(Craft::t('app', 'Token created.'));
            }
        } catch (\Exception $e) {
            Craft::$app->getSession()->setError(Craft::t('app', $e->getMessage()));
        }

        return $this->redirect('oauth-server/playground');
    }

    /**
     * @return \yii\web\Response
     */
    public function actionRefreshTestToken($refreshTokenIdentifier)
    {
        $clientId = Craft::$app->getSession()->get('oauthServer.playground.clientId');
        $client = OauthServer::getInstance()->getClients()->getClientById($clientId);
        $provider = $this->getRefreshTokenProvider($client);

        $newAccessToken = $provider->getAccessToken('refresh_token', [
            'refresh_token' => $refreshTokenIdentifier,
        ]);

        Craft::$app->getSession()->set('oauthServer.playground.token', $newAccessToken);
        Craft::$app->getSession()->setNotice(Craft::t('app', 'Token refreshed.'));


        return $this->redirect('oauth-server/playground');
    }

    public function actionResetTestToken()
    {
        Craft::$app->getSession()->remove('oauthServer.playground.token');
        Craft::$app->getSession()->remove('oauthServer.playground.clientId');

        Craft::$app->getSession()->setNotice(Craft::t('app', 'Test Token Reset.'));

        return $this->redirect('oauth-server/playground');
    }

    // Private Methods
    // =========================================================================

    /**
     * @param $client
     *
     * @return CraftId
     */
    private function getRefreshTokenProvider($client)
    {
        return new CraftId([
            'clientId' => $client->identifier,
            'clientSecret' => $client->secret,
        ]);
    }

    /**
     * @param $client
     *
     * @return CraftId
     */
    private function getAuthCodeProvider($client)
    {
        return new CraftId([
            'clientId' => $client->identifier,
            'clientSecret' => $client->secret,
            'redirectUri' => UrlHelper::actionUrl('oauth-server/playground/auth-code-callback'),
        ]);
    }

    /**
     * @return \yii\web\Response
     */
    private function connectImplicit()
    {
        $clientId = Craft::$app->getRequest()->getParam('clientId');
        $client = OauthServer::getInstance()->getClients()->getClientById($clientId);
        $scope = Craft::$app->getRequest()->getParam('scope');

        $provider = new CraftId([
            'clientId' => $client->identifier,
            'redirectUri' => UrlHelper::actionUrl('oauth-server/playground/implicit-callback'),
        ]);

        $authorizationUrl = $provider->getAuthorizationUrl([
            'scope' => $scope,
            'response_type' => 'token',
        ]);

        return $this->redirect($authorizationUrl);
    }

    /**
     * @return \yii\web\Response
     */
    private function connectAuthCode()
    {
        $clientId = Craft::$app->getRequest()->getParam('clientId');
        Craft::$app->getSession()->set('oauthServer.playground.clientId', $clientId);
        $client = OauthServer::getInstance()->getClients()->getClientById($clientId);
        $scope = Craft::$app->getRequest()->getParam('scope');

        $provider = $this->getAuthCodeProvider($client);

        $authorizationUrl = $provider->getAuthorizationUrl([
            'scope' => $scope,
            'response_type' => 'code',
        ]);

        return $this->redirect($authorizationUrl);
    }

    /**
     * @return \yii\web\Response
     */
    private function connectClientCredentials()
    {
        $clientId = Craft::$app->getRequest()->getParam('clientId');
        Craft::$app->getSession()->set('oauthServer.playground.clientId', $clientId);
        $client = OauthServer::getInstance()->getClients()->getClientById($clientId);
        // $scope = Craft::$app->getRequest()->getParam('scope');

        $provider = $this->getAuthCodeProvider($client);

        $token = $provider->getAccessToken('client_credentials');

        Craft::$app->getSession()->set('oauthServer.playground.token', $token);

        Craft::$app->getSession()->setNotice(Craft::t('app', 'Token created.'));

        return $this->redirect('oauth-server/playground');
    }

    /**
     * @return \yii\web\Response
     */
    private function connectPassword()
    {
        $clientId = Craft::$app->getRequest()->getParam('clientId');
        Craft::$app->getSession()->set('oauthServer.playground.clientId', $clientId);
        $client = OauthServer::getInstance()->getClients()->getClientById($clientId);
        // $scope = Craft::$app->getRequest()->getParam('scope');

        $provider = $this->getAuthCodeProvider($client);

        try {
            $token = $provider->getAccessToken('password', [
                'username' => 'demouser',
                'password' => 'testpass',
            ]);

            Craft::$app->getSession()->set('oauthServer.playground.token', $token);
        } catch (\Exception $e) {
            echo $e->getMessage();
        }

        Craft::$app->getSession()->setNotice(Craft::t('app', 'Token created.'));

        return $this->redirect('oauth-server/playground');
    }
}
