<?php

namespace craftnet\controllers\api\v1;

use Craft;
use craft\commerce\Plugin as Commerce;
use craftnet\controllers\api\BaseApiController;
use yii\helpers\Json;
use yii\web\Response;
use yii\web\UnauthorizedHttpException;

/**
 * Class AccountController
 */
class AccountController extends BaseApiController
{
    // Public Methods
    // =========================================================================

    /**
     * Handles /v1/account requests.
     *
     * @return Response
     */
    public function actionIndex(): Response
    {
        if (($user = Craft::$app->getUser()->getIdentity(false)) === null) {
            throw new UnauthorizedHttpException('Not Authorized');
        }


        // Purchased plugins

        $purchasedPlugins = [];

        foreach ($user->purchasedPlugins->all() as $purchasedPlugin) {
            $purchasedPlugins[] = [
                'name' => $purchasedPlugin->title,
                'developerName' => $purchasedPlugin->getAuthor()->developerName,
                'developerUrl' => $purchasedPlugin->getAuthor()->developerUrl,
            ];
        }


        // Credit cards

        $card = null;
        $cardToken = null;
        $paymentSources = Commerce::getInstance()->getPaymentSources()->getAllPaymentSourcesByUserId($user->id);

        if (count($paymentSources) > 0) {
            $paymentSource = $paymentSources[0];
            $cardToken = $paymentSource->token;
            $response = Json::decode($paymentSource->response);

            if (isset($response['object'])) {
                switch ($response['object']) {
                    case 'card':
                        $card = $response;
                        break;

                    case 'source':
                        $card = $response['card'];
                        break;

                    case 'payment_method':
                        $card = $response['card'];
                        break;
                }
            }
        }
        
        // Billing address

        $billingAddressArray = null;

        $customer = Commerce::getInstance()->getCustomers()->getCustomerByUserId($user->id);

        if ($customer) {
            $billingAddress = $customer->getPrimaryBillingAddress();

            if ($billingAddress) {
                $billingAddressArray = $billingAddress->toArray();

                $country = $billingAddress->getCountry();

                if ($country) {
                    $billingAddressArray['country'] = $country->iso;
                }

                $state = $billingAddress->getState();

                if ($state) {
                    $billingAddressArray['state'] = $state->abbreviation;
                }
            }
        }

        return $this->asJson([
            'id' => $user->getId(),
            'name' => $user->getFullName(),
            'email' => $user->email,
            'username' => $user->username,
            'purchasedPlugins' => $purchasedPlugins,
            'card' => $card,
            'cardToken' => $cardToken,
            'billingAddress' => $billingAddressArray
        ]);
    }
}
