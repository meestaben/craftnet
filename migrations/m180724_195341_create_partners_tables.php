<?php

namespace craft\contentmigrations;

use craft\db\Migration;
use craftnet\db\Table;

/**
 * m180724_195341_create_partners_tables migration.
 */
class m180724_195341_create_partners_tables extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->createTable(Table::PARTNERS, [
            'id' => $this->integer()->notNull(),
            'ownerId' => $this->integer()->notNull(),
            'businessName' => $this->string(),
            'primaryContactName' => $this->string(),
            'primaryContactEmail' => $this->string(),
            'primaryContactPhone' => $this->string(),
            'businessSummary' => $this->text(),
            'minimumBudget' => $this->integer(),
            'msaLink' => $this->text(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
            'PRIMARY KEY([[id]])'
        ]);

        // Sizes ---------------------------------------------------------------

        // Holds available options
        $this->createTable(Table::PARTNERSIZES, [
            'id' => $this->primaryKey(),
            'title' => $this->string()->notNull(),
        ]);

        $this->batchInsert(Table::PARTNERSIZES, ['id', 'title'], [
            [1, 'Boutique'],
            [2, 'Agency'],
            [3, 'Large Agency'],
        ], false);

        // Join table
        $this->createTable(Table::PARTNERS_PARTNERSIZES, [
            'partnerId' => $this->integer()->notNull(),
            'partnerSizesId' => $this->integer()->notNull(),
            'PRIMARY KEY([[partnerId]], [[partnerSizesId]])',
        ]);

        $this->addForeignKey('craftnet_partners_partnersizes_partnerId_fk', Table::PARTNERS_PARTNERSIZES, ['partnerId'], Table::PARTNERS, ['id'], 'CASCADE');

        // Capabilities --------------------------------------------------------

        $this->createTable(Table::PARTNERCAPABILITIES, [
            'id' => $this->primaryKey(),
            'title' => $this->string()->notNull(),
        ]);

        $this->batchInsert(Table::PARTNERCAPABILITIES, ['id', 'title'], [
            [1, 'Commerce'],
            [2, 'Full Service'],
            [3, 'Custom Development'],
            [4, 'Contract Work'],
        ], false);

        $this->createTable(Table::PARTNERS_PARTNERCAPABILITIES, [
            'partnerId' => $this->integer()->notNull(),
            'partnercapabilitiesId' => $this->integer()->notNull(),
            'PRIMARY KEY([[partnerId]], [[partnercapabilitiesId]])',
        ]);

        $this->addForeignKey('partners_capabilities_partnerId_fk', Table::PARTNERS_PARTNERCAPABILITIES, ['partnerId'], Table::PARTNERS, ['id'], 'CASCADE');
        $this->addForeignKey('partners_capabilities_partnercapabilitiesId_fk', Table::PARTNERS_PARTNERCAPABILITIES, ['partnercapabilitiesId'], Table::PARTNERCAPABILITIES, ['id'], 'CASCADE');

        // Locations -----------------------------------------------------------

        $this->createTable(Table::PARTNERLOCATIONS, [
            'id' => $this->primaryKey(),
            'partnerId' => $this->integer()->notNull(),
            'title' => $this->string(),
            'addressLine1' => $this->string(),
            'addressLine2' => $this->string(),
            'city' => $this->string(),
            'state' => $this->string(),
            'zip' => $this->string(),
            'country' => $this->string(),
            'phone' => $this->string(),
            'email' => $this->string(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->addForeignKey('craftnet_partnerlocations_partnerId_fk', Table::PARTNERLOCATIONS, ['partnerId'], Table::PARTNERS, ['id'], 'CASCADE');

        // Projects ------------------------------------------------------------

        $this->createTable(Table::PARTNERPROJECTS, [
            'id' => $this->primaryKey(),
            'partnerId' => $this->integer()->notNull(),
            'url' => $this->string(),
            'private' => $this->boolean(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->addForeignKey('craftnet_partnerprojects_partnerId_fk', Table::PARTNERPROJECTS, ['partnerId'], Table::PARTNERS, ['id'], 'CASCADE');

        $this->createTable(Table::PARTNERPROJECTSCREENSHOTS, [
            'id' => $this->primaryKey(),
            'projectId' => $this->integer()->notNull(),
            'assetId' => $this->integer()->notNull(),
            'sortOrder' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->addForeignKey('craftnet_partnerprojectscreenshots_projectId_fk', Table::PARTNERPROJECTSCREENSHOTS, ['projectId'], Table::PARTNERPROJECTS, ['id'], 'CASCADE');
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
//        echo "m180724_195341_create_partners_tables cannot be reverted.\n";
//        return false;

        // TODO: remove droptables when ready
        $this->dropTable(Table::PARTNERS_PARTNERSIZES);
        $this->dropTable(Table::PARTNERSIZES);
        $this->dropTable(Table::PARTNERS_PARTNERCAPABILITIES);
        $this->dropTable(Table::PARTNERCAPABILITIES);
        $this->dropTable(Table::PARTNERLOCATIONS);
        $this->dropTable(Table::PARTNERPROJECTSCREENSHOTS);
        $this->dropTable(Table::PARTNERPROJECTS);
        $this->dropTable(Table::PARTNERS);
    }
}
