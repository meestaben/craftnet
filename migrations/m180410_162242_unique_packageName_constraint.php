<?php

namespace craft\contentmigrations;

use craft\db\Migration;
use craftnet\db\Table;

/**
 * m180410_162242_unique_packageName_constraint migration.
 */
class m180410_162242_unique_packageName_constraint extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->createIndex(null, Table::PLUGINS, ['packageName'], true);
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180410_162242_unique_packageName_constraint cannot be reverted.\n";
        return false;
    }
}
