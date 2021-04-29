<?php

namespace craft\contentmigrations;

use craft\db\Migration;
use craftnet\db\Table;

/**
 * m200609_230952_cmslicense_plugins migration.
 */
class m200609_230952_cmslicense_plugins extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->createTable(Table::CMSLICENSE_PLUGINS, [
            'licenseId' => $this->integer()->notNull(),
            'pluginId' => $this->integer()->notNull(),
            'timestamp' => $this->dateTime()->notNull(),
            'PRIMARY KEY([[licenseId]], [[pluginId]])',
        ]);
        $this->createIndex(null, Table::CMSLICENSE_PLUGINS, ['pluginId', 'timestamp']);
        $this->addForeignKey(null, Table::CMSLICENSE_PLUGINS, ['licenseId'], Table::CMSLICENSES, ['id'], 'CASCADE');
        $this->addForeignKey(null, Table::CMSLICENSE_PLUGINS, ['pluginId'], Table::PLUGINS, ['id'], 'CASCADE');
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200609_230952_cmslicense_plugins cannot be reverted.\n";
        return false;
    }
}
