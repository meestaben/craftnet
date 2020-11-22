<?php

namespace craftnet\controllers\id;

use AdamPaterson\OAuth2\Client\Provider\Stripe as StripeOauthProvider;
use Craft;
use craft\commerce\Plugin as Commerce;
use craft\commerce\stripe\gateways\PaymentIntents;
use craft\elements\User;
use craft\helpers\UrlHelper;
use craftnet\behaviors\UserBehavior;
use League\OAuth2\Client\Token\AccessToken;
use Stripe\Account;
use Stripe\Stripe;
use yii\web\HttpException;
use yii\web\Response;


/**
 * Class StripeController
 */
class StripeController extends BaseController
{
    // Public Methods
    // =========================================================================

    /**
     * OAuth connect to Stripe.
     *
     * @return Response
     */
    public function actionConnect(): Response
    {
        $provider = $this->_getStripeProvider();
        $options = [
            'scope' => 'read_write'
        ];

        Craft::$app->getSession()->set('stripe.referrer', $this->request->getReferrer());
        $authorizationUrl = $provider->getAuthorizationUrl($options);

        return $this->redirect($authorizationUrl);
    }

    /**
     * OAuth callback.
     *
     * @return Response
     */
    public function actionCallback(): Response
    {
        /** @var User|UserBehavior $user */
        $user = Craft::$app->getUser()->getIdentity();
        $provider = $this->_getStripeProvider();
        $code = $this->request->getParam('code');

        $accessToken = $provider->getAccessToken('authorization_code', [
            'code' => $code
        ]);

        $resourceOwner = $provider->getResourceOwner($accessToken);

        $user->stripeAccessToken = $accessToken->getToken();
        $user->stripeAccount = $resourceOwner->getId();

        // set their country
        Stripe::setApiKey($user->stripeAccessToken);
        $account = Account::retrieve();
        $user->country = $account->country;

        $user->saveDeveloperInfo();

        $referrer = Craft::$app->getSession()->get('stripe.referrer');

        return $this->redirect($referrer);
    }

    /**
     * OAuth disconnect from Stripe.
     *
     * @return Response
     */
    public function actionDisconnect(): Response
    {
        /** @var User|UserBehavior $user */
        $user = Craft::$app->getUser()->getIdentity();

        $provider = $this->_getStripeProvider();
        $accessToken = new AccessToken(['access_token' => $user->stripeAccessToken]);
        $resourceOwner = $provider->getResourceOwner($accessToken);
        $accountId = $resourceOwner->getId();

        $craftIdConfig = Craft::$app->getConfig()->getConfigFromFile('craftid');

        Stripe::setClientId($craftIdConfig['stripeClientId']);
        Stripe::setApiKey($craftIdConfig['stripeApiKey']);

        $account = Account::retrieve($accountId);
        $account->deauthorize();

        $user->stripeAccessToken = null;
        $user->stripeAccount = null;
        $user->saveDeveloperInfo();

        return $this->asJson(['success' => true]);
    }

    /**
     * Returns Stripe account for the current user.
     *
     * @return Response
     */
    public function actionAccount(): Response
    {
        /** @var User|UserBehavior $user */
        $user = Craft::$app->getUser()->getIdentity();

        if ($user->stripeAccessToken) {
            Stripe::setApiKey($user->stripeAccessToken);
            $account = Account::retrieve();
            return $this->asJson($account);
        }

        return $this->asJson(null);
    }

    /**
     * Saves a new credit card and sets it as default source for the Stripe customer.
     *
     * @return Response|null
     * @throws \Throwable if something went wrong when adding the payment source
     */
    public function actionSaveCard(): Response
    {
        $this->requirePostRequest();

        $order = null;

        $plugin = Commerce::getInstance();
        $paymentSources = $plugin->getPaymentSources();

        // Are we paying anonymously?
        $userId = Craft::$app->getUser()->getId();

        if (!$userId) {
            throw new HttpException(401, Craft::t('commerce', 'Not authorized to save a credit card.'));
        }

        /** @var PaymentIntents $gateway */
        $gateway = $plugin->getGateways()->getGatewayById(getenv('STRIPE_GATEWAY_ID'));

        if (!$gateway || !$gateway->supportsPaymentSources()) {
            $error = Craft::t('commerce', 'There is no gateway selected that supports payment sources.');
            return $this->asErrorJson($error);
        }

        // Remove existing payment sources
        $existingPaymentSources = $paymentSources->getAllGatewayPaymentSourcesByUserId($gateway->id, $userId);
        foreach ($existingPaymentSources as $paymentSource) {
            $paymentSources->deletePaymentSourceById($paymentSource->id);
        }

        // Get the payment method' gateway adapter's expected form model
        $paymentForm = $gateway->getPaymentFormModel();
        $paymentForm->setAttributes($this->request->getBodyParams(), false);
        $description = 'Default Source';

        $error = '';

        try {
            $paymentSource = $paymentSources->createPaymentSource($userId, $gateway, $paymentForm, $description);

            if ($paymentSource) {
                $card = $paymentSource->response;

                return $this->asJson([
                    'success' => true,
                    'card' => $card,
                ]);
            }
        } catch (\Throwable $exception) {
            $error = $exception->getMessage();
        }

        return $this->asJson(['error' => $error, 'paymentForm' => $paymentForm->getErrors()]);
    }

    /**
     * Removes the default payment source.
     *
     * @return Response
     * @throws \Throwable
     */
    public function actionRemoveCard(): Response
    {
        $user = Craft::$app->getUser()->getIdentity();

        $paymentSourcesService = Commerce::getInstance()->getPaymentSources();

        $paymentSources = $paymentSourcesService->getAllPaymentSourcesByUserId($user->id);

        if (count($paymentSources)) {
            $result = $paymentSourcesService->deletePaymentSourceById($paymentSources[0]->id);

            if (!$result) {
                return $this->asErrorJson('Couldn’t delete credit card.');
            }
        }

        return $this->asJson(['success' => true]);
    }

    // Private Methods
    // =========================================================================

    /**
     * @return StripeOauthProvider
     */
    private function _getStripeProvider(): StripeOauthProvider
    {
        $craftIdConfig = Craft::$app->getConfig()->getConfigFromFile('craftid');

        $provider = new StripeOauthProvider([
            'clientId' => $craftIdConfig['stripeClientId'],
            'clientSecret' => $craftIdConfig['stripeApiKey'],
            'redirectUri' => UrlHelper::actionUrl('craftnet/id/stripe/callback'),
        ]);

        return $provider;
    }
}
