<?php

namespace craft\contentmigrations;

use craft\db\Migration;
use craftnet\db\Table;

/**
 * m180323_201227_ledger_tweaks migration.
 */
class m180323_201227_ledger_tweaks extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn(Table::DEVELOPERLEDGER, 'type', $this->string()->null());
        $this->addColumn(Table::DEVELOPERLEDGER, 'country', $this->char(2)->null());
        $this->addColumn(Table::DEVELOPERLEDGER, 'isEuMember', $this->boolean()->null());
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180323_201227_ledger_tweaks cannot be reverted.\n";
        return false;
    }
}
