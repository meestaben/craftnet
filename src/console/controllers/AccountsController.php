<?php

namespace craftnet\console\controllers;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\Plugin as Commerce;
use craft\elements\User;
use craft\i18n\Formatter;
use craftnet\behaviors\UserBehavior;
use craftnet\Module;
use craftnet\plugins\Plugin;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;
use craftnet\db\Table;
use craft\commerce\db\Table as CommerceTable;

/**
 * Show information about accounts
 *
 * @property Module $module
 */
class AccountsController extends Controller
{
    /**
     * @var int|null The maximum number of orders, Craft licenses, and plugin licenses to show
     */
    public $limit;

    /**
     * @inheritdoc
     */
    public function options($actionID)
    {
        $options = parent::options($actionID);
        if ($actionID === 'info') {
            $options[] = 'limit';
        }
        return $options;
    }

    /**
     * Shows information about an account
     *
     * @param string $username Username, email, or ID
     * @return int
     */
    public function actionInfo(string $username): int
    {
        /** @var User|UserBehavior|null $user */
        if (is_numeric($username)) {
            $user = User::find()->id($username)->anyStatus()->one();
        } else {
            $user = Craft::$app->getUsers()->getUserByUsernameOrEmail($username);
        }

        if (!$user) {
            $this->stderr('Invalid ID, username, or email' . PHP_EOL, Console::FG_RED);
            return 1;
        }

        $this->stdout(PHP_EOL);
        $this->user($user);
        $this->orders($user);
        $this->cmsLicenses($user);
        $this->pluginLicenses($user);
        $this->plugins($user);

        return ExitCode::OK;
    }

