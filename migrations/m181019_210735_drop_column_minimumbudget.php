<?php

namespace craft\contentmigrations;

use craft\db\Migration;
use craftnet\db\Table;

/**
 * m181019_210735_drop_column_minimumbudget migration.
 */
class m181019_210735_drop_column_minimumbudget extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->dropColumn(Table::PARTNERS, 'minimumBudget');
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->addColumn(Table::PARTNERS, 'minimumBudget', $this->integer());

        return true;
    }
}
