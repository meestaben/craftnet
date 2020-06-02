<?php

namespace craftnet\controllers\api\v1;

use Craft;
use craft\helpers\ArrayHelper;
use craftnet\controllers\api\BaseApiController;
use craftnet\plugins\Plugin;
use yii\helpers\Inflector;
use yii\web\Response;

/**
 * Class PluginController
 */
class AvailablePluginsController extends BaseApiController
{
    // Public Methods
    // =========================================================================

    /**
     * Handles /v1/available-plugins requests.
     *
     * @return Response
     */
    public function actionIndex(): Response
    {
        $this->requirePostRequest();
        $clientInfo = $this->getPayload();
        $craft2Plugins = require(Craft::getAlias('@config/craft2-plugins.php'));

        // Start with any info we know from craft2-plugins.php, and get a list of
        // all the plugin handles we should query for
        $newHandles = [];
        $oldHandlesByNew = [];
        $res = [];

        if (isset($clientInfo->plugins)) {
            foreach ($clientInfo->plugins as $oldHandle) {
                if (isset($craft2Plugins[$oldHandle]['handle'])) {
                    $newHandle = ArrayHelper::remove($craft2Plugins[$oldHandle], 'handle');
                } else {
                    /** @noinspection PhpUndefinedClassInspection */
                    $newHandle = Inflector::camel2id($oldHandle);
                }

                $resInfo = [
                    'statusColor' => '',
                    'status' => 'Not available yet',
                ];

                if (!empty($craft2Plugins[$oldHandle])) {
                    $resInfo = array_merge($resInfo, $craft2Plugins[$oldHandle]);
                }

                if ($newHandle !== false) {
                    $newHandles[] = $newHandle;
                    $oldHandlesByNew[$newHandle] = $oldHandle;
                }

                $res[$oldHandle] = $resInfo;
            }
        }

        // Include the volume type plugin handles
        if (isset($clientInfo->assetSourceTypes)) {
            if (in_array('GoogleCloud', $clientInfo->assetSourceTypes)) {
                $newHandles[] = 'google-cloud';
            }
            if (in_array('Rackspace', $clientInfo->assetSourceTypes)) {
                $newHandles[] = 'rackspace';
            }
            if (in_array('S3', $clientInfo->assetSourceTypes)) {
                $newHandles[] = 'aws-s3';
            }
        }

        // Find the plugins
        $plugins = Plugin::find()
            ->handle($newHandles)
            ->with(['icon', 'developer', 'editions'])
            ->status(null)
            ->all();

        foreach ($plugins as $plugin) {
            $oldHandle = $oldHandlesByNew[$plugin->handle] ?? $plugin->handle;
            $icon = $plugin->getIcon();
            $developer = $plugin->getDeveloper();
            $statusColor = $plugin->enabled ? 'green' : 'red';
            $status = $plugin->enabled ? 'Available' : 'Coming soon';

            $res[$oldHandle] = [
                'statusColor' => $statusColor,
                'status' => "[$status]({$plugin->repository})",
                'iconUrl' => $icon ? $icon->getUrl() . '?' . $icon->dateModified->getTimestamp() : null,
                'name' => strip_tags($plugin->name),
                'price' => $plugin->getEditions()[0]->price,
                'currency' => 'USD',
                'developerName' => strip_tags($developer->getDeveloperName()),
                'developerUrl' => $developer->developerUrl,
            ];
        }

        // Log the plugin hits
        if (isset($clientInfo->plugins)) {
            $db = Craft::$app->getDb();

            foreach ($clientInfo->plugins as $oldHandle) {
                $available = in_array($res[$oldHandle]['statusColor'], ['green', 'orange'], true);
                $db->createCommand(
                    'INSERT INTO [[craftnet_craft2pluginhits]] as [[h]] ([[plugin]], [[hits]], [[available]]) VALUES (:plugin, 1, :available) ' .
                    'ON CONFLICT ([[plugin]]) DO UPDATE SET [[hits]] = [[h.hits]] + 1, [[available]] = :available',
                    [
                        'plugin' => $oldHandle,
                        'available' => $available,
                    ]
                )->execute();
            }
        }

        return $this->asJson($res);
    }
}
