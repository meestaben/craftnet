<?php

namespace craftnet\controllers\api\v1;

use Craft;
use craft\commerce\errors\PaymentException;
use craft\commerce\errors\PaymentSourceException;
use craft\commerce\models\PaymentSource;
use craft\commerce\models\Transaction;
use craft\commerce\Plugin as Commerce;
use craft\commerce\stripe\gateways\PaymentIntents as StripeGateway;
use craft\commerce\stripe\models\forms\payment\PaymentIntent as PaymentForm;
use craft\commerce\stripe\Plugin as Stripe;
use craft\helpers\StringHelper;
use craftnet\errors\ValidationException;
use Stripe\Customer as StripeCustomer;
use Stripe\Error\Base as StripeError;
use Stripe\PaymentMethod as StripePaymentMethod;
use yii\base\Exception;
use yii\base\UserException;
use yii\web\BadRequestHttpException;
use yii\web\Response;

/**
 * Class PaymentsController
 */
class PaymentsController extends CartsController
{
    // Properties
    // =========================================================================

    public $defaultAction = 'pay';

    // Public Methods
    // =========================================================================

    /**
     * Processes a payment for an order.
     *
     * @return Response
     * @throws Exception
     * @throws ValidationException if the order number isn't valid or isn't ready to be purchased
     * @throws BadRequestHttpException if there was an issue with the payment
     */
    public function actionPay(): Response
    {
        $payload = $this->getPayload('payment-request');

        try {
            $cart = $this->getCart($payload->orderNumber);
        } catch (UserException $e) {
            throw new ValidationException([
                [
                    'param' => 'orderNumber',
                    'message' => $e->getMessage(),
                    'code' => $e->getCode() === 404 ? self::ERROR_CODE_MISSING : self::ERROR_CODE_INVALID,
                ]
            ], null, 0, $e);
        }

        $errors = [];
        $commerce = Commerce::getInstance();

        // make sure the cart has an email
        if (!$cart->getEmail()) {
            throw new ValidationException([
                [
                    'param' => 'email',
                    'message' => 'The cart is missing an email',
                    'code' => self::ERROR_CODE_INVALID,
                ],
            ]);
        }

        // make sure the cart has a billing address
        if ($cart->getBillingAddress() === null) {
            $errors[] = [
                'param' => 'orderNumber',
                'message' => 'The cart is missing a billing address',
                'code' => self::ERROR_CODE_INVALID,
            ];
        }

        // make sure the cart isn't empty
        if ($cart->getIsEmpty()) {
            $errors[] = [
                'param' => 'orderNumber',
                'message' => 'The cart is empty',
                'code' => self::ERROR_CODE_INVALID,
            ];
        }

        // make sure the cost is in line with what they were expecting
        $totalPrice = $cart->getTotalPrice();
        if (round($payload->expectedPrice) < round($totalPrice)) {
            $formatter = Craft::$app->getFormatter();
            $fmtExpected = $formatter->asCurrency($payload->expectedPrice, 'USD', [], [], true);
            $fmtTotal = $formatter->asCurrency($totalPrice, 'USD', [], [], true);
            $errors[] = [
                'param' => 'expectedPrice',
                'message' => "Expected price ({$fmtExpected}) was less than the order total ({$fmtTotal}).",
                'code' => self::ERROR_CODE_INVALID,
            ];
        }

        // if there are any errors, send them now before the point of no return
        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        // only process a payment if there's a price
        if ($totalPrice) {
            // get the gateway
            /** @var StripeGateway $gateway */
            $gateway = $commerce->getGateways()->getGatewayById(getenv('STRIPE_GATEWAY_ID'));

            // pay
            /** @var PaymentForm $paymentForm */
            $paymentForm = $gateway->getPaymentFormModel();

            try {
                $this->_populatePaymentForm($payload, $gateway, $paymentForm);
                $commerce->getPayments()->processPayment($cart, $paymentForm, $redirect, $transaction);
            } catch (StripeError $e) {
                throw new BadRequestHttpException($e->getMessage(), 0, $e);
            } catch (PaymentException $e) {
                throw new BadRequestHttpException($e->getMessage(), 0, $e);
            }
        } else {
            // just mark it as complete since it's a free order
            $cart->markAsComplete();
        }

        /** @var Transaction $transaction */
        $response = ['completed' => true];
        if (isset($transaction)) {
            $response['transaction'] = $transaction->toArray();
        }
        return $this->asJson($response);
    }

