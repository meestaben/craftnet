<?php

namespace craftnet\console\controllers;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\models\PaymentSource;
use craft\commerce\Plugin as Commerce;
use craft\commerce\stripe\gateways\PaymentIntents as StripeGateway;
use craft\commerce\stripe\models\forms\payment\PaymentIntent as PaymentForm;
use craft\elements\User;
use craft\helpers\ArrayHelper;
use craftnet\base\LicenseInterface;
use craftnet\Module;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Handles tasks that apply to both Craft and plugin licenses.
 *
 * @property Module $module
 */
class LicensesController extends Controller
{
    /**
     * Sends reminders to people whose Craft/plugin license(s) will be expiring in the next 14-30 days.
     *
     * @return int
     */
    public function actionSendReminders(): int
    {
        $cmsLicenseManager = $this->module->getCmsLicenseManager();
        $pluginLicenseManager = $this->module->getPluginLicenseManager();

        // Find licenses that need reminders
        $this->stdout('Finding licenses that are due for reminders ... ', Console::FG_YELLOW);
        $licenses = array_merge(
            $cmsLicenseManager->getRemindableLicenses(),
            $pluginLicenseManager->getRemindableLicenses()
        );
        $this->stdout('done (' . count($licenses) . ' licenses found)' . PHP_EOL, Console::FG_YELLOW);

        if (empty($licenses)) {
            $this->stdout('Nothing to send.' . PHP_EOL . PHP_EOL, Console::FG_GREEN);
            return ExitCode::OK;
        }

        $this->stdout('Sending reminders ...' . PHP_EOL, Console::FG_YELLOW);

        // Group by owner (or email if unclaimed)
        $licenses = ArrayHelper::index($licenses, null, function(LicenseInterface $license) {
            $ownerId = $license->getOwnerId();
            return $ownerId ? "owner-{$ownerId}" : mb_strtolower($license->getEmail());
        });

        $mailer = Craft::$app->getMailer();

        foreach ($licenses as $ownerKey => $ownerLicenses) {
            try {
                /** @var string $email */
                /** @var User|null $user */
                [$email, $user] = $this->_resolveOwnerKey($ownerKey);

                // Lock in the renewal prices
                /** @var LicenseInterface[] $ownerLicenses */
                foreach ($ownerLicenses as $license) {
                    if ($license->getWillAutoRenew()) {
                        $newRenewalPrice = $license->getEdition()->getRenewal()->getPrice();
                        if ($license->getRenewalPrice() !== $newRenewalPrice) {
                            $license->setRenewalPrice($newRenewalPrice);
                        }
                    }
                }

                $ownerLicensesByType = ArrayHelper::index($ownerLicenses, null, function(LicenseInterface $license) {
                    return $license->getWillAutoRenew() ? 'auto' : 'manual';
                });

                $this->stdout("    - Emailing {$email} about " . count($ownerLicenses) . ' licenses ... ', Console::FG_YELLOW);

                $message = $mailer
                    ->composeFromKey(Module::MESSAGE_KEY_LICENSE_REMINDER, ['licenses' => $ownerLicensesByType])
                    ->setTo($user ?? $email);

                if (!$message->send()) {
                    $this->stderr('error sending email' . PHP_EOL, Console::FG_RED);
                    continue;
                }

                $this->stdout('done' . PHP_EOL, Console::FG_GREEN);

                // Mark the licenses as reminded so we don't send this again for them until the next cycle
                foreach ($ownerLicenses as $license) {
                    $license->markAsReminded();
                }
            } catch (\Throwable $e) {
                // Don't let this stop us from sending other reminders
                $this->stdout('An error occurred: ' . $e->getMessage() . PHP_EOL, Console::FG_RED);
                Craft::$app->getErrorHandler()->logException($e);
            }
        }

        $this->stdout('Done sending reminders.' . PHP_EOL . PHP_EOL, Console::FG_GREEN);
        return ExitCode::OK;
    }

