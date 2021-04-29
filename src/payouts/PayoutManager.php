<?php

namespace craftnet\payouts;

use Craft;
use craft\elements\User;
use craft\helpers\App;
use craft\helpers\Db;
use craftnet\behaviors\UserBehavior;
use craftnet\db\Table;
use craftnet\developers\FundsManager;
use craftnet\errors\InaccessibleFundsException;
use PayPal\Api\Currency;
use PayPal\Api\Payout as ApiPayout;
use PayPal\Api\PayoutItem as ApiPayoutItem;
use PayPal\Api\PayoutSenderBatchHeader;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Exception\PayPalConnectionException;
use PayPal\Rest\ApiContext;
use UnexpectedValueException;
use yii\base\BaseObject;
use yii\helpers\ArrayHelper;

class PayoutManager extends BaseObject
{
    /**
     * Sends payouts to any developers we owe $ to, who have provided their PayPal email.
     *
     * @return Payout|null
     * @throws PayPalConnectionException
     */
    public function sendPayout(): ?Payout
    {
        // Find the accounts we owe money to
        /** @var User[]|UserBehavior[] $users */
        $users = User::find()
            ->andWhere(['>', 'balance', 0])
            ->andWhere(['not', ['payPalEmail' => null]])
            ->all();

        if (empty($users)) {
            return null;
        }

        $amounts = [];

        // Create a new batch record
        $payoutRecord = new Payout();
        $payoutRecord->save(false);

        /* @var PayoutItem[] */
        $itemRecords = [];
        /* @var ApiPayoutItem[] */
        $items = [];

        foreach ($users as $user) {
            // Try to debit the total balance from the developer's account
            $fundsManager = $user->getFundsManager();
            $amount = $fundsManager->getBalance();
            if ($amount <= 0) {
                continue;
            }

            try {
                $user->getFundsManager()->debit("PayPal payout $payoutRecord->id", $amount, FundsManager::TXN_TYPE_PAYPAL_PAYOUT);
            } catch (InaccessibleFundsException $e) {
                continue;
            }

            // Keep track of the payout amount in case something goes wrong
            $amounts[$user->id] = $amount;

            $itemRecord = new PayoutItem();
            $itemRecord->payoutId = $payoutRecord->id;
            $itemRecord->developerId = $user->id;
            $itemRecord->amount = $amount;
            $itemRecord->save(false);
            $itemRecords[$itemRecord->id] = $itemRecord;

            $items[] = (new ApiPayoutItem())
                ->setRecipientType('EMAIL')
                ->setReceiver($user->payPalEmail)
                ->setSenderItemId($itemRecord->id)
                ->setAmount((new Currency())
                    ->setValue($amount)
                    ->setCurrency('USD')
                );
        }

        // If we ended up skipping all items, delete the batch
        if (empty($items)) {
            $payoutRecord->delete();
            return null;
        }

        // Send the payout
        $payout = (new ApiPayout())
            ->setItems($items)
            ->setSenderBatchHeader((new PayoutSenderBatchHeader())
                ->setSenderBatchId($payoutRecord->id)
                ->setEmailSubject('Here’s your payout from the Craft Plugin Store')
            );

        try {
            $batch = $payout->create([], $this->_apiContext());
        } catch (PayPalConnectionException $e) {
            $this->_handleConnectionException($e, $payoutRecord->id);

            // Re-credit the developers' balances
            foreach ($users as $user) {
                if (isset($amounts[$user->id])) {
                    $user->getFundsManager()->credit("Unsuccessful PayPal payout $payoutRecord->id", $amounts[$user->id], null, FundsManager::TXN_TYPE_PAYPAL_PAYOUT);
                }
            }

            throw $e;
        }

        // Update our record.
        // Set status to PENDING regardless of actual status so we can reimburse developer accounts later via updateActivePayouts().
        $payoutRecord->payoutBatchId = $batch->getBatchHeader()->getPayoutBatchId();
        $payoutRecord->status = 'PENDING';
        $payoutRecord->save(false);

        return $payoutRecord;
    }

