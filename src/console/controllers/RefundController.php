<?php

namespace craftnet\console\controllers;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\models\LineItem;
use craft\commerce\models\Transaction;
use craft\commerce\Plugin as Commerce;
use craft\commerce\records\Transaction as TransactionRecord;
use craft\elements\User;
use craft\helpers\ArrayHelper;
use craftnet\behaviors\UserBehavior;
use craftnet\cms\CmsEdition;
use craftnet\cms\CmsLicense;
use craftnet\errors\InaccessibleFundsException;
use craftnet\errors\LicenseNotFoundException;
use craftnet\Module;
use craftnet\plugins\PluginEdition;
use craftnet\plugins\PluginLicense;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Refunds payments for Craft and plugin licenses, and deletes the licenses.
 *
 * @property Module $module
 */
class RefundController extends Controller
{
    /**
     * @inheritdoc
     */
    public $defaultAction = 'refund';

    /**
     * Refunds payments for Craft and plugin licenses, and deletes the licenses.
     *
     * @return int
     */
    public function actionRefund(): int
    {
        $orderNumber = $this->prompt('Order number:', [
            'required' => true,
            'validator' => function(string $input) {
                return Order::find()->number($input)->exists();
            }
        ]);
        $order = Order::find()->number($orderNumber)->one();

        // Get the transaction
        $transaction = ArrayHelper::firstWhere($order->getTransactions(), function(Transaction $transaction) {
            return (
                $transaction->type === TransactionRecord::TYPE_PURCHASE &&
                $transaction->status === TransactionRecord::STATUS_SUCCESS
            );
        });

        if ($transaction === null || !$transaction->canRefund()) {
            $this->stderr('This order can\'t be refunded.', Console::FG_RED);
            return 1;
        }

        // Get all the Craft/plugin editions purchased in this order
        /** @var LineItem[] $lineItems */
        $lineItems = [];
        /** @var CmsLicense[]|PluginLicense[] $lineItemLicenses */
        $lineItemLicenses = [];
        $lineItemDescriptions = [];
        $lineItemOptions = [];

        $formatter = Craft::$app->getFormatter();
        $cmsLicenseManager = $this->module->getCmsLicenseManager();
        $pluginLicenseManager = $this->module->getPluginLicenseManager();

        foreach ($order->getLineItems() as $i => $lineItem) {
            if (($total = $lineItem->getTotal()) <= 0 || !isset($lineItem->getOptions()['licenseKey'])) {
                continue;
            }

            $purchasable = $lineItem->getPurchasable();
            if (!$purchasable instanceof CmsEdition && !$purchasable instanceof PluginEdition) {
                continue;
            }

            $licenseKey = $lineItem->getOptions()['licenseKey'];
            if (strncmp($licenseKey, 'new:', 4) === 0) {
                $licenseKey = substr($licenseKey, 4);
            }

            try {
                if ($purchasable instanceof CmsEdition) {
                    $license = $cmsLicenseManager->getLicenseByKey($licenseKey);
                } else {
                    $license = $pluginLicenseManager->getLicenseByKey($licenseKey);
                }
            } catch (LicenseNotFoundException $e) {
                continue;
            }

            $key = (string)($i + 1);
            $lineItems[$key] = $lineItem;
            $lineItemLicenses[$key] = $license;
            $lineItemDescriptions[$key] = $lineItem->getDescription() . " ({$licenseKey})";
            $lineItemOptions[$key] = $lineItemDescriptions[$key] . ' - ' . $formatter->asCurrency($total, 'USD');
        }

        if (empty($lineItemOptions)) {
            $this->stderr('This order doesn\'t contain any Craft or plugin licenses.' . PHP_EOL, Console::FG_RED);
            return 1;
        }

        // Get the list of line items to return, and how much we owe/are owed
        $returnKeys = [];
        $refundAmount = 0;
        $refundNote = '';
        /** @var User[]|UserBehavior[] $developers */
        $developers = [];
        $devDebitAmounts = [];

        do {
            if (!empty($returnKeys) && !$this->confirm('Return any others?')) {
                break;
            }

            $key = $this->select('Which line item?', $lineItemOptions);
            unset($lineItemOptions[$key]);

            $returnKeys[] = $key;
            $lineItem = $lineItems[$key];
            $license = $lineItemLicenses[$key];

            $total = $lineItem->getTotal();
            $refundAmount += $total;
            $refundNote .= ($refundNote ? ', ' : '') . $lineItemDescriptions[$key];

            if ($license instanceof PluginLicense) {
                /** @var PluginEdition $purchasable */
                $purchasable = $lineItem->getPurchasable();
                $plugin = $purchasable->getPlugin();
                $developer = $developers[$plugin->developerId] ?? ($developers[$plugin->developerId] = $plugin->getDeveloper());

                if ($developer->username !== 'pixelandtonic') {
                    if (!isset($devDebitAmounts[$developer->id])) {
                        $devDebitAmounts[$developer->id] = 0;
                    }
                    $fee = floor($total * 20) / 100;
                    $devDebitAmounts[$developer->id] += ($total - $fee);
                }
            }
        } while (!empty($lineItemOptions));

        // Run the refund
        $this->stdout('Refunding ' . $formatter->asCurrency($refundAmount, 'USD') . ' to customer ... ', Console::FG_YELLOW);
        $child = Commerce::getInstance()->getPayments()->refundTransaction($transaction, $refundAmount, $refundNote);
        if ($child->status !== TransactionRecord::STATUS_SUCCESS) {
            $this->stderr('error: ' . $child->message . PHP_EOL, Console::FG_RED);
            return 1;
        }
        $this->stdout('done' . PHP_EOL, Console::FG_GREEN);

        // Debit the developers' accounts
        foreach ($devDebitAmounts as $developerId => $debitAmount) {
            $developer = $developers[$developerId];
            $this->stdout('Debiting ' . $formatter->asCurrency($debitAmount, 'USD') . " from {$developer->getDeveloperName()}'s account ... ", Console::FG_YELLOW);
            do {
                try {
                    $developer->getFundsManager()->debit("Payment refunded for order {$orderNumber} (txn {$child->id})", $debitAmount);
                    break;
                } catch (InaccessibleFundsException $e) {
                    $this->stdout(PHP_EOL . $e->getMessage() . PHP_EOL, Console::FG_RED);
                    if (!$this->confirm('Retry?', true)) {
                        break;
                    }
                }
            } while (true);
            $this->stdout('done' . PHP_EOL, Console::FG_GREEN);
        }

        // Delete the licenses
        if ($this->confirm('Delete the licenses?', true)) {
            foreach ($returnKeys as $key) {
                $license = $lineItemLicenses[$key];
                $this->stdout("Deleting license {$license->getShortKey()} ... ", Console::FG_YELLOW);
                if ($license instanceof CmsLicense) {
                    $cmsLicenseManager->deleteLicenseByKey($license->key);
                } else {
                    $pluginLicenseManager->deleteLicenseByKey($license->key);
                }
                $this->stdout('done' . PHP_EOL, Console::FG_GREEN);
            }
        } else {
            foreach ($returnKeys as $key) {
                $license = $lineItemLicenses[$key];
                $note = $this->prompt("Note for {$license->getShortKey()}: ", [
                    'required' => true,
                    'default' => 'Refunded',
                ]);
                if ($license instanceof CmsLicense) {
                    $cmsLicenseManager->addHistory($license->id, $note);
                } else {
                    $pluginLicenseManager->addHistory($license->id, $note);
                }
            }
        }

        $this->stdout('All done.' . PHP_EOL . PHP_EOL, Console::FG_GREEN);
        return ExitCode::OK;
    }
}
