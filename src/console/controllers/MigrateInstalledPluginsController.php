<?php

namespace craftnet\console\controllers;

use Craft;
use craft\db\Query;
use craft\helpers\Console;
use craftnet\errors\LicenseNotFoundException;
use craftnet\Module;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Migrates installed plugin data
 *
 * @property Module $module
 */
class MigrateInstalledPluginsController extends Controller
{
    public function actionIndex(): int
    {
        $pluginsByLicenseKey = [];

        $this->stdout('Fetching old data ... ');

        $query = (new Query())
            ->select(['craftLicenseKey', 'pluginId', 'lastActivity'])
            ->from(['craftnet_installedplugins'])
            ->orderBy(['lastActivity' => SORT_ASC]);

        foreach ($query->each() as $row) {
            $pluginsByLicenseKey[$row['craftLicenseKey']][$row['pluginId']] = $row['lastActivity'];
        }

        $this->stdout('done' . PHP_EOL, Console::FG_GREEN);
        $this->stdout('Adding new data ...' . PHP_EOL);
        $cmsLicenseManager = $this->module->getCmsLicenseManager();
        $totalLicenses = count($pluginsByLicenseKey);
        $db = Craft::$app->getDb();
        $i = 0;

        foreach ($pluginsByLicenseKey as $key => $plugins) {
            $i++;
            $shortKey = substr($key, 0, 10);
            $this->stdout("   - [$i/$totalLicenses] $shortKey (" . count($plugins) . ' plugins) ... ');
            try {
                $license = $cmsLicenseManager->getLicenseByKey($key);
            } catch (LicenseNotFoundException $e) {
                $this->stdout('license not found' . PHP_EOL, Console::FG_RED);
                continue;
            }
            $data = [];
            foreach ($plugins as $pluginId => $timestamp) {
                $data[] = [$license->id, $pluginId, $timestamp];
            }
            $db->createCommand()
                ->batchInsert('craftnet_cmslicense_plugins', ['licenseId', 'pluginId', 'timestamp'], $data, false)
                ->execute();
            $this->stdout('done' . PHP_EOL, Console::FG_GREEN);
        }

        return ExitCode::OK;
    }
}
