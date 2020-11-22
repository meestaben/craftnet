<?php

namespace craftnet\controllers\api\v1;

use Craft;
use craft\elements\Entry;
use craft\helpers\Db;
use craft\helpers\Json;
use craftnet\controllers\api\BaseApiController;
use craftnet\records\StripeCustomer as StripeCustomerRecord;
use Stripe\Customer;
use Stripe\Source;
use Stripe\Stripe;
use yii\web\Response;

/**
 * Class CheckoutController
 */
class CheckoutController extends BaseApiController
{
    // Public Methods
    // =========================================================================

    /**
     * Handles /v1/checkout requests.
     *
     * @return Response
     */
    public function actionIndex(): Response
    {
        $userId = $this->request->getParam('craftId');
        $identity = $this->request->getParam('identity');
        $cardToken = $this->request->getParam('cardToken');
        $replaceCard = $this->request->getParam('replaceCard');
        $cartItems = $this->request->getParam('cartItems');

        $entry = new Entry();
        $entry->sectionId = 4;
        $entry->typeId = 6;
        $entry->authorId = 3;

        if ($userId) {
            $entry->customer = [$userId];

            if ($replaceCard && $cardToken) {
                /** @var StripeCustomerRecord|null $stripeCustomerRecord */
                $stripeCustomerRecord = StripeCustomerRecord::find()
                    ->where(Db::parseParam('userId', $userId))
                    ->one();

                if ($stripeCustomerRecord) {
                    if ($stripeCustomerRecord->reference) {
                        $craftIdConfig = Craft::$app->getConfig()->getConfigFromFile('craftid');

                        Stripe::setApiKey($craftIdConfig['stripeApiKey']);
                        $customer = Customer::retrieve($stripeCustomerRecord->reference);

                        if ($customer->default_source) {
                            /** @var Source $source */
                            $source = $customer->sources->retrieve($customer->default_source);
                            $source->detach();
                        }

                        /** @var Source $source */
                        $source = $customer->sources->create(['source' => $cardToken]);
                        $customer->default_source = $source->id;
                        $customer->save();
                    }
                }
            }
        } else {
            if (isset($identity['fullName'])) {
                $entry->customerName = $identity['fullName'];
            }

            if (isset($identity['email'])) {
                $entry->customerEmail = $identity['email'];
            }
        }

        if ($cardToken) {
            $entry->cardToken = $cardToken;
        }

        if ($cartItems) {
            $items = [];
            foreach ($cartItems as $item) {
                $items[] = $item['id'];
            }
            $entry->items = $items;
        }

        if (Craft::$app->getElements()->saveElement($entry)) {
            return $this->asJson($entry);
        }

        $errors = Json::encode($entry->getErrors());

        return $this->asErrorJson($errors);
    }
}
