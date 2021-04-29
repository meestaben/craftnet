<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\Console;
use craft\helpers\Json;
use craftnet\cms\CmsLicense;
use craftnet\db\Table;
use craftnet\Module;

/**
 * m180912_040313_reactivate_all_cmslicenses migration.
 */
class m180912_040313_reactivate_all_cmslicenses extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $userIdsByEmail = (new Query())
            ->select(['lower(email)', 'id'])
            ->from(['users'])
            ->pairs();

        $query = (new Query())
            ->select(['key', 'data'])
            ->from([Table::INACTIVECMSLICENSES])
            ->limit(1000);

        $cmsLicenseManager = Module::getInstance()->getCmsLicenseManager();

        do {
            $results = $query->all();
            foreach ($results as $result) {
                Console::stdout('    > Reactivating ' . substr($result['key'], 0, 10) . ' ... ');

                $data = Json::decode($result['data']);
                $data['editionHandle'] = 'solo';
                unset($data['edition'], $data['ownerId']);
                $license = new CmsLicense($data);
                $license->ownerId = $userIdsByEmail[mb_strtolower($data['email'])] ?? null;
                $cmsLicenseManager->saveLicense($license, false);

                Console::stdout('deleting inactive row ... ');

                Craft::$app->getDb()->createCommand()
                    ->delete(Table::INACTIVECMSLICENSES, [
                        'key' => $result['key']
                    ])
                    ->execute();

                Console::stdout('adding note ... ');

                $note = "created by {$license->email}";
                if ($license->domain) {
                    $note .= " for domain {$license->domain}";
                }
                $cmsLicenseManager->addHistory($license->id, $note, $data['dateCreated']);

                Console::output(Console::ansiFormat('done', [Console::FG_GREEN]));
            }
        } while (!empty($results));

        $this->dropTable(Table::INACTIVECMSLICENSES);
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180912_040313_reactivate_all_cmslicenses cannot be reverted.\n";
        return false;
    }
}