    /**
     * Auto-renews or expires licenses that are due for it.
     *
     * @return int
     */
    public function actionProcessExpiredLicenses(): int
    {
        $cmsLicenseManager = $this->module->getCmsLicenseManager();
        $pluginLicenseManager = $this->module->getPluginLicenseManager();

        // Find freshly-expired licenses
        $this->stdout('Finding freshly-expired licenses ... ', Console::FG_YELLOW);
        $licenses = array_merge(
            $cmsLicenseManager->getFreshlyExpiredLicenses(),
            $pluginLicenseManager->getFreshlyExpiredLicenses()
        );
        $this->stdout('done (' . count($licenses) . ' licenses found)' . PHP_EOL, Console::FG_YELLOW);

        if (empty($licenses)) {
            $this->stdout('No licenses have expired.' . PHP_EOL . PHP_EOL, Console::FG_GREEN);
            return ExitCode::OK;
        }

        $this->stdout('Processing licenses ...' . PHP_EOL, Console::FG_YELLOW);

        // Group by owner (or email if unclaimed)
        $licenses = ArrayHelper::index($licenses, null, function(LicenseInterface $license) {
            $ownerId = $license->getOwnerId();
            return $ownerId ? "owner-{$ownerId}" : mb_strtolower($license->getEmail());
        });

        $mailer = Craft::$app->getMailer();

        foreach ($licenses as $ownerKey => $ownerLicenses) {
            try {
                /** @var string $email */
                /** @var User|null $user */
                [$email, $user] = $this->_resolveOwnerKey($ownerKey);

                /** @var LicenseInterface[] $renewLicenses */
                /** @var LicenseInterface[] $expireLicenses */
                [$renewLicenses, $expireLicenses] = $this->_findRenewableLicenses($ownerLicenses, $user);

                // If there are any licenses that should be auto-renewed, give that a shot
                if (!empty($renewLicenses)) {
                    if ($autoRenewFailed = !$this->_autoRenewLicenses($renewLicenses, $user)) {
                        $expireLicenses = array_merge($renewLicenses, $expireLicenses);
                    }
                } else {
                    $autoRenewFailed = false;
                }

                // Expire the licenses
                $this->stdout('    - Expiring ' . count($expireLicenses) . " licenses for {$email} ... ", Console::FG_YELLOW);
                foreach ($expireLicenses as $license) {
                    $license->markAsExpired();
                }
                $this->stdout('done' . PHP_EOL, Console::FG_GREEN);

                // Send a notification email
                $this->stdout("    - Emailing {$email} about " . count($ownerLicenses) . ' licenses ... ', Console::FG_YELLOW);

                $message = $mailer
                    ->composeFromKey(Module::MESSAGE_KEY_LICENSE_NOTIFICATION, [
                        'renewedLicenses' => $autoRenewFailed ? [] : $renewLicenses,
                        'expiredLicenses' => $expireLicenses,
                        'autoRenewFailed' => $autoRenewFailed,
                    ])
                    ->setTo($user ?? $email);

                if ($message->send()) {
                    $this->stdout('done' . PHP_EOL, Console::FG_GREEN);
                } else {
                    $this->stderr('error sending email' . PHP_EOL, Console::FG_RED);
                }
            } catch (\Throwable $e) {
                // Don't let this stop us from processing other licenses
                $this->stdout('An error occurred: ' . $e->getMessage() . PHP_EOL, Console::FG_RED);
                Craft::$app->getErrorHandler()->logException($e);
            }
        }

        $this->stdout('Done processing licenses.' . PHP_EOL . PHP_EOL, Console::FG_GREEN);
        return ExitCode::OK;
    }

    /**
     * Returns the email and user account (if one exists) for the given license owner key.
     *
     * @param string $ownerKey
     * @return array
     */
    private function _resolveOwnerKey(string $ownerKey): array
    {
        if (preg_match('/^owner-(\d+)$/', $ownerKey, $matches)) {
            $user = User::find()->id((int)$matches[1])->anyStatus()->one();
            $email = $user->email;
        } else {
            $email = $ownerKey;
            $user = User::find()->email($email)->anyStatus()->one();
        }

        return [$email, $user];
    }

