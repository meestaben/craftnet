<?php

namespace craft\contentmigrations;

use craft\db\Migration;
use craftnet\db\Table;

/**
 * m180402_204534_rename_license_handle_columns migration.
 */
class m180402_204534_rename_license_handle_columns extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->renameColumn(Table::CMSLICENSES, 'edition', 'editionHandle');
        $this->renameColumn(Table::PLUGINLICENSES, 'plugin', 'pluginHandle');
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180402_204534_rename_license_handle_columns cannot be reverted.\n";
        return false;
    }
}
