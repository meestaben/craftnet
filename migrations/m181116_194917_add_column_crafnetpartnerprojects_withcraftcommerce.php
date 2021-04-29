<?php

namespace craft\contentmigrations;

use craft\db\Migration;
use craftnet\db\Table;

/**
 * m181116_194917_add_column_crafnetpartnerprojects_withcraftcommerce migration.
 */
class m181116_194917_add_column_crafnetpartnerprojects_withcraftcommerce extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn(
            Table::PARTNERPROJECTS,
            'withCraftCommerce',
            $this->boolean()->defaultValue(false)->notNull()
        );
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropColumn(Table::PARTNERPROJECTS, 'withCraftCommerce');

        return true;
    }
}
