<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;

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
        $this->createTable('craftnet_payouts', [
            'id' => $this->primaryKey(),
            'payoutBatchId' => $this->string(),
            'status' => $this->string(),
            'timeCompleted' => $this->dateTime(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex(null, 'craftnet_payouts', ['status']);

        $this->createTable('craftnet_payout_items', [
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
        $this->addForeignKey(null, 'craftnet_payout_items', ['payoutId'], 'craftnet_payouts', ['id'], 'CASCADE');
        $this->addForeignKey(null, 'craftnet_payout_items', ['developerId'], 'users', ['id'], 'CASCADE');

        $this->createTable('craftnet_payout_errors', [
            'id' => $this->primaryKey(),
            'payoutId' => $this->integer()->notNull(),
            'message' => $this->text()->notNull(),
            'data' => $this->text(),
            'date' => $this->dateTime()->notNull(),
        ]);
        $this->addForeignKey(null, 'craftnet_payout_errors', ['payoutId'], 'craftnet_payouts', ['id'], 'CASCADE');
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropTable('craftnet_payout_errors');
        $this->dropTable('craftnet_payout_items');
        $this->dropTable('craftnet_payouts');
    }
}
