<?php

namespace craftnet\developers;

use Craft;
use craft\commerce\models\LineItem;
use craft\db\Query;
use craft\elements\User;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use craftnet\errors\InaccessibleFundsException;
use craftnet\errors\InsufficientFundsException;
use craftnet\errors\MissingStripeAccountException;
use Moccalotto\Eu\CountryInfo;
use Stripe\Charge;
use Stripe\Error\Base as StripeError;
use Stripe\Stripe;
use Stripe\Transfer;
use yii\base\BaseObject;
use yii\base\InvalidArgumentException;
use yii\db\Expression;

class FundsManager extends BaseObject
{
    const TXN_TYPE_PLUGIN_PAYMENT = 'plugin_payment';
    const TXN_TYPE_STRIPE_TRANSFER = 'stripe_transfer';

    /**
     * @var User|UserBehavior
     */
    public $developer;

    /**
     * @var string
     */
    private $_lockName;

    /**
     * @var bool
     * @see _lockFunds()
     * @see _unlockFunds()
     */
    private $_lockLevel = 0;

    /**
     * @inheritdoc
     */
    public function __construct(User $developer, array $config = [])
    {
        $this->developer = $developer;
        $this->_lockName = 'funds:' . $developer->id;
        parent::__construct($config);
    }

    /**
     * Returns the developer’s current balance.
     */
    public function getBalance(): float
    {
        return (new Query())
            ->select(['balance'])
            ->from('craftnet_developers')
            ->where(['id' => $this->developer->id])
            ->scalar();
    }

    /**
     * Credits the developer's account.
     *
     * @param string $note the transaction note
     * @param float $credit the credit amount (not including any fees that need to be removed)
     * @param float|null $fee the total fees that should be deducted from the credit amount
     * @param string|null $txnType the transaction type (e.g. `plugin_payment`)
     * @return int the transaction ID
     * @throws InvalidArgumentException if $credit or $fee are negative or 0
     */
    public function credit(string $note, float $credit, float $fee = null, string $txnType = null): int
    {
        if ($credit <= 0) {
            throw new InvalidArgumentException('Invalid credit amount: ' . $credit);
        }

        if ($fee !== null && $fee <= 0) {
            throw new InvalidArgumentException('Invalid fee amount: ' . $credit);
        }

        return $this->_addTransaction($note, $credit, null, $fee, $txnType);
    }

    /**
     * Debits the developer's account.
     *
     * @param string $note the transaction note
     * @param float $debit the credit amount (not including any fees that need to be removed)
     * @param string|null $txnType the transaction type (e.g. `stripe_transfer`)
     * @return int the transaction ID
     * @throws InvalidArgumentException if $debit is negative or 0
     * @throws InaccessibleFundsException if the developer's funds could not be locked
     */
    public function debit(string $note, float $debit, string $txnType = null): int
    {
        if ($debit <= 0) {
            throw new InvalidArgumentException('Invalid debit amount: ' . $debit);
        }

        // no double-dipping
        if (!$this->_lockFunds()) {
            throw new InaccessibleFundsException();
        }

        $txnId = $this->_addTransaction($note, null, $debit, null, $txnType);
        $this->_unlockFunds();
        return $txnId;
    }

    /**
     * Credits the developer's account based on their total sales in a given order,
     * and then attempts to transfer the funds to their Stripe account.
     *
     * @param string $orderNumber
     * @param LineItem[] $lineItems
     * @param string $chargeId
     * @param float $credit the credit amount (not including any fees that need to be removed)
     * @param float|null $fee the total fees that should be deducted from the credit amount
     * @throws InvalidArgumentException if $credit or $fee are negative or 0
     */
    public function processOrder(string $orderNumber, array $lineItems, string $chargeId, float $credit, float $fee = null)
    {
        $e = null;

        // no double-dipping
        if (!$this->_lockFunds(5)) {
            $e = new InaccessibleFundsException();
        }

        // credit the account
        $txnId = $this->credit("Payment received for order {$orderNumber}", $credit, $fee, self::TXN_TYPE_PLUGIN_PAYMENT);

        // only attempt the transfer if we could get a lock
        if (!$e) {
            // figure out how much we're going to transfer (credit amount minus fee, but no more than whatever they actually have in their account)
            $balance = $this->getBalance();
            $transferAmount = min($credit - ($fee ?? 0), $balance);

            // don't transfer if they're in the red
            if ($transferAmount <= 0) {
                $e = new InsufficientFundsException($balance);
            } else {
                $itemDescriptions = [];
                foreach ($lineItems as $lineItem) {
                    $itemDescriptions[] = $lineItem->getDescription();
                }
                try {
                    $transfer = $this->_transferFunds("Funds transferred for order {$orderNumber}", $transferAmount, [
                        'source_transaction' => $chargeId,
                        'metadata' => [
                            'items' => implode(', ', $itemDescriptions),
                            'order_number' => $orderNumber,
                            'credit_id' => $txnId,
                            'credit_amount' => $credit,
                            'credit_fee' => $fee ?? 0,
                        ],
                    ]);

                    // Update the charge description with the line items
                    Stripe::setApiKey($this->developer->stripeAccessToken);
                    /** @var Charge $charge */
                    $charge = Charge::retrieve($transfer->destination_payment);
                    $charge->description = implode(', ', $itemDescriptions);
                    $charge->save();
                } catch (StripeError $e) {
                    // Something unexpected happened on Stripe's end
                    Craft::$app->getErrorHandler()->logException($e);
                } catch (\Throwable $e) {
                }
            }
        }

        if ($e) {
            $this->_addTransaction("Failed transfer: {$e->getMessage()}");
        }

        // unlock the funds
        $this->_unlockFunds();
    }

