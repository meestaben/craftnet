<?php

namespace craftnet\console\controllers;

use Craft;
use craft\helpers\Db;
use craftnet\Module;
use craftnet\plugins\Plugin;
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
     * Displays info about all plugins
     *
     * @return int
     */
    public function actionInfo(): int
    {
        $formatter = Craft::$app->getFormatter();
        $total = $formatter->asDecimal(Plugin::find()->count(), 0);
        $abandoned = $formatter->asDecimal(Plugin::find()->status(Plugin::STATUS_ABANDONED)->count(), 0);
        $pending = $formatter->asDecimal(Plugin::find()->status(Plugin::STATUS_PENDING)->count(), 0);

        $output = <<<OUTPUT
Total approved: $total
Abandoned:      $abandoned
Pending:        $pending

OUTPUT;

        $this->stdout($output);
        return ExitCode::OK;
    }

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
