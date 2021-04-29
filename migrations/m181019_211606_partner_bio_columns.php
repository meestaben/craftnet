<?php

namespace craft\contentmigrations;

use craft\db\Migration;
use craftnet\db\Table;

/**
 * m181019_211606_partner_bio_columns migration.
 */
class m181019_211606_partner_bio_columns extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn(
            Table::PARTNERS,
            'shortBio',
            $this->string()->after('businessSummary')
        );

        $this->renameColumn(Table::PARTNERS, 'businessSummary', 'fullBio');
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropColumn(Table::PARTNERS, 'shortBio');
        $this->renameColumn(Table::PARTNERS, 'fullBio', 'businessSummary');

        return true;
    }
}
