<?php

namespace craftnet\oauthserver\controllers;

use Craft;
use craft\web\Controller;
use craftnet\oauthserver\Module;
use craftnet\oauthserver\server\Entities\UserEntity;
use craftnet\oauthserver\server\Repositories\AccessTokenRepository;
use craftnet\oauthserver\server\Repositories\AuthCodeRepository;
use craftnet\oauthserver\server\Repositories\ClientRepository;
use craftnet\oauthserver\server\Repositories\RefreshTokenRepository;
use craftnet\oauthserver\server\Repositories\ScopeRepository;
use craftnet\oauthserver\server\Repositories\UserRepository;
use craftnet\oauthserver\server\Response;
use DateInterval;
use Exception;
use GuzzleHttp\Psr7\ServerRequest;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\Grant\ClientCredentialsGrant;
use League\OAuth2\Server\Grant\ImplicitGrant;
use League\OAuth2\Server\Grant\PasswordGrant;
use League\OAuth2\Server\Grant\RefreshTokenGrant;

/**
 * Class OauthController
 */
class OauthController extends Controller
{
    // Properties
    // =========================================================================

    /**
     * @var bool
     */
    public $enableCsrfValidation = false;

    /**
     * @var bool
     */
    protected $allowAnonymous = true;

    // Public Methods
    // =========================================================================

    /**
     * @return \yii\web\Response
     */
    public function actionAccessToken()
    {
        try {
            $this->requirePostRequest();

            // Authorization server
            $server = $this->getAuthorizationServer();

            // Request
            $request = $this->getPsr7Request();

            // Return response
            $response = new Response(); // PSR7 compliant response
            $result = $server->respondToAccessTokenRequest($request, $response);
            $resultJson = $result->getBody();
            $data = json_decode($resultJson, true);

            return $this->asJson($data);
        } catch (OAuthServerException $e) {
            return $this->asErrorJson($e->getHint());
        } catch (\Exception $e) {
            return $this->asErrorJson($e->getMessage());
        }
    }

    /**
     * @return \yii\web\Response|string
     */
    public function actionAuthorize()
    {
        $this->requireLogin();

        // Redirect the user to an authorization page and ask the user to approve the client and the scopes
        if (!Craft::$app->getRequest()->getBodyParam('approve') && !Craft::$app->getRequest()->getBodyParam('deny')) {
            $requestedScopes = explode(" ", Craft::$app->getRequest()->getParam('scope'));

            $scopes = [];

            foreach ($requestedScopes as $requestedScope) {
                foreach (Module::getInstance()->getOauth()->getScopes() as $scope => $description) {
                    if ($scope == $requestedScope) {
                        $scopes[$scope] = $description;
                    }
                }
            }

            return $this->renderTemplate('oauth/clientApproval', [
                'scopes' => $scopes,
            ]);
        } else {
            $rememberMe = Craft::$app->getRequest()->getBodyParam('rememberMe');

            $customAccessTokenExpiry = null;

            if ($rememberMe) {
                $customAccessTokenExpiry = 'P30D';
            }

            $currentCraftUser = Craft::$app->getUser()->getIdentity();

            $server = $this->getAuthorizationServer($customAccessTokenExpiry);

            $serverRequest = $this->getPsr7Request();

            // Validate the HTTP request and return an AuthorizationRequest object.
            $authRequest = $server->validateAuthorizationRequest($serverRequest);

            // Once the user is logged in set the user on the AuthorizationRequest
            $user = new UserEntity();
            $user->setIdentifier($currentCraftUser->id);

            // PSR7 compliant response
            $response = new Response();

            try {
                // Set the user on the auth request
                $authRequest->setUser($user);


                // Once the user has approved or denied the client update the status
                // (true = approved, false = denied)
                $approved = false;

                if (Craft::$app->getRequest()->getBodyParam('approve')) {
                    $approved = true;
                }

                $authRequest->setAuthorizationApproved($approved);

                // Return the HTTP redirect response

                $redirectResponse = $server->completeAuthorizationRequest($authRequest, $response);

                return Craft::$app->getResponse()->redirect($redirectResponse->getHeaders()['Location'][0]);
            } catch (OAuthServerException $exception) {
                $exceptionResponse = $exception->generateHttpResponse($response);
                $location = $exceptionResponse->getHeaderLine('Location');

                if ($location) {
                    return Craft::$app->getResponse()->redirect($location);
                }

                throw $exception;
            }
        }
    }

    /**
     * @param $accessToken
     *
     * @return string
     */
    public function actionRevoke($accessToken)
    {
        $jwt = $accessToken;
        $token = Module::getInstance()->getOauth()->parseJwt($jwt);
        $claims = $token->getClaims();

        $accessToken = Module::getInstance()->getAccessTokens()->getAccessTokenByIdentifier($claims['jti']);

        if ($accessToken) {
            $accessToken->isRevoked = true;

            Module::getInstance()->getAccessTokens()->saveAccessToken($accessToken);
        }

        return 'done';
    }

