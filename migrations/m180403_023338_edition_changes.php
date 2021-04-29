<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craftnet\cms\CmsEdition;
use craftnet\db\Table;
use craftnet\Module;

/**
 * m180403_023338_edition_changes migration.
 */
class m180403_023338_edition_changes extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $elementsService = Craft::$app->getElements();
        $licenseManager = Module::getInstance()->getCmsLicenseManager();

        /** @var CmsEdition[] $editions */
        $editions = CmsEdition::find()
            ->indexBy('handle')
            ->all();

        echo '    > renaming Personal edition to Solo ...';
        $editions['personal']->name = 'Solo';
        $editions['personal']->handle = 'solo';
        $elementsService->saveElement($editions['personal']);
        echo "done\n";

        echo "    > updating Solo licenses' edition handles ...";
        Craft::$app->getDb()->createCommand()
            ->update(Table::CMSLICENSES, [
                'editionHandle' => 'solo'
            ], [
                'editionId' => $editions['personal']->id
            ], [], false)
            ->execute();
        echo "done\n";

        $clientLicenseQuery = (new Query())
            ->select(['id'])
            ->from([Table::CMSLICENSES])
            ->where(['editionId' => $editions['client']->id]);
        echo "    > upgrading {$clientLicenseQuery->count()} Client licenses ...\n";

        foreach ($clientLicenseQuery->each() as $result) {
            $license = $licenseManager->getLicenseById($result['id']);
            echo "        > updating {$license->getShortKey()} ...";
            $license->editionId = $editions['pro']->id;
            $license->editionHandle = $editions['pro']->handle;
            $licenseManager->saveLicense($license, false);
            $licenseManager->addHistory($license->id, 'complimentary upgrade to Pro edition');
            echo "done\n";
        }

        echo "    > All licenses upgraded\n";

        echo '    > deleting Client edition ...';
        $elementsService->deleteElement($editions['client']);
        echo "done\n";
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180403_023338_edition_changes cannot be reverted.\n";
        return false;
    }
}
