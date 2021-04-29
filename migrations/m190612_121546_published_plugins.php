<?php

namespace craft\contentmigrations;

use craft\db\Migration;
use craftnet\db\Table;

/**
 * m190612_121546_published_plugins migration.
 */
class m190612_121546_published_plugins extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn(Table::PLUGINS, 'published', $this->boolean()->defaultValue(false)->notNull());
        $sql = <<<SQL
update craftnet_plugins p
SET published = true
from elements e
where e.id = p.id
and e.enabled = true
and exists(
    select *
    from craftnet_packageversions v
    where v."packageId" = p."packageId"
    and v.valid = true
)
SQL;
        $this->execute($sql);
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m190612_121546_published_plugins cannot be reverted.\n";
        return false;
    }
}
