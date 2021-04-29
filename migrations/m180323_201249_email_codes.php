<?php

namespace craft\contentmigrations;

use craft\db\Migration;
use craftnet\db\Table;
use craft\db\Table as CraftTable;

/**
 * m180323_201249_email_codes migration.
 */
class m180323_201249_email_codes extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->createTable(Table::EMAILCODES, [
            'id' => $this->primaryKey(),
            'userId' => $this->integer(),
            'email' => $this->string()->notNull(),
            'code' => $this->string()->notNull(),
            'dateIssued' => $this->dateTime()->notNull(),
        ]);

        $this->addForeignKey(null, Table::EMAILCODES, ['userId'], CraftTable::USERS, ['id'], 'CASCADE');
        $this->createIndex(null, Table::EMAILCODES, ['userId', 'email']);
        $this->createIndex(null, Table::EMAILCODES, ['dateIssued']);
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180323_201249_email_codes cannot be reverted.\n";
        return false;
    }
}
