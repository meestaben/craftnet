<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;

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
        $this->addColumn('craftnet_packageversions', 'date', $this->dateTime());
        $this->addColumn('craftnet_packageversions', 'critical', $this->boolean()->notNull()->defaultValue(false));
        $this->addColumn('craftnet_packageversions', 'notes', $this->text());
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropColumn('craftnet_packageversions', 'date');
        $this->dropColumn('craftnet_packageversions', 'critical');
        $this->dropColumn('craftnet_packageversions', 'notes');
    }
}
