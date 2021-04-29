<?php

namespace craft\contentmigrations;

use craft\db\Migration;
use craftnet\db\Table;

/**
 * m181023_212855_add_column_partner_verification_date migration.
 */
class m181023_212855_add_column_partner_verification_date extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn(Table::PARTNERS, 'verificationStartDate', $this->date());
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropColumn(Table::PARTNERS, 'verificationStartDate');

        return true;
    }
}
