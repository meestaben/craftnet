<?php

namespace craft\contentmigrations;

use craft\db\Migration;

/**
 * m200703_222854_add_license_status migration.
 */
class m200703_222854_add_license_status extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        if (!$this->db->columnExists('{{%craftnet_cmslicenses}}', 'lastStatus')) {
            $this->addColumn('{{%craftnet_cmslicenses}}', 'lastStatus', $this->string());
        }

        if (!$this->db->columnExists('{{%craftnet_pluginlicenses}}', 'lastStatus')) {
            $this->addColumn('{{%craftnet_pluginlicenses}}', 'lastStatus', $this->string());
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200703_222854_add_license_status cannot be reverted.\n";
        return false;
    }
}
