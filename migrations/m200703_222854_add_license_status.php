<?php

namespace craft\contentmigrations;

use craft\db\Migration;
use craftnet\db\Table;

/**
 * m200703_222854_add_license_status migration.
 */
class m200703_222854_add_license_status extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        if (!$this->db->columnExists(Table::CMSLICENSES, 'lastStatus')) {
            $this->addColumn(Table::CMSLICENSES, 'lastStatus', $this->string());
        }

        if (!$this->db->columnExists(Table::PLUGINLICENSES, 'lastStatus')) {
            $this->addColumn(Table::PLUGINLICENSES, 'lastStatus', $this->string());
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200703_222854_add_license_status cannot be reverted.\n";
        return false;
    }
}
