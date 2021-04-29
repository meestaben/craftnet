<?php

namespace craft\contentmigrations;

use craft\db\Migration;
use craftnet\db\Table;

/**
 * m200610_222854_drop_installedplugins_table migration.
 */
class m200610_222854_drop_installedplugins_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->dropTable(Table::INSTALLEDPLUGINS);
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200610_222854_drop_installedplugins_table cannot be reverted.\n";
        return false;
    }
}
