<?php

namespace craft\contentmigrations;

use craft\db\Migration;
use craftnet\db\Table;

/**
 * m181019_024758_add_column_agencysize migration.
 */
class m181019_024758_add_column_agencysize extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn(
            Table::PARTNERS,
            'agencySize',
            $this->integer()->after('isRegisteredBusiness')
        );
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropcolumn(Table::PARTNERS, 'agencySize');

        return true;
    }
}