    /**
     * Checks on the status of any in-progress payouts.
     *
     * @return Payout[]
     * @throws PayPalConnectionException
     */
    public function updateInProgressPayouts(): array
    {
        /* @var Payout[] $payoutRecords */
        $payoutRecords = Payout::find()
            ->where([
                'status' => ['PENDING', 'PROCESSING'],
            ])
            ->with('items')
            ->all();

        foreach ($payoutRecords as $payoutRecord) {
            $this->updatePayout($payoutRecord);
        }

        return $payoutRecords;
    }

    /**
     * Updates an active payout.
     *
     * @param Payout $payoutRecord
     * @return void
     * @throws PayPalConnectionException
     */
    public function updatePayout(Payout $payoutRecord): void
    {
        /* @var PayoutItem[] $itemRecords */
        $itemRecords = ArrayHelper::index($payoutRecord->items, 'id');

        try {
            $batch = ApiPayout::get($payoutRecord->payoutBatchId, $this->_apiContext());
        } catch (PayPalConnectionException $e) {
            $this->_handleConnectionException($e, $payoutRecord->id);
            throw $e;
        }

        Craft::$app->db->transaction(function() use ($payoutRecord, $itemRecords, $batch) {
            $payoutRecord->status = $batch->getBatchHeader()->getBatchStatus();
            $payoutRecord->timeCompleted = Db::prepareDateForDb($batch->getBatchHeader()->getTimeCompleted());
            $payoutRecord->save(false);

            $isComplete = !in_array($payoutRecord->status, ['PENDING', 'PROCESSING']);

            foreach ($batch->getItems() as $item) {
                $itemId = $item->getPayoutItem()->getSenderItemId();
                if (!isset($itemRecords[$itemId])) {
                    throw new UnexpectedValueException("Unexpected payout item: $itemId");
                }

                $itemRecord = $itemRecords[$itemId];
                $itemRecord->payoutItemId = $item->getPayoutItemId();
                $itemRecord->transactionId = $item->getTransactionId();
                $itemRecord->transactionStatus = $item->getTransactionStatus();
                $itemRecord->timeProcessed = Db::prepareDateForDb($item->getTimeProcessed());
                $itemRecord->fee = $item->getPayoutItemFee()->getValue();
                $itemRecord->save(false);

                // If we're finished with the batch, see if there was an issue with this payment
                if ($isComplete && in_array($itemRecord->transactionStatus, ['FAILED', 'BLOCKED'])) {
                    // Re-credit the developers' balance
                    /* @var User|UserBehavior $user */
                    $user = User::findOne($itemRecord->developerId);
                    $user->getFundsManager()->credit("Unsuccessful PayPal payout $payoutRecord->id", $itemRecord->amount, null, FundsManager::TXN_TYPE_PAYPAL_PAYOUT);
                }
            }
        });
    }

    /**
     * @var ApiContext|null
     */
    private $_apiContext;

    /**
     * @return ApiContext
     */
    private function _apiContext(): ApiContext
    {
        if ($this->_apiContext === null) {
            $this->_apiContext = new ApiContext(new OAuthTokenCredential(App::env('PP_ID'), App::env('PP_SECRET')));
            $this->_apiContext->setConfig([
                'mode' => App::env('PP_MODE') ?: 'live',
            ]);
        }
        return $this->_apiContext;
    }

    /**
     * @param PayPalConnectionException $e
     * @param int $payoutId
     * @return void
     */
    private function _handleConnectionException(PayPalConnectionException $e, int $payoutId): void
    {
        Craft::warning("Unsuccessful PayPal connection: {$e->getMessage()}");
        Craft::$app->getErrorHandler()->logException($e);

        Db::insert(Table::PAYOUT_ERRORS, [
            'payoutId' => $payoutId,
            'message' => $e->getMessage(),
            'data' => $e->getData(),
            'date' => Db::prepareDateForDb(new \DateTime()),
        ], false);
    }
}