    /**
     * Locks the developer's funds so no other FundsManager instance can deduct funds.
     *
     * @param int $timeout
     * @return bool
     */
    private function _lockFunds(int $timeout = 0): bool
    {
        if ($this->_lockLevel === 0) {
            if (!Craft::$app->getMutex()->acquire($this->_lockName, $timeout)) {
                return false;
            }
        }
        $this->_lockLevel++;
        return true;
    }

    /**
     * Unlocks the developer's funds so other FundsManager instances can deduct funds again.
     */
    private function _unlockFunds()
    {
        if ($this->_lockLevel === 0) {
            return;
        }
        $this->_lockLevel--;
        if ($this->_lockLevel === 0) {
            Craft::$app->getMutex()->release($this->_lockName);
        }
    }

    /**
     * Attempts to transfer funds to the developer's Stripe account.
     *
     * @param string $note
     * @param float $amount
     * @param array $params
     * @return Transfer
     * @throws InvalidArgumentException if $credit or $fee are negative or 0
     * @throws MissingStripeAccountException if the developer doesn't have a Stripe account
     * @throws InaccessibleFundsException if the developer's funds could not be locked
     * @throws InsufficientFundsException if the developer doesn't have enough funds for the transfer
     * @throws StripeError if anything goes wrong on Stripe's end
     */
    private function _transferFunds(string $note, float $amount, array $params = []): Transfer
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Invalid transfer amount: ' . $amount);
        }

        if (($stripeAccount = $this->developer->stripeAccount) === null) {
            throw new MissingStripeAccountException();
        }

        // no double-dipping
        if (!$this->_lockFunds()) {
            throw new InaccessibleFundsException();
        }

        // make sure the funds are there
        $balance = $this->getBalance();
        if ($balance < $amount) {
            $this->_unlockFunds();
            throw new InsufficientFundsException($balance);
        }

        Stripe::setApiKey(getenv('STRIPE_API_KEY'));

        $params = ArrayHelper::merge([
            'amount' => round($amount * 100),
            'currency' => 'usd',
            'destination' => $stripeAccount,
            'metadata' => [
                'developer_id' => $this->developer->id,
                'developer_name' => $this->developer->getDeveloperName(),
                'developer_email' => $this->developer->email,
            ],
        ], $params);

        try {
            $transfer = Transfer::create($params);
        } catch (StripeError $e) {
            $this->_unlockFunds();
            throw $e;
        }

        $this->debit($note, $amount, self::TXN_TYPE_STRIPE_TRANSFER);
        $this->_unlockFunds();

        return $transfer;
    }

    /**
     * Adds a new transaction to the developer's ledger, and updates their current account balance.
     *
     * @param string $note
     * @param float|null $credit
     * @param float|null $debit
     * @param float|null $fee
     * @param string|null $type
     * @return int the transaction ID
     */
    private function _addTransaction(string $note, float $credit = null, float $debit = null, float $fee = null, string $type = null): int
    {
        $db = Craft::$app->getDb();

        if ($credit !== null) {
            $operator = '+';
            $adjustment = $credit - ($fee ?? 0);
        } else if ($debit !== null) {
            $operator = '-';
            $adjustment = $debit;
        } else {
            $adjustment = false;
        }

        if ($adjustment !== false) {
            $db->createCommand()
                ->update('craftnet_developers',
                    [
                        'balance' => new Expression("[[balance]] {$operator} :adjustment", [':adjustment' => $adjustment])
                    ],
                    [
                        'id' => $this->developer->id
                    ], [], false)
                ->execute();
        }

        $ledgerSql = <<<SQL
insert into {{craftnet_developerledger}} (
    [[developerId]],
    [[note]],
    [[credit]],
    [[debit]],
    [[fee]],
    [[balance]],
    [[type]],
    [[country]],
    [[isEuMember]],
    [[dateCreated]]
) values (
    :developerId,
    :note,
    :credit,
    :debit,
    :fee,
    (
        select [[balance]]
        from {{craftnet_developers}}
        where [[id]] = :developerId
    ),
    :type,
    :country,
    :isEuMember,
    :dateCreated
)
SQL;

        $db->createCommand($ledgerSql, [
            'developerId' => $this->developer->id,
            'note' => $note,
            'credit' => $credit,
            'debit' => $debit,
            'fee' => $fee,
            'type' => $type,
            'country' => $this->developer->country,
            'isEuMember' => $this->developer->country ? (new CountryInfo())->isEuMember($this->developer->country) : null,
            'dateCreated' => Db::prepareDateForDb(new \DateTime()),
        ])->execute();

        return $db->getLastInsertID('craftnet_developerledger');
    }
}
