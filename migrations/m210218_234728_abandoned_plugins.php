<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craftnet\db\Table;

/**
 * m210218_234728_abandoned_plugins migration.
 */
class m210218_234728_abandoned_plugins extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn(Table::PLUGINS, 'abandoned', $this->boolean()->notNull()->defaultValue(false));
        $this->addColumn(Table::PLUGINS, 'replacementId', $this->integer());
        $this->addForeignKey(null, Table::PLUGINS, ['replacementId'], Table::PLUGINS, ['id'], 'SET NULL');
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m210218_234728_abandoned_plugins cannot be reverted.\n";
        return false;
    }
}
