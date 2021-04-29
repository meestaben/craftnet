<?php

namespace craft\contentmigrations;

use craft\db\Migration;
use craftnet\db\Table;

/**
 * m181108_180436_add_column_partner_website migration.
 */
class m181108_180436_add_column_partner_website extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn(Table::PARTNERS, 'website', $this->text());
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {

        $this->dropColumn(Table::PARTNERS, 'website');

        return true;
    }
}
