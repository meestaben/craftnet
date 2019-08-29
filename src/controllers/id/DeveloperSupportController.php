<?php

namespace craftnet\controllers\id;

use Craft;
use craft\commerce\base\Plan;
use craft\commerce\elements\Subscription;
use craft\commerce\Plugin as Commerce;
use craft\commerce\stripe\base\SubscriptionGateway;
use craft\commerce\stripe\gateways\PaymentIntents;
use craft\commerce\stripe\models\forms\CancelSubscription;
use craft\commerce\stripe\models\forms\Subscription as SubscriptionForm;
use craft\commerce\stripe\models\forms\SwitchPlans;
use craft\helpers\DateTimeHelper;
use craft\web\Controller;
use yii\helpers\Json;
use yii\web\Response;

/**
 * Class DeveloperSupportController
 */
class DeveloperSupportController extends Controller
{
    const PLAN_STANDARD = 'basic';
    const PLAN_STANDARD_NAME = 'Basic';
    const PLAN_PRO = 'pro';
    const PLAN_PREMIUM = 'premium';

    // Public Methods
    // =========================================================================

    public function beforeAction($action)
    {
        $this->requireLogin();

        return parent::beforeAction($action);
    }

    /**
     * This action returns the developer support subscription information for the current user.
     *
     * @return Response
     */
    public function actionGetSubscriptionInfo(): Response
    {
        return $this->asJson($this->_getSubscriptionData(true));
    }

    public function actionCancelPlan(): Response
    {

    }

    public function actionReactivatePlan(): Response
    {

    }

    public function actionSubscribe(): Response
    {

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
//        $commerce = Commerce::getInstance();
//        $requestedPlan = Craft::$app->getRequest()->getRequiredBodyParam('newPlan');
//
//        $subscriptionData = $this->_getSubscriptionData();
//        $subscriptionService = $commerce->getSubscriptions();
//        /** @var PaymentIntents $gateway */
//        $gateway = $commerce->getGateways()->getGatewayById(getenv('STRIPE_GATEWAY_ID'));
//
//        $premiumSubscription = null;
//        $proSubscription = null;
//
//        if (!empty($subscriptionData[self::PLAN_PREMIUM])) {
//            /** @var Subscription $premiumSubscription */
//            $premiumSubscription = Subscription::find()->uid($subscriptionData[self::PLAN_PREMIUM]['uid'])->one();
//        }
//
//        if (!empty($subscriptionData[self::PLAN_PRO])) {
//            /** @var Subscription $proSubscription */
//            $proSubscription = Subscription::find()->uid($subscriptionData[self::PLAN_PRO]['uid'])->one();
//        }
//
//        /** @var CancelSubscription $cancelForm */
//        $cancelForm = $gateway->getCancelSubscriptionFormModel();
//        $cancelForm->cancelImmediately = false;
//
//        switch ($requestedPlan) {
//            case self::PLAN_STANDARD:
//                // Cancel existing subscriptions, if any
//                if ($premiumSubscription) {
//                    $subscriptionService->cancelSubscription($premiumSubscription, $cancelForm);
//                }
//
//                if ($proSubscription) {
//                    $subscriptionService->cancelSubscription($proSubscription, $cancelForm);
//                }
//
//                break;
//            case self::PLAN_PRO:
//                // No duplicates
//                if ($proSubscription) {
//                    Craft::warning('Tried to subscribe to pro while on it already. (' . Json::encode($subscriptionData) . ')', 'developerSupport');
//                    return $this->asErrorJson('You are already subscribed to that plan.');
//                }
//
//                /** @var SubscriptionForm $subscriptionForm */
//                $subscriptionForm = $gateway->getSubscriptionFormModel();
//
//                // If downgrading, set trial to end as priority expires
//                if ($premiumSubscription) {
//                    $subscriptionService->cancelSubscription($premiumSubscription, $cancelForm);
//                    $trialEndTime = $premiumSubscription->getSubscriptionData()['current_period_end'];
//                    $subscriptionForm->trialEnd = $trialEndTime;
//                }
//
//                $subscription = $subscriptionService->createSubscription(Craft::$app->getUser()->getIdentity(), $commerce->getPlans()->getPlanByHandle(self::PLAN_PRO), $subscriptionForm);
//
//                break;
//            case self::PLAN_PREMIUM:
//                // No duplicates
//                if ($premiumSubscription) {
//                    Craft::warning('Tried to subscribe to premium while on it already. (' . Json::encode($subscriptionData) . ')', 'developerSupport');
//                    return $this->asErrorJson('You are already subscribed to that plan.');
//                }
//
//                // If upgrading, reset the billing anchor and prorate
//                if ($proSubscription) {
//                    /** @var SwitchPlans $switchPlansForm */
//                    $switchPlansForm = $gateway->getSwitchPlansFormModel();
//                    $switchPlansForm->prorate = true;
//                    $switchPlansForm->billingCycleAnchor = 'now';
//                    $subscriptionService->switchSubscriptionPlan($proSubscription, $commerce->getPlans()->getPlanByHandle(self::PLAN_PREMIUM), $switchPlansForm);
//                    $subscription = $proSubscription;
//                } else {
//                    /** @var SubscriptionForm $subscriptionForm */
//                    $subscriptionForm = $gateway->getSubscriptionFormModel();
//                    $subscription = $subscriptionService->createSubscription(Craft::$app->getUser()->getIdentity(), $commerce->getPlans()->getPlanByHandle(self::PLAN_PRO), $subscriptionForm);
//                }
//
//                break;
//        }
//
//        return $this->asJson($this->_getSubscriptionData(true));
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

        if ($subscriptionData[self::PLAN_PRO]['status'] === 'active' && $subscriptionData[self::PLAN_PREMIUM]['status'] === 'inactive') {
            $planData[self::PLAN_PREMIUM]['cost']['switch'] = $proSubscription->getGateway()->previewSwitchCost($proSubscription, $premiumPlan);
        }

        return [
            'plans' => $planData,
            'subscriptionData' => $subscriptionData,
        ];
    }
}
