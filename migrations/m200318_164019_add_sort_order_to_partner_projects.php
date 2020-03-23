<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;

/**
 * m200318_164019_add_sort_order_to_partner_projects migration.
 */
class m200318_164019_add_sort_order_to_partner_projects extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('craftnet_partnerprojects', 'sortOrder', $this->smallInteger()->unsigned()->notNull()->defaultValue(0));
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200318_164019_add_sort_order_to_partner_projects cannot be reverted.\n";
        return false;
    }
}
