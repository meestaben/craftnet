<?php

namespace craft\contentmigrations;

use craft\db\Migration;
use craftnet\db\Table;

/**
 * m201120_235906_nullable_plugin_license_edition migration.
 */
class m201120_235906_nullable_plugin_license_edition extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->execute('alter table craftnet_pluginlicenses alter column "editionId" drop not null');
        $this->execute('alter table craftnet_pluginlicenses alter column "edition" drop not null');
        $this->addColumn(Table::PLUGINLICENSES, 'trial', $this->boolean()->notNull()->defaultValue(false));
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m201120_235906_nullable_plugin_license_edition cannot be reverted.\n";
        return false;
    }
}
