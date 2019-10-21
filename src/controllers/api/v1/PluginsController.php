<?php

namespace craftnet\controllers\api\v1;

use Craft;
use craftnet\controllers\api\BaseApiController;
use craftnet\plugins\Plugin;
use yii\web\Response;

/**
 * Class PluginsController
 */
class PluginsController extends BaseApiController
{
    // Public Methods
    // =========================================================================

    /**
     * Handles /v1/plugins requests.
     *
     * @return Response
     * @throws \craftnet\errors\MissingTokenException
     * @throws \yii\base\InvalidConfigException
     */
    public function actionIndex(): Response
    {
        $pluginQuery = $this->_getPluginQuery();

        $ids = Craft::$app->getRequest()->getParam('ids');
        $ids = explode(',', $ids);

        if ($ids) {
            $pluginQuery->andWhere(['craftnet_plugins.id' => $ids]);
        }

        $plugins = $pluginQuery->all();

        $data = $this->_transformPlugins($plugins);

        return $this->asJson($data);
    }

    // Private Methods
    // =========================================================================

    /**
     * @return \craft\elements\db\ElementQueryInterface|\craftnet\plugins\PluginQuery
     */
    private function _getPluginQuery()
    {
        return Plugin::find()
            ->withLatestReleaseInfo(true, $this->cmsVersion)
            ->with(['developer', 'categories', 'icon'])
            ->indexBy('id');
    }

    /**
     * @param array $plugins
     * @return array
     * @throws \craftnet\errors\MissingTokenException
     * @throws \yii\base\InvalidConfigException
     */
    private function _transformPlugins(array $plugins): array
    {
        $ret = [];

        foreach ($plugins as $plugin) {
            $ret[] = $this->transformPlugin($plugin, false);
        }

        return $ret;
    }
}
