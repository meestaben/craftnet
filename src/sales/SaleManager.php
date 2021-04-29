<?php

namespace craftnet\sales;

use craft\db\Query;
use craft\elements\User;
use craft\helpers\ArrayHelper;
use craftnet\db\Table;
use craft\db\Table as CraftTable;
use craft\commerce\db\Table as CommerceTable;
use craftnet\plugins\Plugin;
use craftnet\plugins\PluginEdition;
use yii\base\Component;

class SaleManager extends Component
{
    /**
     * Get sales by plugin owner.
     *
     * @param User $owner
     * @param string|null $searchQuery
     * @param $limit
     * @param $page
     * @return array
     */
    public function getSalesByPluginOwner(User $owner, string $searchQuery = null, $limit, $page): array
    {
        $defaultLimit = 30;
        $perPage = $limit ?? $defaultLimit;
        $offset = ($page - 1) * $perPage;

        $query = $this->_getSalesQuery($owner, $searchQuery);

        $query
            ->offset($offset)
            ->limit($limit);

        $results = $query->all();

        foreach ($results as &$row) {
            $row['netAmount'] = number_format($row['grossAmount'] * 0.8, 2);

            // Plugin
            $hasMultipleEditions = false;
            $plugin = Plugin::findOne($row['pluginId']);

            if ($plugin) {
                $editions = $plugin->getEditions();

                if ($editions) {
                    $hasMultipleEditions = count($editions) > 1;
                }
            }

            $row['plugin'] = [
                'id' => $row['pluginId'],
                'name' => $row['pluginName'],
                'hasMultipleEditions' => $hasMultipleEditions,
            ];

            // Customer
            $row['customer'] = [
                'id' => $row['ownerId'],
                'name' => implode(' ', array_filter([$row['ownerFirstName'], $row['ownerLastName']])),
                'email' => $row['ownerEmail'] ?? $row['orderEmail'],
            ];

            // Edition
            $edition = PluginEdition::findOne($row['editionId']);

            $row['edition'] = [
                'name' => $edition['name'],
                'handle' => $edition['handle'],
            ];

            // Unset attributes we don’t need anymore
            unset($row['pluginId'], $row['pluginName'], $row['ownerId'], $row['ownerFirstName'], $row['ownerLastName'], $row['ownerEmail']);
        }

        // Adjustments
        $results = ArrayHelper::index($results, 'id');
        $lineItemIds = array_keys($results);

        $adjustments = (new Query())
            ->select(['lineItemId', 'name', 'amount'])
            ->from([CommerceTable::ORDERADJUSTMENTS])
            ->where(['lineItemId' => $lineItemIds])
            ->all();

        foreach ($adjustments as $adjustment) {
            $results[$adjustment['lineItemId']]['adjustments'][] = $adjustment;
        }

        $results = array_values($results);

        return $results;
    }

    /**
     * Get total sales by plugin owner.
     *
     * @param User $owner
     * @param string|null $searchQuery
     * @return int|string
     */
    public function getTotalSalesByPluginOwner(User $owner, string $searchQuery = null)
    {
        $query = $this->_getSalesQuery($owner, $searchQuery);

        return $query->count();
    }

    /**
     * Get sales query.
     *
     * @param User $owner
     * @param string|null $searchQuery
     * @return Query
     */
    private function _getSalesQuery(User $owner, string $searchQuery = null): Query
    {
        $query = (new Query())
            ->select([
                'lineitems.id AS id',
                'plugins.id AS pluginId',
                'plugins.name AS pluginName',
                'lineitems.total AS grossAmount',
                'users.id AS ownerId',
                'users.firstName AS ownerFirstName',
                'users.lastName AS ownerLastName',
                'users.email AS ownerEmail',
                'lineitems.dateCreated AS saleTime',
                'orders.email AS orderEmail',
                'elements.type AS purchasableType',
                'licenses.editionId AS editionId',
            ])
            ->from([Table::PLUGINLICENSES_LINEITEMS . ' licenses_items'])
            ->innerJoin(CommerceTable::LINEITEMS . ' lineitems', '[[lineitems.id]] = [[licenses_items.lineItemId]]')
            ->innerJoin(CommerceTable::ORDERS . ' orders', '[[orders.id]] = [[lineitems.orderId]]')
            ->innerJoin(Table::PLUGINLICENSES . ' licenses', '[[licenses.id]] = [[licenses_items.licenseId]]')
            ->innerJoin(Table::PLUGINS . ' plugins', '[[plugins.id]] = [[licenses.pluginId]]')
            ->leftJoin(CraftTable::USERS, '[[users.id]] = [[licenses.ownerId]]')
            ->leftJoin(CraftTable::ELEMENTS, '[[elements.id]] = [[lineitems.purchasableId]]')
            ->where(['plugins.developerId' => $owner->id])
            ->orderBy(['lineitems.dateCreated' => SORT_DESC]);

        if ($searchQuery) {
            $query->andWhere([
                'or',
                ['ilike', 'orders.email', $searchQuery],
                ['ilike', 'plugins.name', $searchQuery],
                ['ilike', 'plugins.handle', $searchQuery],
            ]);
        }

        return $query;
    }
}
