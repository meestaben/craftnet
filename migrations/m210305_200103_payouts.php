<?php

namespace craft\contentmigrations;

use craft\db\Migration;
use craftnet\db\Table;
use craft\db\Table as CraftTable;

/**
 * m210305_200103_payouts migration.
 */
class m210305_200103_payouts extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->createTable(Table::PAYOUTS, [
            'id' => $this->primaryKey(),
            'payoutBatchId' => $this->string(),
            'status' => $this->string(),
            'timeCompleted' => $this->dateTime(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex(null, Table::PAYOUTS, ['status']);

        $this->createTable(Table::PAYOUT_ITEMS, [
            'id' => $this->primaryKey(),
            'payoutId' => $this->integer()->notNull(),
            'developerId' => $this->integer()->notNull(),
            'amount' => $this->decimal(14, 4)->unsigned()->notNull(),
            'payoutItemId' => $this->string(),
            'transactionId' => $this->string(),
            'transactionStatus' => $this->string(),
            'timeProcessed' => $this->dateTime(),
            'fee' => $this->decimal(14, 4)->unsigned(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
        ]);
        $this->addForeignKey(null, Table::PAYOUT_ITEMS, ['payoutId'], Table::PAYOUT_ITEMS, ['id'], 'CASCADE');
        $this->addForeignKey(null, Table::PAYOUT_ITEMS, ['developerId'], CraftTable::USERS, ['id'], 'CASCADE');

        $this->createTable(Table::PAYOUT_ERRORS, [
            'id' => $this->primaryKey(),
            'payoutId' => $this->integer()->notNull(),
            'message' => $this->text()->notNull(),
            'data' => $this->text(),
            'date' => $this->dateTime()->notNull(),
        ]);
        $this->addForeignKey(null, Table::PAYOUT_ERRORS, ['payoutId'], Table::PAYOUTS, ['id'], 'CASCADE');
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropTable(Table::PAYOUT_ERRORS);
        $this->dropTable(Table::PAYOUT_ITEMS);
        $this->dropTable(Table::PAYOUTS);
    }
}
