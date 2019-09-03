<?php

namespace craftnet\controllers\id;

use Craft;
use craft\commerce\elements\Subscription;
use craft\commerce\Plugin as Commerce;
use craft\commerce\stripe\gateways\PaymentIntents;
use craft\commerce\stripe\models\forms\CancelSubscription;
use craft\commerce\stripe\models\forms\Subscription as SubscriptionForm;
use craft\commerce\stripe\models\forms\SwitchPlans;
use craft\elements\User;
use craft\helpers\DateTimeHelper;
use craft\web\Controller;
use yii\helpers\Json;
use yii\web\Response;

/**
 * Class DeveloperSupportController
 */
class DeveloperSupportController extends Controller
{
    // Constants
    // =========================================================================

    const PLAN_STANDARD = 'basic';
    const PLAN_STANDARD_NAME = 'Basic';
    const PLAN_PRO = 'pro';
    const PLAN_PREMIUM = 'premium';

    // Properties
    // =========================================================================
    /** @var User */
    private $_user;

    // Public Methods
    // =========================================================================

    public function beforeAction($action)
    {
        $this->requireLogin();
        $this->_user = Craft::$app->getUser()->getIdentity();

        return parent::beforeAction($action);
    }

    /**
     * This action returns the developer support subscription information for the current user.
     *
     * @return Response
     */
    public function actionGetSubscriptionInfo(): Response
    {
        return $this->asJson($this->_getSubscriptionData());
    }

    /**
     * This action cancels an active subscription.
     *
     * @return Response
     */
    public function actionCancelSubscription(): Response
    {
        $subscriptionUid = Craft::$app->getRequest()->getRequiredBodyParam('subscription');
        $subscription = Subscription::find()->userId($this->_user->id)->uid($subscriptionUid)->one();

        if ($subscription) {
            /** @var CancelSubscription $cancelForm */
            $cancelForm = $subscription->getGateway()->getCancelSubscriptionFormModel();
            $cancelForm->cancelImmediately = $subscription->getSubscriptionData()['status'] === 'trialing';

            Commerce::getInstance()->getSubscriptions()->cancelSubscription($subscription, $cancelForm);
        }

        return $this->asJson($this->_getSubscriptionData());
    }

    /**
     * This action reactivates an active subscription.
     *
     * @return Response
     */
    public function actionReactivateSubscription(): Response
    {
        $subscriptionUid = Craft::$app->getRequest()->getRequiredBodyParam('subscription');
        $subscription = Subscription::find()->userId($this->_user->id)->uid($subscriptionUid)->one();

        // If re-activating Premium, cancel any pro subscriptions.
        if ($subscription->getPlan()->handle === self::PLAN_PREMIUM) {
            $proSubscription = Subscription::find()->plan(self::PLAN_PRO)->userId($this->_user->id)->isExpired(false)->one();

            if ($proSubscription) {
                /** @var CancelSubscription $cancelForm */
                $cancelForm = $subscription->getGateway()->getCancelSubscriptionFormModel();
                $cancelForm->cancelImmediately = true;
                Commerce::getInstance()->getSubscriptions()->cancelSubscription($proSubscription, $cancelForm);
            }
        }

        if ($subscription) {
            Commerce::getInstance()->getSubscriptions()->reactivateSubscription($subscription);
        }

        return $this->asJson($this->_getSubscriptionData());
    }


