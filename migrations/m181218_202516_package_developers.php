<?php

namespace craft\contentmigrations;

use craft\db\Migration;
use craftnet\db\Table;
use craft\db\Table as CraftTable;

/**
 * m181218_202516_package_developers migration.
 */
class m181218_202516_package_developers extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn(Table::PACKAGES, 'developerId', $this->integer());
        $this->addForeignKey(null, Table::PACKAGES, ['developerId'], CraftTable::USERS, ['id'], 'SET NULL');

        // Populate the new developerId column with plugins' developerId's
        $sql = <<<SQL
update craftnet_packages
set "developerId" = craftnet_plugins."developerId"
from craftnet_plugins
where craftnet_plugins."packageId" = craftnet_packages.id
SQL;
        $this->execute($sql);
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m181218_202516_package_developers cannot be reverted.\n";
        return false;
    }
}
