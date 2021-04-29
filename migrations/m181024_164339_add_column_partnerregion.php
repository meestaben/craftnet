<?php

namespace craft\contentmigrations;

use craft\db\Migration;
use craftnet\db\Table;

/**
 * m181024_164339_add_column_partnerregion migration.
 */
class m181024_164339_add_column_partnerregion extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn(Table::PARTNERS, 'region', $this->string());
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropColumn(Table::PARTNERS, 'region');

        return true;
    }
}