    /**
     * This action subscribes user to a plan.
     *
     * @return Response
     */
    public function actionSubscribe(): Response
    {
        $plan = Craft::$app->getRequest()->getRequiredBodyParam('plan');

        $commerce = Commerce::getInstance();
        $subscriptionService = $commerce->getSubscriptions();

        $proSubscription = Subscription::find()->plan(self::PLAN_PRO)->userId($this->_user->id)->isExpired(false)->one();
        $premiumSubscription = Subscription::find()->plan(self::PLAN_PREMIUM)->userId($this->_user->id)->isExpired(false)->one();

        /** @var PaymentIntents $gateway */
        $gateway = $commerce->getGateways()->getGatewayById(getenv('STRIPE_GATEWAY_ID'));

        switch ($plan) {
            case self::PLAN_PRO:
                // No doubles
                if ($proSubscription) {
                    break;
                }

                // If premiums exists, can only be expiring.
                if ($premiumSubscription && !$premiumSubscription->getSubscriptionData()['cancel_at_period_end']) {
                    break;
                }

                $subscriptionForm = $gateway->getSubscriptionFormModel();

                // If premium is expiring, mark it's end the end of this subscription's trial.
                if ($premiumSubscription) {
                    $trialEndTime = $premiumSubscription->getSubscriptionData()['current_period_end'];
                    $subscriptionForm->trialEnd = $trialEndTime;
                }

                $subscriptionService->createSubscription($this->_user, $commerce->getPlans()->getPlanByHandle(self::PLAN_PRO), $subscriptionForm);
                break;

            case self::PLAN_PREMIUM:
                // No doubles
                if ($premiumSubscription) {
                    break;
                }

                // If pro exists, they should be using "switch"
                if ($proSubscription) {
                    break;
                }

                $subscriptionService->createSubscription($this->_user, $commerce->getPlans()->getPlanByHandle(self::PLAN_PREMIUM), $gateway->getSubscriptionFormModel());
                break;
        }

        return $this->asJson($this->_getSubscriptionData());
    }

    /**
     * This action switches the subscription plan for a user.
     *
     * @return Response
     * @throws \craft\commerce\errors\SubscriptionException
     * @throws \yii\base\InvalidConfigException
     */
    public function actionSwitchPlan(): Response
    {
        $plan = Craft::$app->getRequest()->getRequiredBodyParam('plan');

        $commerce = Commerce::getInstance();
        $subscriptionService = $commerce->getSubscriptions();

        $proSubscription = Subscription::find()->plan(self::PLAN_PRO)->userId($this->_user->id)->isExpired(false)->one();
        $premiumSubscription = Subscription::find()->plan(self::PLAN_PREMIUM)->userId($this->_user->id)->isExpired(false)->one();

        /** @var PaymentIntents $gateway */
        $gateway = $commerce->getGateways()->getGatewayById(getenv('STRIPE_GATEWAY_ID'));

        /** @var SubscriptionForm $subscriptionForm */
        $subscriptionForm = $gateway->getSubscriptionFormModel();

        /** @var CancelSubscription $cancelForm */
        $cancelForm = $gateway->getCancelSubscriptionFormModel();
        $cancelForm->cancelImmediately = false;

        switch ($plan) {
            case self::PLAN_PRO:
                // No doubles
                if ($proSubscription) {
                    break;
                }

                // Premium must exist in active state
                if (!$premiumSubscription
                    || $premiumSubscription->getSubscriptionData()['status'] !== 'active'
                    || $premiumSubscription->getSubscriptionData()['cancel_at_period_end']
                ) {
                    break;
                }

                $subscriptionService->cancelSubscription($premiumSubscription, $cancelForm);
                $trialEndTime = $premiumSubscription->getSubscriptionData()['current_period_end'];
                $subscriptionForm->trialEnd = $trialEndTime;

                $subscriptionService->createSubscription($this->_user, $commerce->getPlans()->getPlanByHandle(self::PLAN_PRO), $subscriptionForm);
                break;

            case self::PLAN_PREMIUM:
                // No doubles
                if ($premiumSubscription) {
                    break;
                }

                // Pro must exist and be active or expiring
                if (!($proSubscription && in_array($proSubscription->getSubscriptionData()['status'], ['active', 'expiring'], true))) {
                    break;
                }

                $switchPlansForm = $gateway->getSwitchPlansFormModel();
                $switchPlansForm->prorate = true;
                $switchPlansForm->billingCycleAnchor = 'now';
                $switchResult = $subscriptionService->switchSubscriptionPlan($proSubscription, $commerce->getPlans()->getPlanByHandle(self::PLAN_PREMIUM), $switchPlansForm);

                // If this was an expiring pro subscription, reactivate it.
                if ($switchResult && $proSubscription->getSubscriptionData()['cancel_at_period_end'] == true) {
                    Commerce::getInstance()->getSubscriptions()->reactivateSubscription($proSubscription);
                }

                break;
        }

        return $this->asJson($this->_getSubscriptionData(true));
    }

