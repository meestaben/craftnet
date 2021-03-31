<?php

namespace craftnet\console\controllers;

use Craft;
use craft\helpers\Db;
use craftnet\Module;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Manages plugins.
 *
 * @property Module $module
 */
class PluginsController extends Controller
{
    /**
     * Updates plugin install counts
     *
     * @return int
     */
    public function actionUpdateInstallCounts(): int
    {
        Craft::$app->getDb()
            ->createCommand('update craftnet_plugins as p set "activeInstalls" = (
    select count(*) from craftnet_cmslicense_plugins as lp
    where lp."pluginId" = p.id
    and lp.timestamp > :date
)', [
                'date' => Db::prepareDateForDb(new \DateTime('1 year ago')),
            ])
            ->execute();

        return ExitCode::OK;
    }
}