    // Private Methods
    // =========================================================================

    /**
     * Builds a PSR7 compliant request from the current request.
     *
     * @return ServerRequest|static
     */
    private function getPsr7Request()
    {
        // Get current request

        $craftRequest = Craft::$app->getRequest();


        // Instantiate and return a PSR7 compliant ServerRequest from current request

        $method = $craftRequest->getMethod();
        $uri = $craftRequest->getUrl();
        $headers = $craftRequest->getHeaders()->toArray();

        $serverRequest = new ServerRequest($method, $uri, $headers);

        $queryParams = $craftRequest->getQueryParams();
        $serverRequest = $serverRequest->withQueryParams($queryParams);

        $data = $craftRequest->getBodyParams();
        $serverRequest = $serverRequest->withParsedBody($data);

        return $serverRequest;
    }

    /**
     * Returns the authorization server.
     *
     * @return AuthorizationServer
     * @throws Exception
     */
    private function getAuthorizationServer($customAccessTokenExpiry = null)
    {
        $grantTypes = [
            'client_credentials' => 'ClientCredentialsGrant',
            'password' => 'PasswordGrant',
            'refresh_token' => 'RefreshTokenGrant',
            'authorization_code' => 'AuthCodeGrant',
        ];

        $responseTypes = [
            'client_credentials' => 'ClientCredentialsGrant',
            'token' => 'ImplicitGrant',
            'code' => 'AuthCodeGrant',
        ];

        $grantTypeParam = Craft::$app->getRequest()->getBodyParam('grant_type');
        $responseTypeParam = Craft::$app->getRequest()->getParam('response_type');

        if ($grantTypeParam) {
            if (isset($grantTypes[$grantTypeParam])) {
                $grantClass = $grantTypes[$grantTypeParam];
            }
        } else if ($responseTypeParam) {
            if (isset($responseTypes[$responseTypeParam])) {
                $grantClass = $responseTypes[$responseTypeParam];
            }
        }

        if (isset($grantClass)) {
            $grant = Module::getInstance()->getOauth()->getGrant($grantClass);

            if ($grant) {
                // Initialize server repositories
                $clientRepository = new ClientRepository();
                $scopeRepository = new ScopeRepository();
                $accessTokenRepository = new AccessTokenRepository();
                $authCodeRepository = new AuthCodeRepository();
                $refreshTokenRepository = new RefreshTokenRepository();
                $userRepository = new UserRepository();

                // Private & public keys
                $privateKey = Module::getInstance()->getSettings()->privateKey;
                $publicKey = Module::getInstance()->getSettings()->publicKey;

                // Encryption key
                $encryptionKey = Module::getInstance()->getSettings()->encryptionKey;

                // Setup the authorization server
                $server = new AuthorizationServer(
                    $clientRepository,
                    $accessTokenRepository,
                    $scopeRepository,
                    $privateKey,
                    $encryptionKey
                );

                // Grant expiry

                $accessTokenExpiry = new DateInterval(Module::getInstance()->getSettings()->accessTokenExpiry);

                if ($customAccessTokenExpiry) {
                    $accessTokenExpiry = new DateInterval($customAccessTokenExpiry);
                }

                $refreshTokenExpiry = new DateInterval(Module::getInstance()->getSettings()->refreshTokenExpiry);

                // Instantiate grant
                switch ($grantClass) {
                    case 'ClientCredentialsGrant':
                        $grantType = new ClientCredentialsGrant();
                        break;
                    case 'PasswordGrant':
                        $grantType = new PasswordGrant($userRepository, $refreshTokenRepository);
                        $grantType->setRefreshTokenTTL($refreshTokenExpiry);
                        break;
                    case 'RefreshTokenGrant':
                        $grantType = new RefreshTokenGrant($refreshTokenRepository);
                        $grantType->setRefreshTokenTTL($refreshTokenExpiry);
                        break;
                    case 'AuthCodeGrant':
                        $authCodeExpiry = new DateInterval(Module::getInstance()->getSettings()->authCodeExpiry);
                        $grantType = new AuthCodeGrant($authCodeRepository, $refreshTokenRepository, $authCodeExpiry);
                        $grantType->setRefreshTokenTTL($refreshTokenExpiry);
                        break;
                    case 'ImplicitGrant':
                        $grantType = new ImplicitGrant($accessTokenExpiry);
                        break;
                    default:
                        throw new Exception("Grant not supported.");
                }

                // Enable this grant on the server
                $server->enableGrantType($grantType, $accessTokenExpiry);

                return $server;
            } else {
                throw new Exception("Grant not supported.");
            }
        } else {
            throw new Exception("Grant not supported.");
        }
    }
}
