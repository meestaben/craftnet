<?php

namespace craft\contentmigrations;

use craft\db\Migration;
use craftnet\db\Table;

/**
 * m181018_162119_add__more_partner_columns migration.
 */
class m181018_162119_add__more_partner_columns extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn(
            Table::PARTNERS,
            'hasFullTimeDev',
            $this->boolean()->defaultValue(false)->after('minimumBudget')
        );

        $this->addColumn(
            Table::PARTNERS,
            'isCraftVerified',
            $this->boolean()->defaultValue(false)->after('hasFullTimeDev')
        );

        $this->addColumn(
            Table::PARTNERS,
            'isCommerceVerified',
            $this->boolean()->defaultValue(false)->after('isCraftVerified')
        );

        $this->addColumn(
            Table::PARTNERS,
            'isEnterpriseVerified',
            $this->boolean()->defaultValue(false)->after('isCommerceVerified')
        );
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropColumn(Table::PARTNERS, 'hasFullTimeDev');
        $this->dropColumn(Table::PARTNERS, 'isCraftVerified');
        $this->dropColumn(Table::PARTNERS, 'isCommerceVerified');
        $this->dropColumn(Table::PARTNERS, 'isEnterpriseVerified');

        return true;
    }
}
