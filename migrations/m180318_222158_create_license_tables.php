<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craftnet\cms\CmsEdition;
use craftnet\cms\CmsRenewal;
use craftnet\db\Table;
use craft\db\Table as CraftTable;
use craft\commerce\db\Table as CommerceTable;
use craftnet\plugins\PluginEdition;
use craftnet\plugins\PluginRenewal;
use yii\console\Exception;

/**
 * m180318_222158_create_license_tables migration.
 */
class m180318_222158_create_license_tables extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->_createCmsTables();
        $this->_createPluginTables();
        $this->_createCmsEditions();
        $this->_createPluginEditions();
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180318_222158_create_license_tables cannot be reverted.\n";
        return false;
    }

    private function _createCmsTables()
    {
        // fix plugins table ---------------------------------------------------

        $this->alterColumn(Table::PLUGINS, 'price', $this->decimal(14, 4)->unsigned());
        $this->alterColumn(Table::PLUGINS, 'renewalPrice', $this->decimal(14, 4)->unsigned());

        // cmseditions ---------------------------------------------------------

        $this->createTable(Table::CMSEDITIONS, [
            'id' => $this->integer()->notNull(),
            'name' => $this->string()->notNull(),
            'handle' => $this->string()->notNull(),
            'price' => $this->decimal(14, 4)->unsigned()->notNull(),
            'renewalPrice' => $this->decimal(14, 4)->unsigned()->notNull(),
            'PRIMARY KEY([[id]])',
        ]);

        $this->createIndex(null, Table::CMSEDITIONS, ['name'], true);
        $this->createIndex(null, Table::CMSEDITIONS, ['handle'], true);
        $this->createIndex(null, Table::CMSEDITIONS, ['price']);

        $this->addForeignKey(null, Table::CMSEDITIONS, ['id'], CraftTable::ELEMENTS, ['id'], 'CASCADE');

        // cmsrenewals ---------------------------------------------------------

        $this->createTable(Table::CMSRENEWALS, [
            'id' => $this->integer()->notNull(),
            'editionId' => $this->integer()->notNull(),
            'price' => $this->decimal(14, 4)->unsigned()->notNull(),
            'PRIMARY KEY([[id]])',
        ]);

        $this->addForeignKey(null, Table::CMSRENEWALS, ['id'], CraftTable::ELEMENTS, ['id'], 'CASCADE');
        $this->addForeignKey(null, Table::CMSRENEWALS, ['editionId'], Table::CMSEDITIONS, ['id'], 'CASCADE');

        // cmslicenses ---------------------------------------------------------

        $this->createTable(Table::CMSLICENSES, [
            'id' => $this->primaryKey(),
            'editionId' => $this->integer()->notNull(),
            'ownerId' => $this->integer()->null(),
            'expirable' => $this->boolean()->notNull(),
            'expired' => $this->boolean()->notNull(),
            'autoRenew' => $this->boolean()->notNull(),
            'edition' => $this->string()->notNull(),
            'email' => $this->string()->notNull(),
            'domain' => $this->string()->null(),
            'key' => $this->string(250)->notNull(),
            'notes' => $this->text()->null(),
            'privateNotes' => $this->text()->null(),
            'lastEdition' => $this->smallInteger()->null(),
            'lastVersion' => $this->string()->null(),
            'lastAllowedVersion' => $this->string()->null(),
            'lastActivityOn' => $this->dateTime()->null(),
            'lastRenewedOn' => $this->dateTime()->null(),
            'expiresOn' => $this->dateTime()->null(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createIndex(null, Table::CMSLICENSES, ['key'], true);
        $this->createIndex($this->db->getIndexName(Table::CMSLICENSES, ['ownerId', 'email']), Table::CMSLICENSES, ['ownerId', 'lower([[email]])']);

        $this->addForeignKey(null, Table::CMSLICENSES, ['editionId'], Table::CMSEDITIONS, ['id']);
        $this->addForeignKey(null, Table::CMSLICENSES, ['ownerId'], CraftTable::USERS, ['id'], 'SET NULL');

        // cmslicensehistory ---------------------------------------------------

        $this->createTable(Table::CMSLICENSEHISTORY, [
            'id' => $this->bigPrimaryKey(),
            'licenseId' => $this->integer(),
            'note' => $this->string()->notNull(),
            'timestamp' => $this->dateTime()->notNull(),
        ]);

        $this->addForeignKey(null, Table::CMSLICENSEHISTORY, ['licenseId'], Table::CMSLICENSES, ['id'], 'CASCADE');

        // cmslicenses_lineitems -----------------------------------------------

        $this->createTable(Table::CMSLICENSES_LINEITEMS, [
            'licenseId' => $this->integer()->notNull(),
            'lineItemId' => $this->integer()->notNull(),
            'PRIMARY KEY([[licenseId]], [[lineItemId]])',
        ]);

        $this->addForeignKey(null, Table::CMSLICENSES_LINEITEMS, ['licenseId'], Table::CMSLICENSES, ['id'], 'CASCADE');
        $this->addForeignKey(null, Table::CMSLICENSES_LINEITEMS, ['lineItemId'], CommerceTable::LINEITEMS, ['id'], 'CASCADE');

        // inactivecmslicenses -------------------------------------------------

        $this->createTable(Table::INACTIVECMSLICENSES, [
            'key' => $this->string(250)->notNull(),
            'data' => $this->text(),
            'PRIMARY KEY([[key]])',
        ]);
    }

    private function _createPluginTables()
    {
        // plugineditions ------------------------------------------------------

        $this->createTable(Table::PLUGINEDITIONS, [
            'id' => $this->integer()->notNull(),
            'pluginId' => $this->integer()->notNull(),
            'name' => $this->string()->notNull(),
            'handle' => $this->string()->notNull(),
            'price' => $this->decimal(14, 4)->unsigned()->notNull(),
            'renewalPrice' => $this->decimal(14, 4)->unsigned()->notNull(),
            'PRIMARY KEY([[id]])',
        ]);

        $this->createIndex(null, Table::PLUGINEDITIONS, ['pluginId', 'name'], true);
        $this->createIndex(null, Table::PLUGINEDITIONS, ['pluginId', 'handle'], true);
        $this->createIndex(null, Table::PLUGINEDITIONS, ['pluginId', 'price']);

        $this->addForeignKey(null, Table::PLUGINEDITIONS, ['id'], CraftTable::ELEMENTS, ['id'], 'CASCADE');
        $this->addForeignKey(null, Table::PLUGINEDITIONS, ['pluginId'], Table::PLUGINS, ['id'], 'CASCADE');

        // cmsrenewals ---------------------------------------------------------

        $this->createTable(Table::PLUGINRENEWALS, [
            'id' => $this->integer()->notNull(),
            'pluginId' => $this->integer()->notNull(),
            'editionId' => $this->integer()->notNull(),
            'price' => $this->decimal(14, 4)->unsigned()->notNull(),
            'PRIMARY KEY([[id]])',
        ]);

        $this->addForeignKey(null, Table::PLUGINRENEWALS, ['id'], CraftTable::ELEMENTS, ['id'], 'CASCADE');
        $this->addForeignKey(null, Table::PLUGINRENEWALS, ['pluginId'], Table::PLUGINS, ['id'], 'CASCADE');
        $this->addForeignKey(null, Table::PLUGINRENEWALS, ['editionId'], Table::PLUGINEDITIONS, ['id'], 'CASCADE');

        // pluginlicenses ------------------------------------------------------

        $this->createTable(Table::PLUGINLICENSES, [
            'id' => $this->primaryKey(),
            'pluginId' => $this->integer()->notNull(),
            'editionId' => $this->integer()->notNull(),
            'cmsLicenseId' => $this->integer()->null(),
            'ownerId' => $this->integer()->null(),
            'expirable' => $this->boolean()->notNull(),
            'expired' => $this->boolean()->notNull(),
            'autoRenew' => $this->boolean()->notNull(),
            'plugin' => $this->string()->notNull(),
            'edition' => $this->string()->notNull(),
            'email' => $this->string()->notNull(),
            'key' => $this->string(24)->notNull(),
            'notes' => $this->text()->null(),
            'privateNotes' => $this->text()->null(),
            'lastVersion' => $this->string()->null(),
            'lastAllowedVersion' => $this->string()->null(),
            'lastActivityOn' => $this->dateTime()->null(),
            'lastRenewedOn' => $this->dateTime()->null(),
            'expiresOn' => $this->dateTime()->null(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createIndex(null, Table::PLUGINLICENSES, ['key'], true);
        $this->createIndex($this->db->getIndexName(Table::PLUGINLICENSES, ['ownerId', 'email']), Table::PLUGINLICENSES, ['ownerId', 'lower([[email]])']);

        $this->addForeignKey(null, Table::PLUGINLICENSES, ['pluginId'], Table::PLUGINS, ['id']);
        $this->addForeignKey(null, Table::PLUGINLICENSES, ['editionId'], Table::PLUGINEDITIONS, ['id']);
        $this->addForeignKey(null, Table::PLUGINLICENSES, ['cmsLicenseId'], Table::CMSLICENSES, ['id'], 'SET NULL');
        $this->addForeignKey(null, Table::PLUGINLICENSES, ['ownerId'], CraftTable::USERS, ['id'], 'SET NULL');

        // pluginlicensehistory ------------------------------------------------

        $this->createTable(Table::PLUGINLICENSEHISTORY, [
            'id' => $this->bigPrimaryKey(),
            'licenseId' => $this->integer(),
            'note' => $this->string()->notNull(),
            'timestamp' => $this->dateTime()->notNull(),
        ]);

        $this->addForeignKey(null, Table::PLUGINLICENSEHISTORY, ['licenseId'], Table::PLUGINLICENSES, ['id'], 'CASCADE');

        // pluginlicenses_lineitems --------------------------------------------

        $this->createTable(Table::PLUGINLICENSES_LINEITEMS, [
            'licenseId' => $this->integer()->notNull(),
            'lineItemId' => $this->integer()->notNull(),
            'PRIMARY KEY([[licenseId]], [[lineItemId]])',
        ]);

        $this->addForeignKey(null, Table::PLUGINLICENSES_LINEITEMS, ['licenseId'], Table::PLUGINLICENSES, ['id'], 'CASCADE');
        $this->addForeignKey(null, Table::PLUGINLICENSES_LINEITEMS, ['lineItemId'], CommerceTable::LINEITEMS, ['id'], 'CASCADE');
    }

    private function _createCmsEditions()
    {
        $elementsService = Craft::$app->getElements();

        /** @var CmsEdition[] $editions */
        $editions = [
            new CmsEdition([
                'name' => 'Personal',
                'handle' => 'personal',
                'price' => 0,
                'renewalPrice' => 0,
            ]),
            new CmsEdition([
                'name' => 'Client',
                'handle' => 'client',
                'price' => 199,
                'renewalPrice' => 39,
            ]),
            new CmsEdition([
                'name' => 'Pro',
                'handle' => 'pro',
                'price' => 299,
                'renewalPrice' => 59,
            ]),
        ];

        foreach ($editions as $edition) {
            // Save the edition
            if (!$elementsService->saveElement($edition)) {
                throw new Exception("Couldn't save Craft {$edition->name} edition: " . implode(', ', $edition->getFirstErrors()));
            }

            // Save the renewal
            $renewal = new CmsRenewal([
                'editionId' => $edition->id,
                'price' => $edition->renewalPrice,
            ]);

            if (!$elementsService->saveElement($renewal)) {
                throw new Exception("Couldn't save Craft {$edition->name} renewal: " . implode(', ', $renewal->getFirstErrors()));
            }
        }
    }

    private function _createPluginEditions()
    {
        $elementsService = Craft::$app->getElements();

        $plugins = (new Query())
            ->select(['id', 'name', 'price', 'renewalPrice'])
            ->from(Table::PLUGINS)
            ->all();

        foreach ($plugins as $plugin) {
            // Save the edition
            $edition = new PluginEdition([
                'pluginId' => $plugin['id'],
                'name' => 'Standard',
                'handle' => 'standard',
                'price' => $plugin['price'] ?? 0,
                'renewalPrice' => $plugin['renewalPrice'] ?? 0,
            ]);

            if (!$elementsService->saveElement($edition)) {
                throw new Exception("Couldn't save {$plugin['name']} edition: " . implode(', ', $edition->getFirstErrors()));
            }

            // Save the renewal
            $renewal = new PluginRenewal([
                'pluginId' => $plugin['id'],
                'editionId' => $edition->id,
                'price' => $edition->renewalPrice,
            ]);

            if (!$elementsService->saveElement($renewal)) {
                throw new Exception("Couldn't save {$plugin['name']} renewal: " . implode(', ', $renewal->getFirstErrors()));
            }
        }
    }
}
