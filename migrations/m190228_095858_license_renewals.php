<?php

namespace craft\contentmigrations;

use craft\db\Migration;
use craftnet\db\Table;

/**
 * m190228_095858_license_renewals migration.
 */
class m190228_095858_license_renewals extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn(Table::CMSLICENSES, 'reminded', $this->boolean()->defaultValue(false)->notNull());
        $this->addColumn(Table::PLUGINLICENSES, 'reminded', $this->boolean()->defaultValue(false)->notNull());

        $this->addColumn(Table::CMSLICENSES, 'renewalPrice', $this->decimal(14, 4)->unsigned()->null());
        $this->addColumn(Table::PLUGINLICENSES, 'renewalPrice', $this->decimal(14, 4)->unsigned()->null());

        $this->createIndex(null, Table::CMSLICENSES, ['expirable', 'reminded', 'expiresOn']);
        $this->createIndex(null, Table::PLUGINLICENSES, ['expirable', 'reminded', 'expiresOn']);

        $this->update(Table::CMSLICENSES, ['renewalPrice' => 59], [
            'editionId' => 1259,
            'expirable' => true
        ]);

        $sql = <<<SQL
update {{craftnet_pluginlicenses}}
set [[renewalPrice]] = [[pe.renewalPrice]]
from {{craftnet_plugineditions}} pe
where [[pe.id]] = [[editionId]]
and [[expirable]] = true
SQL;

        $this->execute($sql);
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m190228_095858_license_renewals cannot be reverted.\n";
        return false;
    }
}
