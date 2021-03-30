<?php

namespace craft\contentmigrations;

use craft\db\Migration;
use craft\helpers\MigrationHelper;

/**
 * m210330_200103_index_pl_datecreated migration.
 */
class m210330_200103_index_pl_datecreated extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        if (!MigrationHelper::doesIndexExist('craftnet_pluginlicenses', ['dateCreated'])) {
            $this->createIndex(null, 'craftnet_pluginlicenses', ['dateCreated']);
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        if (MigrationHelper::doesIndexExist('craftnet_pluginlicenses', ['dateCreated'])) {
            $this->dropIndex($this->db->getIndexName('craftnet_pluginlicenses', ['dateCreated']), 'craftnet_pluginlicenses');
        }
    }
}
