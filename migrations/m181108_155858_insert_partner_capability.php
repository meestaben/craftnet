<?php

namespace craft\contentmigrations;

use craft\db\Migration;
use craftnet\db\Table;

/**
 * m181108_155858_insert_partner_capability migration.
 */
class m181108_155858_insert_partner_capability extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->insert(Table::PARTNERCAPABILITIES, [
            'id' => 5,
            'title' => 'Ongoing Maintenance'
        ], false);
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->delete(Table::PARTNERCAPABILITIES, ['id' => 5]);
        return true;
    }
}
