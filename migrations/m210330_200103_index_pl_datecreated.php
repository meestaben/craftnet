<?php

namespace craft\contentmigrations;

use craft\db\Migration;
use craft\helpers\MigrationHelper;
use craftnet\db\Table;

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
        if (!MigrationHelper::doesIndexExist(Table::PLUGINLICENSES, ['dateCreated'])) {
            $this->createIndex(null, Table::PLUGINLICENSES, ['dateCreated']);
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        if (MigrationHelper::doesIndexExist(Table::PLUGINLICENSES, ['dateCreated'])) {
            $this->dropIndex($this->db->getIndexName(Table::PLUGINLICENSES, ['dateCreated']), Table::PLUGINLICENSES);
        }
    }
}
