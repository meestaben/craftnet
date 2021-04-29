<?php

namespace craft\contentmigrations;

use craft\db\Migration;
use craftnet\db\Table;

/**
 * m180904_214847_create_partnerhistory_table migration.
 */
class m180904_214847_create_partnerhistory_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->createTable(Table::PARTNERHISTORY, [
            'id' => $this->primaryKey(),
            'authorId' => $this->integer(),
            'partnerId' => $this->integer()->notNull(),
            'message' => $this->string()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->addForeignKey(null, Table::PARTNERHISTORY, ['partnerId'], Table::PARTNERS, ['id'], 'CASCADE');
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropTableIfExists(Table::PARTNERHISTORY);
        return true;
    }
}
