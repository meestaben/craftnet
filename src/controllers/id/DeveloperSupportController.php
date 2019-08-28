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
use craft\web\Controller;
use yii\helpers\Json;
use yii\web\Response;

/**
 * Class DeveloperSupportController
 */
class DeveloperSupportController extends Controller
{
    const PLAN_STANDARD = 'basic';
    const PLAN_PRO = 'pro';
    const PLAN_PREMIUM = 'premium';

    // Public Methods
    // =========================================================================

    /**
     * This action returns the developer support subscription information for the current user.
     *
     * @return Response
     */
    public function actionGetSubscriptionInfo(): Response
    {
        return $this->asJson($this->_getSubscriptionData(true));
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
        $commerce = Commerce::getInstance();
        $requestedPlan = Craft::$app->getRequest()->getRequiredBodyParam('newPlan');

        $subscriptionData = $this->_getSubscriptionData();
        $subscriptionService = $commerce->getSubscriptions();
        /** @var PaymentIntents $gateway */
        $gateway = $commerce->getGateways()->getGatewayById(getenv('STRIPE_GATEWAY_ID'));

        $premiumSubscription = null;
        $proSubscription = null;

        if (!empty($subscriptionData[self::PLAN_PREMIUM])) {
            /** @var Subscription $premiumSubscription */
            $premiumSubscription = Subscription::find()->uid($subscriptionData[self::PLAN_PREMIUM]['uid'])->one();
        }

        if (!empty($subscriptionData[self::PLAN_PRO])) {
            /** @var Subscription $proSubscription */
            $proSubscription = Subscription::find()->uid($subscriptionData[self::PLAN_PRO]['uid'])->one();
        }

        /** @var CancelSubscription $cancelForm */
        $cancelForm = $gateway->getCancelSubscriptionFormModel();
        $cancelForm->cancelImmediately = false;

        switch ($requestedPlan) {
            case self::PLAN_STANDARD:
                // Cancel existing subscriptions, if any
                if ($premiumSubscription) {
                    $subscriptionService->cancelSubscription($premiumSubscription, $cancelForm);
                }

                if ($proSubscription) {
                    $subscriptionService->cancelSubscription($proSubscription, $cancelForm);
                }

                break;
            case self::PLAN_PRO:
                // No duplicates
                if ($proSubscription) {
                    Craft::warning('Tried to subscribe to pro while on it already. (' . Json::encode($subscriptionData) . ')', 'developerSupport');
                    return $this->asErrorJson('You are already subscribed to that plan.');
                }

                /** @var SubscriptionForm $subscriptionForm */
                $subscriptionForm = $gateway->getSubscriptionFormModel();

                // If downgrading, set trial to end as priority expires
                if ($premiumSubscription) {
                    $subscriptionService->cancelSubscription($premiumSubscription, $cancelForm);
                    $trialEndTime = $premiumSubscription->getSubscriptionData()['current_period_end'];
                    $subscriptionForm->trialEnd = $trialEndTime;
                }

                $subscription = $subscriptionService->createSubscription(Craft::$app->getUser()->getIdentity(), $commerce->getPlans()->getPlanByHandle(self::PLAN_PRO), $subscriptionForm);

                break;
            case self::PLAN_PREMIUM:
                // No duplicates
                if ($premiumSubscription) {
                    Craft::warning('Tried to subscribe to premium while on it already. (' . Json::encode($subscriptionData) . ')', 'developerSupport');
                    return $this->asErrorJson('You are already subscribed to that plan.');
                }

                // If upgrading, reset the billing anchor and prorate
                if ($proSubscription) {
                    /** @var SwitchPlans $switchPlansForm */
                    $switchPlansForm = $gateway->getSwitchPlansFormModel();
                    $switchPlansForm->prorate = true;
                    $switchPlansForm->billingCycleAnchor = 'now';
                    $subscriptionService->switchSubscriptionPlan($proSubscription, $commerce->getPlans()->getPlanByHandle(self::PLAN_PREMIUM), $switchPlansForm);
                    $subscription = $proSubscription;
                } else {
                    /** @var SubscriptionForm $subscriptionForm */
                    $subscriptionForm = $gateway->getSubscriptionFormModel();
                    $subscription = $subscriptionService->createSubscription(Craft::$app->getUser()->getIdentity(), $commerce->getPlans()->getPlanByHandle(self::PLAN_PRO), $subscriptionForm);
                }

                break;
        }

        return $this->asJson($this->_getSubscriptionData(true));
    }

    /**
     * Get the current subscription data
     * @param bool $fetchUpgradeCost if true will calculate upgrade cost from premium to priority (if appropriate)
     * @return array
     * @throws \yii\base\InvalidConfigException
     */
    private function _getSubscriptionData(bool $fetchUpgradeCost = false)
    {
        $user = Craft::$app->getUser()->getIdentity();

        $subscriptions = Subscription::find()->userId($user->id)->isExpired(false)->all();

        $data = [
            'currentPlan' => self::PLAN_STANDARD
        ];

        foreach ($subscriptions as $subscription) {
            /** @var Plan $plan */
            $plan = $subscription->getPlan();
            $subscriptionUntil = $subscription->nextPaymentDate->format('Y-m-d');

            switch ($plan->handle) {
                case self::PLAN_PREMIUM:
                    $data['currentPlan'] = self::PLAN_PREMIUM;
                    $data[self::PLAN_PREMIUM] = [
                        'uid' => $subscription->uid,
                        'canceled' => $subscription->isCanceled,
                        'cycleEnd' => $subscriptionUntil
                    ];

                    break;
                case self::PLAN_PRO:
                    $data['currentPlan'] = $data['currentPlan'] == self::PLAN_STANDARD ? self::PLAN_PRO : $data['currentPlan'];
                    $data[self::PLAN_PRO] = [
                        'uid' => $subscription->uid,
                        'canceled' => $subscription->isCanceled,
                        'cycleEnd' => $subscriptionUntil
                    ];

                    if ($data['currentPlan'] == self::PLAN_PRO && $fetchUpgradeCost) {
                        /** @var SubscriptionGateway $gateway */
                        $gateway = $subscription->getGateway();
                        $premiumPlan = Commerce::getInstance()->getPlans()->getPlanByHandle(self::PLAN_PREMIUM);

                        if (!$premiumPlan) {
                            continue 2;
                        }

                        if ($subscription->getSubscriptionData()['status'] !== 'trialing') {
                            $data[self::PLAN_PREMIUM]['upgradeCost'] = $gateway->previewSwitchCost($subscription, $premiumPlan);
                        }
                    }

                    break;
            }
        }

        return $data;
    }
}
