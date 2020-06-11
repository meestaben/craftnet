<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;

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
        $this->dropTable('craftnet_installedplugins');
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
