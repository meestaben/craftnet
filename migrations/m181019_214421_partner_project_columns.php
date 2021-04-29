<?php

namespace craft\contentmigrations;

use craft\db\Migration;
use craftnet\db\Table;

/**
 * m181019_214421_partner_project_columns migration.
 */
class m181019_214421_partner_project_columns extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn(Table::PARTNERPROJECTS, 'name', $this->string()->after('partnerId'));
        $this->addColumn(Table::PARTNERPROJECTS, 'role', $this->string()->after('name'));
        $this->dropColumn(Table::PARTNERPROJECTS, 'private');
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropColumn(Table::PARTNERPROJECTS, 'name');
        $this->dropColumn(Table::PARTNERPROJECTS, 'role');
        $this->addColumn(Table::PARTNERPROJECTS, 'private', $this->boolean()->after('url'));

        return true;
    }
}