    /**
     * Reassigns orders, licenses, and plugins from one account to another, and deletes the first account
     *
     * @param int $id1 The account ID to be deleted
     * @param int $id2 The account ID to be preserved
     * @return int
     */
    public function actionMerge(int $id1, int $id2): int
    {
        $user1 = User::find()->id($id1)->anyStatus()->one();
        $user2 = User::find()->id($id2)->anyStatus()->one();

        if (!$user1) {
            $this->stderr('No user exists with an ID of ' . $id1 . PHP_EOL, Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        if (!$user2) {
            $this->stderr('No user exists with an ID of ' . $id2 . PHP_EOL, Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        /** @var Commerce $commerce */
        $commerce = Commerce::getInstance();
        $db = Craft::$app->getDb();

        $customerTables = [
            CommerceTable::CUSTOMER_DISCOUNTUSES,
            CommerceTable::CUSTOMERS_ADDRESSES,
            CommerceTable::ORDERHISTORIES,
            CommerceTable::ORDERS,
        ];

        $userTables = [
            CommerceTable::PAYMENTSOURCES,
            CommerceTable::SUBSCRIPTIONS,
            CommerceTable::TRANSACTIONS,
            Table::CMSLICENSES => 'ownerId',
            Table::PLUGINLICENSES => 'ownerId',
            Table::PLUGINS => 'developerId',
            Table::DEVELOPERLEDGER => 'developerId',
            Table::PACKAGES => 'developerId',
        ];

        $customer1 = $commerce->getCustomers()->getCustomerByUserId($id1);
        $customer2 = $commerce->getCustomers()->getCustomerByUserId($id2);

        if ($customer1 && $customer2) {
            foreach ($customerTables as $table) {
                $this->stdout("Updating {$table} ... ");
                $rows = $db->createCommand()
                    ->update($table, ['customerId' => $customer2->id], ['customerId' => $customer1->id], [], false)
                    ->execute();
                $this->stdout("done ({$rows} rows)" . PHP_EOL, Console::FG_GREEN);
            }

            $this->stdout("Deleting customer {$customer1->id} ... ");
            $commerce->getCustomers()->deleteCustomer($customer1);
            $this->stdout('done' . PHP_EOL . PHP_EOL, Console::FG_GREEN);
        } else if ($customer1) {
            $userTables[] = CommerceTable::CUSTOMERS;
        }

        foreach ($userTables as $table => $column) {
            if (is_numeric($table)) {
                $table = $column;
                $column = 'userId';
            }
            $this->stdout("Updating {$table} ... ");
            $rows = $db->createCommand()
                ->update($table, [$column => $id2], [$column => $id1], [], false)
                ->execute();
            $this->stdout("done ({$rows} rows)" . PHP_EOL, Console::FG_GREEN);
        }

        $this->stdout("Deleting user {$id1} ... ");
        Craft::$app->getElements()->deleteElement($user1);
        $this->stdout('done' . PHP_EOL . PHP_EOL, Console::FG_GREEN);

        return ExitCode::OK;
    }

    protected function user(User $user)
    {
        $formatter = Craft::$app->getFormatter();

        $this->stdout('ID: ', Console::FG_CYAN);
        $this->stdout($user->id . PHP_EOL);
        $this->stdout('Username: ', Console::FG_CYAN);
        $this->stdout($user->username . PHP_EOL);
        $this->stdout('Email: ', Console::FG_CYAN);
        $this->stdout($user->email . PHP_EOL);
        $this->stdout('Status: ', Console::FG_CYAN);
        $this->stdout(ucfirst($user->getStatus()) . PHP_EOL);
        $this->stdout('Last login: ', Console::FG_CYAN);
        $this->stdout(($user->lastLoginDate ? $formatter->asDate($user->lastLoginDate, Formatter::FORMAT_WIDTH_SHORT) : '') . PHP_EOL);
        $this->stdout('Developer: ', Console::FG_CYAN);
        $this->stdout(($user->isInGroup('developers') ? 'Yes' : 'No') . PHP_EOL);
        $this->stdout('Partner: ', Console::FG_CYAN);
        $this->stdout(($user->enablePartnerFeatures ? 'Yes' : 'No') . PHP_EOL);
        $this->stdout(PHP_EOL);
    }

    protected function orders(User $user)
    {
        $orders = Order::find()
            ->select(['elements.id', 'number', 'dateOrdered', 'totalPrice'])
            ->user($user)
            ->isCompleted()
            ->orderBy(['dateOrdered' => SORT_DESC])
            ->asArray()
            ->all();

        $totalOrders = count($orders);
        if ($this->limit) {
            $orders = array_slice($orders, 0, $this->limit);
        }

        $formatter = Craft::$app->getFormatter();
        foreach ($orders as &$order) {
            $order = [
                $order['id'],
                $order['number'],
                $formatter->asDate($order['dateOrdered'], Formatter::FORMAT_WIDTH_SHORT),
                $formatter->asCurrency($order['totalPrice'], 'USD'),
            ];
        }
        unset($order);

        $this->stdout("Orders ({$totalOrders})" . PHP_EOL . PHP_EOL, Console::FG_CYAN);
        if ($orders) {
            $this->table(['ID', 'Number', 'Date', 'Total'], $orders);
            if ($this->limit && count($orders) !== $totalOrders) {
                $this->stdout('...' . PHP_EOL);
            }
            $this->stdout(PHP_EOL);
        }
    }

    protected function cmsLicenses(User $user)
    {
        $cmsLicenses = $this->module->getCmsLicenseManager()->getLicensesByOwner($user);

        $totalCmsLicenses = count($cmsLicenses);
        if ($this->limit) {
            $cmsLicenses = array_slice($cmsLicenses, 0, $this->limit);
        }

        $formatter = Craft::$app->getFormatter();
        foreach ($cmsLicenses as &$cmsLicense) {
            $cmsLicense = [
                $cmsLicense->id,
                $cmsLicense->getShortKey(),
                ucfirst($cmsLicense->editionHandle),
                $cmsLicense->expiresOn ? $formatter->asDate($cmsLicense->expiresOn, Formatter::FORMAT_WIDTH_SHORT) : '',
            ];
        }
        unset($cmsLicense);

        $this->stdout("Craft Licenses ({$totalCmsLicenses})" . PHP_EOL . PHP_EOL, Console::FG_CYAN);
        if ($cmsLicenses) {
            $this->table(['ID', 'Key', 'Edition', 'Expiry Date'], $cmsLicenses);
            if ($this->limit && count($cmsLicenses) !== $totalCmsLicenses) {
                $this->stdout('...' . PHP_EOL);
            }
            $this->stdout(PHP_EOL);
        }
    }

    protected function pluginLicenses(User $user)
    {
        $pluginLicenses = $this->module->getPluginLicenseManager()->getLicensesByOwner($user);

        $totalPluginLicenses = count($pluginLicenses);
        if ($this->limit) {
            $pluginLicenses = array_slice($pluginLicenses, 0, $this->limit);
        }

        $formatter = Craft::$app->getFormatter();
        foreach ($pluginLicenses as &$pluginLicense) {
            $pluginLicense = [
                $pluginLicense->id,
                $pluginLicense->getShortKey(),
                $pluginLicense->getPlugin()->name,
                $pluginLicense->expiresOn ? $formatter->asDate($pluginLicense->expiresOn, Formatter::FORMAT_WIDTH_SHORT) : '',
            ];
        }
        unset($pluginLicense);

        $this->stdout("Plugin Licenses ({$totalPluginLicenses})" . PHP_EOL . PHP_EOL, Console::FG_CYAN);
        if ($pluginLicenses) {
            $this->table(['ID', 'Key', 'Plugin', 'Expiry Date'], $pluginLicenses);
            if ($this->limit && count($pluginLicenses) !== $totalPluginLicenses) {
                $this->stdout('...' . PHP_EOL);
            }
            $this->stdout(PHP_EOL);
        }
    }

    protected function plugins(User $user)
    {
        $plugins = Plugin::find()
            ->developerId($user->id)
            ->anyStatus()
            ->all();

        $totalPlugins = count($plugins);

        if ($this->limit) {
            $plugins = array_slice($plugins, 0, $this->limit);
        }

        foreach ($plugins as &$plugin) {
            $plugin = [
                $plugin->name,
            ];
        }
        unset($plugin);

        $this->stdout("Plugins ({$totalPlugins})" . PHP_EOL . PHP_EOL, Console::FG_CYAN);
        if ($plugins) {
            $this->table(['Name'], $plugins);
            if ($this->limit && count($plugins) !== $totalPlugins) {
                $this->stdout('...' . PHP_EOL);
            }
            $this->stdout(PHP_EOL);
        }
    }

    protected function table(array $headers = null, array $data)
    {
        // Figure out the max col sizes
        $cellSizes = [];
        foreach (array_merge($data, [$headers ?? []]) as $row) {
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
            $maxCellSizes[$i] = max($sizes);
        }

        if ($headers !== null) {
            $this->tableRow($headers, $maxCellSizes);
            $this->tableRow([], $maxCellSizes, '-');
        }

        foreach ($data as $row) {
            $this->tableRow($row, $maxCellSizes);
        }
    }

    protected function tableRow(array $row, array $sizes, $pad = ' ')
    {
        foreach ($sizes as $i => $size) {
            if ($i !== 0) {
                $this->stdout('  ');
            }

            $cell = $row[$i] ?? '';
            $value = is_array($cell) ? $cell[0] : $cell;
            $value = str_pad($value, $sizes[$i], $pad, $cell['pad'] ?? STR_PAD_RIGHT);
            if (isset($cell['format']) && $this->isColorEnabled()) {
                $value = Console::ansiFormat($value, $cell['format']);
            }
            $this->stdout($value);
        }

        $this->stdout(PHP_EOL);
    }
}
