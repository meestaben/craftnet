<?php

namespace craft\contentmigrations;

use craft\db\Migration;
use craft\elements\User;
use craftnet\db\Table;
use craft\db\Table as CraftTable;

/**
 * m180318_222228_developer_tables migration.
 */
class m180318_222228_developer_tables extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // developers table
        $this->createTable(Table::DEVELOPERS, [
            'id' => $this->integer()->notNull(),
            'country' => $this->char(2)->null(),
            'balance' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'stripeAccessToken' => $this->text()->null(),
            'stripeAccount' => $this->string()->null(),
            'payPalEmail' => $this->string()->null(),
            'apiToken' => $this->char(60)->null(),
            'PRIMARY KEY([[id]])',
        ]);

        $this->addForeignKey(null, Table::DEVELOPERS, ['id'], CraftTable::USERS, ['id'], 'CASCADE', null);

        // developerledger
        $this->createTable(Table::DEVELOPERLEDGER, [
            'id' => $this->bigPrimaryKey(),
            'developerId' => $this->integer(),
            'note' => $this->string(),
            'credit' => $this->decimal(14, 4)->unsigned()->null(),
            'debit' => $this->decimal(14, 4)->unsigned()->null(),
            'fee' => $this->decimal(14, 4)->unsigned()->null(),
            'balance' => $this->decimal(14, 4)->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
        ]);

        $this->addForeignKey(null, Table::DEVELOPERLEDGER, ['developerId'], Table::DEVELOPERS, ['id'], 'CASCADE', null);

        // add initial rows
        $developerIds = User::find()
            ->status(null)
            ->group('developers')
            ->ids();

        $developerValues = [];

        foreach ($developerIds as $id) {
            $developerValues[] = [$id];
        }

        $this->batchInsert(Table::DEVELOPERS, ['id'], $developerValues, false);
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180318_222228_developer_tables cannot be reverted.\n";
        return false;
    }
}
