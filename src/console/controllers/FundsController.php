<?php

namespace craftnet\console\controllers;

use Craft;
use craft\db\Query;
use craft\elements\User;
use craft\i18n\Locale;
use craftnet\behaviors\UserBehavior;
use craftnet\Module;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Manages developer funds
 *
 * @property Module $module
 */
class FundsController extends Controller
{
    public $defaultAction = 'report';

    /**
     * @var int The number of items to show on each page of the ledger.
     */
    public $batchSize = 50;

    public function options($actionID)
    {
        $options = parent::options($actionID);
        if ($actionID === 'ledger') {
            $options[] = 'batchSize';
        }
        return $options;
    }

    /**
     * Outputs a list of accounts that have a balance owed.
     *
     * @return int
     */
    public function actionReport(): int
    {
        // Find the accounts we owe money to
        $accounts = User::find()
            ->select(['username', 'firstName', 'lastName', 'field_developerName', 'email', 'payPalEmail', 'balance'])
            ->andWhere(['not', ['balance' => 0]])
            ->orderBy(['balance' => SORT_DESC])
            ->asArray()
            ->all();

        if (empty($accounts)) {
            $this->stdout('No accounts with a non-zero balance.' . PHP_EOL, Console::FG_GREEN);
            return ExitCode::OK;
        }

        $tableData = [];
        foreach ($accounts as $account) {
            $name = implode(' ', array_filter([$account['firstName'], $account['lastName']]));
            if ($name && $account['field_developerName']) {
                $name = "{$name} ({$account['field_developerName']})";
            } else if ($account['field_developerName']) {
                $name = $account['field_developerName'];
            }

            $tableData[] = [
                $account['username'],
                $name,
                $account['email'],
                $account['payPalEmail'],
                $this->_currency($account['balance'], true),
            ];
        }
        $this->stdout('Accounts with a non-zero balance:' . PHP_EOL . PHP_EOL);
        $this->table(['Username', 'Name', 'Email', 'PayPal', 'Balance'], $tableData);
        $this->stdout(PHP_EOL);

        return ExitCode::OK;
    }

    /**
     * Credits an account.
     *
     * @param string|null $username
     * @return int
     */
    public function actionCredit(string $username = null): int
    {
        $account = $this->_account($username);
        $fm = $account->getFundsManager();
        $balance = $fm->getBalance();

        $this->stdout('Current balance: ' . $this->_currency($balance) . PHP_EOL, Console::FG_YELLOW);

        $amount = (float)$this->prompt('Credit amount: ($)', [
            'required' => true,
            'validator' => function($input) {
                return is_numeric($input);
            },
        ]);

        $note = $this->prompt('Note:', [
            'required' => true,
        ]);

        $this->stdout('Account will go from ' . $this->_currency($balance) . ' to ' . $this->_currency($balance + $amount) . '.' . PHP_EOL, Console::FG_YELLOW);

        if (!$this->confirm('Proceed?')) {
            return ExitCode::OK;
        }

        $fm->credit($note, $amount);
        $this->stdout('Done. Account is now at ' . $this->_currency($fm->getBalance()) . PHP_EOL, Console::FG_YELLOW);
        return ExitCode::OK;
    }

    /**
     * Debits an account.
     *
     * @param string|null $username
     * @return int
     */
    public function actionDebit(string $username = null): int
    {
        $account = $this->_account($username);
        $fm = $account->getFundsManager();
        $balance = $fm->getBalance();

        $this->stdout('Current balance: ' . $this->_currency($balance) . PHP_EOL, Console::FG_YELLOW);

        $amount = (float)$this->prompt('Debit amount: ($)', [
            'required' => true,
            'validator' => function($input) {
                return is_numeric($input);
            },
            'default' => $balance,
        ]);

        $note = $this->prompt('Note:', [
            'required' => true,
        ]);

        $this->stdout('Account will go from ' . $this->_currency($balance) . ' to ' . $this->_currency($balance - $amount) . '.' . PHP_EOL, Console::FG_YELLOW);

        if (!$this->confirm('Proceed?')) {
            return ExitCode::OK;
        }

        $fm->debit($note, $amount);
        $this->stdout('Done. Current balance is now ' . $this->_currency($fm->getBalance()) . PHP_EOL, Console::FG_YELLOW);
        return ExitCode::OK;
    }

