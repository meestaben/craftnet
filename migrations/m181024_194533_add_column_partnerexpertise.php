<?php

namespace craft\contentmigrations;

use craft\db\Migration;
use craftnet\db\Table;

/**
 * m181024_194533_add_column_partnerexpertise migration.
 */
class m181024_194533_add_column_partnerexpertise extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn(Table::PARTNERS, 'expertise', $this->text());
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {

        $this->dropColumn(Table::PARTNERS, 'expertise');

        return true;
    }
}
