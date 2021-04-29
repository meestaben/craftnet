<?php

namespace craft\contentmigrations;

use craft\db\Migration;
use craftnet\db\Table;

/**
 * m181115_204831_add_column_partnerproject_linktype migration.
 */
class m181115_204831_add_column_partnerproject_linktype extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn(Table::PARTNERPROJECTS, 'linkType', $this->text()->defaultValue('website'));
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropColumn(Table::PARTNERPROJECTS, 'linkType');

        return true;
    }
}