    /**
     * Splits a list of licenses into an array of licenses that should be auto-renewed and an array of licenses
     * that should expire.
     *
     * @param LicenseInterface[] $licenses
     * @param User|null $user
     * @return array
     */
    private function _findRenewableLicenses(array $licenses, User $user = null): array
    {
        // If no Craft ID, then there's nothing to auto-renew
        if ($user === null) {
            return [[], $licenses];
        }

        $utc = new \DateTimeZone('UTC');
        $today = new \DateTime('midnight', $utc);

        $licensesByType = ArrayHelper::index($licenses, null, function(LicenseInterface $license) use ($utc, $today) {
            if ($license->getWillAutoRenew() && $license->getWasReminded() && $license->getRenewalPrice()) {
                // Only auto-renew if it just expired yesterday
                $expiryDate = $license->getExpiryDate();
                $expiryDate->setTimezone($utc);
                if ($expiryDate >= $today) {
                    return 'renew';
                }
            }
            return 'expire';
        });

        return [
            $licensesByType['renew'] ?? [],
            $licensesByType['expire'] ?? [],
        ];
    }

    /**
     * Attempts to auto-renew some licenses.
     *
     * @param LicenseInterface[] $licenses
     * @param User $user
     * @return bool Whether it was successful
     */
    private function _autoRenewLicenses(array $licenses, User $user): bool
    {
        try {
            // Make sure they have a Commerce customer record
            $commerce = Commerce::getInstance();
            $customer = $commerce->getCustomers()->getCustomerByUserId($user->id);
            if ($customer === null || !$customer->primaryBillingAddressId) {
                return false;
            }

            // Make sure they have a payment source
            /** @var PaymentSource|null $paymentSource */
            $paymentSource = ArrayHelper::firstValue($commerce->getPaymentSources()->getAllGatewayPaymentSourcesByUserId(getenv('STRIPE_GATEWAY_ID'), $user->id));
            if ($paymentSource === null) {
                return false;
            }

            $this->stdout('    - Creating order for ' . count($licenses) . " licenses for {$user->email} ... ", Console::FG_YELLOW);

            $order = new Order([
                'number' => $commerce->getCarts()->generateCartNumber(),
                'currency' => 'USD',
                'paymentCurrency' => 'USD',
                'gatewayId' => getenv('STRIPE_GATEWAY_ID'),
                'orderLanguage' => Craft::$app->language,
                'customerId' => $customer->id,
                'email' => $user->email,
            ]);

            // Save the cart so it gets an ID
            if (!Craft::$app->getElements()->saveElement($order)) {
                throw new \Exception('Could not save the cart: ' . implode(', ', $order->getErrorSummary(true)));
            }

            // Add the line items to the cart
            $lineItemsService = $commerce->getLineItems();
            foreach ($licenses as $license) {
                $renewalId = $license->getEdition()->getRenewal()->getId();
                $lineItem = $lineItemsService->resolveLineItem($order->id, $renewalId, [
                    'licenseKey' => $license->getKey(),
                    'lockedPrice' => $license->getRenewalPrice(),
                ]);
                $lineItem->qty = 1;
                $order->addLineItem($lineItem);
            }

            // Recalculate the order
            $order->recalculate();

            // Pay for it
            /** @var StripeGateway $gateway */
            $gateway = $commerce->getGateways()->getGatewayById(getenv('STRIPE_GATEWAY_ID'));
            /** @var PaymentForm $paymentForm */
            $paymentForm = $gateway->getPaymentFormModel();
            $paymentForm->populateFromPaymentSource($paymentSource);
            $commerce->getPayments()->processPayment($order, $paymentForm, $redirect, $transaction);
        } catch (\Throwable $e) {
            $this->stderr(PHP_EOL . 'error: ' . $e->getMessage() . PHP_EOL, Console::FG_RED);
            Craft::$app->getErrorHandler()->logException($e);
            return false;
        }

        $this->stdout('done' . PHP_EOL, Console::FG_GREEN);
        return true;
    }
}