    /**
     * Get the current subscription data
     *
     * @return array
     * @throws \yii\base\InvalidConfigException
     */
    private function _getSubscriptionData()
    {
        $user = Craft::$app->getUser()->getIdentity();
        $planService = Commerce::getInstance()->getPlans();
        $proPlan = $planService->getPlanByHandle(self::PLAN_PRO);
        $premiumPlan = $planService->getPlanByHandle(self::PLAN_PREMIUM);

        $planData = [
            self::PLAN_STANDARD => [
                'name' => self::PLAN_STANDARD_NAME,
                'cost' => [
                    'switch' => 0,
                    'recurring' => 0
                ],
            ],
            self::PLAN_PRO => [
                'name' => $proPlan->name,
                'cost' => [
                    'switch' => $proPlan->getPlanData()['plan']['amount'] / 100,
                    'recurring' => $proPlan->getPlanData()['plan']['amount'] / 100,
                ],
            ],
            self::PLAN_PREMIUM => [
                'name' => $premiumPlan->name,
                'cost' => [
                    'switch' => $premiumPlan->getPlanData()['plan']['amount'] / 100,
                    'recurring' => $premiumPlan->getPlanData()['plan']['amount'] / 100,
                ]
            ],
        ];

        $proSubscription = Subscription::find()->planId($proPlan->id)->userId($user->id)->isExpired(false)->one();
        $premiumSubscription = Subscription::find()->planId($premiumPlan->id)->userId($user->id)->isExpired(false)->one();

        $proData = $premiumData = [
            'status' => 'inactive'
        ];

        if ($proSubscription) {
            $proSubscriptionData = $proSubscription->getSubscriptionData();
            $proData['uid'] = $proSubscription->uid;

            if ($proSubscriptionData['status'] === 'trialing') {
                $proData['status'] = 'upcoming';
                $proData['startingDate'] = DateTimeHelper::toDateTime($proSubscriptionData['trial_end'])->format('Y-m-d');
            } else if ($proSubscriptionData['cancel_at_period_end']) {
                $proData['status'] = 'expiring';
                $proData['expiringDate'] = DateTimeHelper::toDateTime($proSubscriptionData['current_period_end'])->format('Y-m-d');
            } else if ($proSubscriptionData['status'] === 'active') {
                $proData['status'] = 'active';
                $proData['nextBillingDate'] = DateTimeHelper::toDateTime($proSubscriptionData['current_period_end'])->format('Y-m-d');
            }
        }

        if ($premiumSubscription) {
            $premiumSubscriptionData = $premiumSubscription->getSubscriptionData();
            $premiumData['uid'] = $premiumSubscription->uid;

            if ($premiumSubscriptionData['cancel_at_period_end']) {
               $premiumData['status'] = 'expiring';
               $premiumData['expiringDate'] = DateTimeHelper::toDateTime($premiumSubscriptionData['current_period_end'])->format('Y-m-d');
            } else if ($premiumSubscriptionData['status'] === 'active') {
               $premiumData['status'] = 'active';
               $premiumData['nextBillingDate'] = DateTimeHelper::toDateTime($premiumSubscriptionData['current_period_end'])->format('Y-m-d');
            }
        }

        $subscriptionData = [
            self::PLAN_PRO => $proData,
            self::PLAN_PREMIUM => $premiumData,
        ];

        if (in_array($subscriptionData[self::PLAN_PRO]['status'], ['active', 'expiring'], true) && $subscriptionData[self::PLAN_PREMIUM]['status'] === 'inactive') {
            $planData[self::PLAN_PREMIUM]['cost']['switch'] = $proSubscription->getGateway()->previewSwitchCost($proSubscription, $premiumPlan);
        }

        return [
            'plans' => $planData,
            'subscriptionData' => $subscriptionData,
        ];
    }
}