    /**
     * Outputs the ledger for an account.
     *
     * @param string|null $username
     * @return int
     */
    public function actionLedger(string $username = null): int
    {
        $account = $this->_account($username);

        $records = (new Query())
            ->select(['dateCreated', 'note', 'type', 'credit', 'debit', 'balance'])
            ->from(['craftnet_developerledger'])
            ->where(['developerId' => $account->id])
            ->orderBy(['id' => SORT_ASC])
            ->all();

        $tableData = [];
        $formatter = Craft::$app->getFormatter();
        foreach ($records as $record) {
            $tableData[] = [
                $formatter->asDatetime($record['dateCreated'], Locale::LENGTH_SHORT),
                $record['note'],
                $record['type'],
                $record['credit'] ? ['+' . $formatter->asCurrency($record['credit'], 'USD'), 'pad' => STR_PAD_LEFT] : '',
                $record['debit'] ? ['-' . $formatter->asCurrency($record['debit'], 'USD'), 'pad' => STR_PAD_LEFT] : '',
                $this->_currency($record['balance'], true),
            ];
        }

        $this->stdout(PHP_EOL);
        $this->table([
            'Date',
            'Note',
            'Type',
            'Credit',
            'Debit',
            ['Balance', 'pad' => STR_PAD_LEFT],
        ], $tableData);
        $this->stdout(PHP_EOL);

        return ExitCode::OK;
    }

    private function _currency($amount, bool $forTable = false)
    {
        $formatted = Craft::$app->getFormatter()->asCurrency($amount, 'USD');
        if (!$this->isColorEnabled()) {
            return $forTable
                ? [$formatted, 'pad' => STR_PAD_LEFT]
                : $formatted;
        }
        $color = [$amount >= 0 ? Console::FG_GREEN : Console::FG_RED];
        return $forTable
            ? [$formatted, 'format' => $color, 'pad' => STR_PAD_LEFT]
            : Console::ansiFormat($formatted, $color);
    }

    protected function table(array $headers, array $data)
    {
        // Figure out the max col sizes
        $cellSizes = [];
        foreach (array_merge($data, [$headers]) as $row) {
            foreach ($row as $i => $cell) {
                if (is_array($cell)) {
                    $cellSizes[$i][] = strlen($cell[0]);
                } else {
                    $cellSizes[$i][] = strlen($cell);
                }
            }
        }

        $maxCellSizes = [];
        foreach ($cellSizes as $i => $sizes) {
            $maxCellSizes[$i] = min(max($sizes), 80);
        }

        $this->tableRow($headers, $maxCellSizes);
        $this->stdout(str_repeat('-', array_sum($maxCellSizes) + (count($maxCellSizes) - 1) * 2) . PHP_EOL);
        foreach ($data as $row) {
            $this->tableRow($row, $maxCellSizes);
        }
    }

    protected function tableRow(array $row, array $sizes)
    {
        foreach ($sizes as $i => $size) {
            if ($i !== 0) {
                $this->stdout('  ');
            }

            $cell = $row[$i] ?? '';
            $value = is_array($cell) ? $cell[0] : $cell;
            $len = strlen($value);
            if ($len < $sizes[$i]) {
                $value = str_pad($value, $sizes[$i], ' ', $cell['pad'] ?? STR_PAD_RIGHT);
            } else if ($len > $sizes[$i]) {
                $value = substr($value, 0, $sizes[$i] - 1) . '…';
            }
            if (isset($cell['format']) && $this->isColorEnabled()) {
                $value = Console::ansiFormat($value, $cell['format']);
            }
            $this->stdout($value);
        }

        $this->stdout(PHP_EOL);
    }

    /**
     * @param string|null $username
     * @return User|UserBehavior
     */
    protected function _account(string $username = null): User
    {
        if ($username === null) {
            $username = $this->prompt('Username:', [
                'required' => true,
            ]);
        }

        $account = User::find()
            ->username($username)
            ->one();

        if (!$account) {
            $this->stderr('No account exists with that username.' . PHP_EOL, Console::FG_RED);
            return $this->_account();
        }

        return $account;
    }
}
