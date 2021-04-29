<?php

namespace craft\contentmigrations;

use craft\db\Migration;
use craftnet\db\Table;

/**
 * m181024_215625_alter_partner_agencysize migration.
 */
class m181024_215625_alter_partner_agencysize extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->alterColumn(Table::PARTNERS, 'agencySize', 'string');
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m181024_215625_alter_partner_agencysize cannot be reverted but going backwards is allowed.\n";
        return true;
    }
}
