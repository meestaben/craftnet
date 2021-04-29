<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craftnet\db\Table;

/**
 * m201216_172456_release_changelog_info migration.
 */
class m201216_172456_release_changelog_info extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn(Table::PACKAGEVERSIONS, 'date', $this->dateTime());
        $this->addColumn(Table::PACKAGEVERSIONS, 'critical', $this->boolean()->notNull()->defaultValue(false));
        $this->addColumn(Table::PACKAGEVERSIONS, 'notes', $this->text());
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropColumn(Table::PACKAGEVERSIONS, 'date');
        $this->dropColumn(Table::PACKAGEVERSIONS, 'critical');
        $this->dropColumn(Table::PACKAGEVERSIONS, 'notes');
    }
}
