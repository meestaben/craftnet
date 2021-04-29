<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craftnet\db\Table;

/**
 * m180831_214018_change_parter_msa_column_type migration.
 */
class m180831_214018_change_partner_msa_to_asset extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->createMsaAssetIdColumn();
        $this->createPartnerDocumentsVolume();

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180831_214018_change_parter_msa_column_type cannot be undone but down is allowed.\n";
        return true;
    }

    /**
     * Drops the old `msaLink` column and adds a `msaAssetId` column
     */
    protected function createMsaAssetIdColumn()
    {
        $schema = $this->db->getTableSchema(Table::PARTNERS);

        // If this migration has already run then skip it
        if ($schema->getColumn('msaAssetId')) {
            echo "msaAssetId column already exists\n";
            return true;
        }

        $this->addColumn(Table::PARTNERS, 'msaAssetId', $this->integer()->after('msaLink'));
        $this->dropColumn(Table::PARTNERS, 'msaLink');
    }

    /**
     * Creates the Partner Documents volume for things like MSA PDFs and such
     */
    protected function createPartnerDocumentsVolume()
    {
        $volumes = Craft::$app->getVolumes();
        $exisitngVolume = $volumes->getVolumeByHandle('partnerDocuments');
        $id = $exisitngVolume ? $exisitngVolume->id : null;

        if ($id) {
            echo "Updating existing Partner Documents volume\n";
        }

        $volume = $volumes->createVolume([
            'id' => $id,
            'type' => 'craft\awss3\Volume',
            'name' => 'Partner Documents',
            'handle' => 'partnerDocuments',
            'hasUrls' => true,
            'url' => 'http://partners.craftcms.s3.amazonaws.com/',
            'settings' => [
                'keyId' => getenv('AWS_ACCESS_KEY_ID'),
                'secret' => getenv('AWS_SECRET_ACCESS_KEY'),
                'bucket' => 'partners.craftcms',
                'region' => 'us-east-1',
                'subfolder' => 'documents',
                'expires' => '',
                'cfDistributionId' => '',
            ]
        ]);

        $success = $volumes->saveVolume($volume);

        if (!$success) {
            echo "Can't save volume: Partner Documents\n";
        }
    }
}
