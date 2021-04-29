<?php

namespace craft\contentmigrations;

use craft\db\Migration;
use craftnet\db\Table;

/**
 * m181019_015807_add_column_isregisteredbusiness migration.
 */
class m181019_015807_add_column_isregisteredbusiness extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn(
            Table::PARTNERS,
            'isRegisteredBusiness',
            $this->boolean()->defaultValue(false)->after('isEnterpriseVerified')
        );
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropcolumn(Table::PARTNERS, 'isRegisteredBusiness');

        return true;
    }
}