    /**
     * Populates a Stripe payment form from the payload.
     *
     * @param \stdClass $payload
     * @param StripeGateway $gateway
     * @param PaymentForm $paymentForm
     * @throws PaymentSourceException
     */
    private function _populatePaymentForm(\stdClass $payload, StripeGateway $gateway, PaymentForm $paymentForm)
    {
        // use the payload's token by default
        $paymentForm->paymentMethodId = $payload->token;

        $commerce = Commerce::getInstance();
        $stripe = Stripe::getInstance();
        $paymentSourcesService = $commerce->getPaymentSources();
        $customersService = $stripe->getCustomers();

        // If the request is anonymous, we still have to create a customer so that
        // we can pass the source as a payment method. Even if it's sort of temporary
        if ((($user = Craft::$app->getUser()->getIdentity(false)) === null) || !$payload->makePrimary) {

            $cart = $this->getCart($payload->orderNumber);
            $address = $cart->getBillingAddress();

            $customerData = [
                'address' => [
                    'line1' => $address->address1,
                    'line2' => $address->address2,
                    'country' => $address->getCountry()->iso,
                    'city' => $address->city,
                    'postal_code' => $address->zipCode,
                    'state' => $address->getState(),
                ],
                'name' => $address->getFullName(),
                'description' => 'Guest customer created for order #' . $payload->orderNumber,
                'email' => $cart->getEmail(),
                'source' => $payload->token,
            ];

            $customer = StripeCustomer::create($customerData);
            $paymentForm->customer = $customer->id;

            return;
        }

        // Otherwise, see if the token is for an existing Stripe source
        $existingPaymentSources = $paymentSourcesService->getAllGatewayPaymentSourcesByUserId($gateway->id, $user->id);

        foreach ($existingPaymentSources as $paymentSource) {
            if ($paymentSource->token === $payload->token) {
                $customer = $customersService->getCustomer($gateway->id, $user);
                $paymentForm->customer = $customer->reference;
                return;
            }
        }

        // delete any existing payment sources
        // todo: remove this if we ever add support for multiple cards
        foreach ($existingPaymentSources as $paymentSource) {
            $paymentSourcesService->deletePaymentSourceById($paymentSource->id);
        }

        // get the Stripe customer
        $customer = $customersService->getCustomer($gateway->id, $user);
        /** @var StripeCustomer $stripeCustomer */
        $stripeCustomer = StripeCustomer::retrieve($customer->reference);

        // Figure out if we're dealing with a source or a payment method
        if (StringHelper::startsWith($paymentForm->paymentMethodId, 'pm_')) {
            // Attach the payment method
            $paymentMethod = StripePaymentMethod::retrieve($paymentForm->paymentMethodId);
            $stripeResponse = $paymentMethod->attach(['customer' => $stripeCustomer->id]);
        } else {
            // Attach the payment source
            $stripeResponse = StripeCustomer::createSource($stripeCustomer->id, ['source' => $paymentForm->paymentMethodId]);
        }

        // set it as the customer default for subscriptions
        $stripeCustomer->invoice_settings = [
            'default_payment_method' => $paymentForm->paymentMethodId
        ];
        $stripeCustomer->save();

        // save it for Commerce
        $paymentSource = new PaymentSource([
            'userId' => $user->id,
            'gatewayId' => $gateway->id,
            'token' => $stripeResponse->id,
            'response' => $stripeResponse->jsonSerialize(),
            'description' => 'Default Source',
        ]);

        if (!$paymentSourcesService->savePaymentSource($paymentSource)) {
            throw new PaymentSourceException('Could not create the payment method: ' . implode(', ', $paymentSource->getErrorSummary(true)));
        }

        // update the payment token and customer
        $paymentForm->token = $stripeResponse->id;
        $paymentForm->customer = $customer->reference;
    }
}
