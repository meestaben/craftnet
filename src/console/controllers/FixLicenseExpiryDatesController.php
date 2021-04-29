<?php

namespace craftnet\console\controllers;

use Craft;
use craft\commerce\helpers\LineItem as LineItemHelper;
use craft\helpers\ArrayHelper;
use craft\helpers\DateTimeHelper;
use craft\helpers\Json;
use craftnet\base\LicenseInterface;
use craftnet\cms\CmsLicense;
use craftnet\db\Table;
use craftnet\helpers\OrderHelper;
use craftnet\Module;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;
use yii\helpers\Markdown;

/**
 * Fixed license expiry dates based on what was actually paid
 *
 * @property Module $module
 */
class FixLicenseExpiryDatesController extends Controller
{
    public $dryRun = false;

    public function options($actionID)
    {
        $options = parent::options($actionID);
        $options[] = 'dryRun';
        return $options;
    }

    /**
     * Regenerates Composer repository JSON files.
     */
    public function actionIndex()
    {
        $db = Craft::$app->getDb();
        $formatter = Craft::$app->getFormatter();
        $cmsLicenseManager = $this->module->getCmsLicenseManager();
        $pluginLicenseManager = $this->module->getPluginLicenseManager();
        $mailer = Craft::$app->getMailer();

        $badLineItemSql = <<<SQL
select
       li.id,
       cms_l_li."licenseId" as "cmsLicenseId",
       plugins_l_li."licenseId" as "pluginLicenseId",
       li."orderId",
       li."purchasableId",
       li.options,
       li.price,
       li."saleAmount",
       li."salePrice",
       li.sku,
       li.description,
       o."customerId",
       o.number,
       o.email,
       o."datePaid"
from commerce_lineitems li
inner join commerce_orders o on li."orderId" = o.id
left join craftnet_cmslicenses_lineitems cms_l_li on cms_l_li."lineItemId" = li.id
left join craftnet_pluginlicenses_lineitems plugins_l_li on plugins_l_li."lineItemId" = li.id 
where o."orderStatusId" = 3
  and cast(li."salePrice" as int) <> cast(li.price as int)
  and li."dateCreated" >= '2020-06-10 00:00:00'
  and o."datePaid" is not null
SQL;

        $badLineItems = $db->createCommand($badLineItemSql)->queryAll();
        $badOrders = ArrayHelper::index($badLineItems, null, ['orderId']);

        $this->stdout('Found ' . count($badOrders) . ' orders with incorrect line item sale prices.' . PHP_EOL . PHP_EOL);

        foreach ($badOrders as $orderId => $lineItems) {
            $transaction = $db->beginTransaction();
            try {
                $firstLineItem = reset($lineItems);
                $this->stdout("Processing order {$firstLineItem['number']}" . PHP_EOL . PHP_EOL);
                $emailItems = [];

                foreach ($lineItems as $lineItem) {
                    if (!isset($lineItem['cmsLicenseId']) && !isset($lineItem['pluginLicenseId'])) {
                        $this->stderr("No associated Craft/plugin license for line item {$lineItem['id']}." . PHP_EOL . PHP_EOL, Console::FG_RED);
                        continue;
                    }

                    $options = Json::decode($lineItem['options']);

                    $this->stdout("    - Line item {$lineItem['id']} ({$lineItem['sku']}):" . PHP_EOL);
                    $this->stdout('        - Expected price: ' . $formatter->asCurrency($lineItem['price']) . PHP_EOL);
                    $this->stdout('        - Paid: ' . $formatter->asCurrency($lineItem['salePrice']) . PHP_EOL);

                    // See if we can determine the previous expiry date for this license
                    /** @var LicenseInterface $license */
                    if ($lineItem['cmsLicenseId']) {
                        $joinTable = Table::CMSLICENSES_LINEITEMS;
                        $licenseId = $lineItem['cmsLicenseId'];
                        $license = $cmsLicenseManager->getLicenseById($licenseId);
                        $itemName = 'Craft Pro';
                    } else {
                        $joinTable = Table::PLUGINLICENSES_LINEITEMS;
                        $licenseId = $lineItem['pluginLicenseId'];
                        $license = $pluginLicenseManager->getLicenseById($licenseId);
                        $itemName = $license->getPlugin()->name;
                    }

                    $prevLineItemSql = <<<SQL
select li.options, o."datePaid"
from commerce_lineitems li
inner join commerce_orders o on li."orderId" = o.id
inner join $joinTable l_li on l_li."lineItemId" = li.id
where l_li."licenseId" = $licenseId
  and o."orderStatusId" = 3
  and li."orderId" < {$lineItem['orderId']}
order by li."orderId" desc
limit 1
SQL;

                    $prevLineItem = $db->createCommand($prevLineItemSql)->queryOne();
                    if (!$prevLineItem) {
                        $this->stderr("No previous orders exist for purchasable {$lineItem['id']}." . PHP_EOL . PHP_EOL, Console::FG_RED);
                        $this->stdout($prevLineItemSql . PHP_EOL . PHP_EOL);
                        continue;
                    }

                    $prevLineItemOptions = Json::decode($prevLineItem['options']);
                    $prevLineItemDate = DateTimeHelper::toDateTime($prevLineItem['datePaid']);
                    $prevExpiryDate = OrderHelper::expiryStr2Obj($prevLineItemOptions['expiryDate'] ?? '1y', $prevLineItemDate);
                    $lineItemDate = DateTimeHelper::toDateTime($lineItem['datePaid']);

                    if ($lineItemDate === false) {
                        $this->stdout("Invalid date: {$lineItem['datePaid']}" . PHP_EOL . PHP_EOL, Console::FG_RED);
                        continue;
                    }

                    $requestedExpiryDate = OrderHelper::expiryStr2Obj($options['expiryDate'], $lineItemDate);

                    // Make sure the license actually has that expiry date
                    $oldExpiryDate = $license->getExpiryDate();
                    if ($requestedExpiryDate->format('Y-m-d') !== $oldExpiryDate->format('Y-m-d')) {
                        $this->stdout('Unexpected expiry date: ' . $oldExpiryDate->format('Y-m-d') . PHP_EOL . PHP_EOL, Console::FG_RED);
                        continue;
                    }

                    if ($prevExpiryDate < $lineItemDate) {
                        $newExpiryDate = (clone $lineItemDate)->modify('+1 year');
                    } else {
                        $newExpiryDate = (clone $prevExpiryDate)->modify('+1 year');
                    }

                    $this->stdout('        - Previous expiry date: ' . $prevExpiryDate->format('Y-m-d') . PHP_EOL);
                    $this->stdout('        - Requested expiry date: ' . $requestedExpiryDate->format('Y-m-d') . PHP_EOL);

                    if ($requestedExpiryDate->format('Y-m-d') === $newExpiryDate->format('Y-m-d')) {
                        $this->stdout('        ✔ No action needed' . PHP_EOL . PHP_EOL, Console::FG_GREEN);
                        continue;
                    }

                    $this->stdout('        - Corrected expiry date: ' . $newExpiryDate->format('Y-m-d') . PHP_EOL);
                    $emailItems[] = "- $itemName (`" . $license->getShortKey() . '`) – adjusted from ' . $oldExpiryDate->format('Y-m-d') .
                        ' to ' . $newExpiryDate->format('Y-m-d');

                    // Update the license
                    $license->expiresOn = $newExpiryDate;
                    if (!$this->dryRun) {
                        $this->stdout('        - Updating the license ... ');
                        if ($license instanceof CmsLicense) {
                            $cmsLicenseManager->saveLicense($license);
                        } else {
                            $pluginLicenseManager->saveLicense($license);
                        }
                        $this->stdout('done' . PHP_EOL, Console::FG_GREEN);
                    }

                    // Update the line item
                    if (!$this->dryRun) {
                        $this->stdout('        - Updating the line item ... ');
                        $options['originalExpiryDate'] = $options['expiryDate'];
                        $options['originalPrice'] = $lineItem['price'];
                        $options['originalSaleAmount'] = $lineItem['saleAmount'];
                        $options['expiryDate'] = $newExpiryDate->format('Y-m-d');
                        $db->createCommand()->update('commerce_lineitems', [
                            'options' => Json::encode($options),
                            'optionsSignature' => LineItemHelper::generateOptionsSignature($options),
                            'price' => $lineItem['salePrice'],
                            'saleAmount' => 0,
                        ], [
                            'id' => $lineItem['id'],
                        ])->execute();
                        $this->stdout('done' . PHP_EOL, Console::FG_GREEN);
                    }

                    // Add a note to the license history
                    if (!$this->dryRun) {
                        $this->stdout('        - Adding note to license history ... ');
                        $note = 'Adjusted expiry date from ' . $oldExpiryDate->format('Y-m-d') . ' to ' . $newExpiryDate->format('Y-m-d') . ' due to billing bug';
                        if ($license instanceof CmsLicense) {
                            $cmsLicenseManager->addHistory($license->id, $note);
                        } else {
                            $pluginLicenseManager->addHistory($license->id, $note);
                        }
                        $this->stdout('done' . PHP_EOL, Console::FG_GREEN);
                    }

                    $this->stdout(PHP_EOL);
                }

                $transaction->commit();
            } catch (\Throwable $e) {
                $transaction->rollBack();
            }

            if ($this->dryRun || empty($emailItems)) {
                continue;
            }

            $emailItems = implode("\n", $emailItems);
            $email = <<<MD
Hi there,

We recently discovered a bug which affected renewals purchased between June 10 and June 26, 2020.
All renewals during this period were changed the single-year renewal fee, regardless of the actual
selected renewal duration.

To compensate, we have adjusted the expiry dates for the following licenses:

$emailItems

No action is required on your part. Sorry for any inconvenience this may have caused :(

MD;

            $this->stdout("Sending email to {$firstLineItem['email']} ... ");
            $mailer->compose()
                ->setTo($firstLineItem['email'])
                ->setSubject('Important license info')
                ->setTextBody($email)
                ->setHtmlBody(Markdown::process($email))
                ->send();
            $this->stdout('done' . PHP_EOL . PHP_EOL, Console::FG_GREEN);
        }

        return ExitCode::OK;
    }
}
