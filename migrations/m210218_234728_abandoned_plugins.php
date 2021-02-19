<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;

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
        $this->addColumn('craftnet_plugins', 'abandoned', $this->boolean()->notNull()->defaultValue(false));
        $this->addColumn('craftnet_plugins', 'replacementId', $this->integer());
        $this->addForeignKey(null, 'craftnet_plugins', ['replacementId'], 'craftnet_plugins', ['id'], 'SET NULL');
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
