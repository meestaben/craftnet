<?php

namespace craft\contentmigrations;

use craft\db\Migration;
use craftnet\db\Table;

/**
 * m181106_195542_add_column_logoassetid migration.
 */
class m181106_195542_add_column_logoassetid extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn(Table::PARTNERS, 'logoAssetId', $this->integer());
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropColumn(Table::PARTNERS, 'logoAssetId');

        return false;
    }
}
