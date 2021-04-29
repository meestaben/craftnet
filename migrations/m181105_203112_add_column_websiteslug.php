<?php

namespace craft\contentmigrations;

use craft\db\Migration;
use craftnet\db\Table;

/**
 * m181105_203112_add_column_websiteslug migration.
 */
class m181105_203112_add_column_websiteslug extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn(Table::PARTNERS, 'websiteSlug', $this->string());
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropColumn(Table::PARTNERS, 'websiteSlug');

        return true;
    }
}
