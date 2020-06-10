<?php

namespace craft\contentmigrations;

use craft\db\Migration;

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
        $this->createTable('craftnet_cmslicense_plugins', [
            'licenseId' => $this->integer()->notNull(),
            'pluginId' => $this->integer()->notNull(),
            'timestamp' => $this->dateTime()->notNull(),
            'PRIMARY KEY([[licenseId]], [[pluginId]])',
        ]);
        $this->createIndex(null, 'craftnet_cmslicense_plugins', ['pluginId', 'timestamp']);
        $this->addForeignKey(null, 'craftnet_cmslicense_plugins', ['licenseId'], 'craftnet_cmslicenses', ['id'], 'CASCADE');
        $this->addForeignKey(null, 'craftnet_cmslicense_plugins', ['pluginId'], 'craftnet_plugins', ['id'], 'CASCADE');
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
