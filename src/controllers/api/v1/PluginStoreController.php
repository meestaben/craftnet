<?php

namespace craftnet\controllers\api\v1;

use Craft;
use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\Entry;
use craftnet\controllers\api\BaseApiController;
use craftnet\plugins\Plugin;
use craftnet\plugins\PluginQuery;
use yii\caching\FileDependency;
use yii\web\Response;

/**
 * Class PluginStoreController
 */
class PluginStoreController extends BaseApiController
{
    // Public Methods
    // =========================================================================

    /**
     * Handles /v1/plugin-store requests.
     *
     * @return Response
     * @throws \craftnet\errors\MissingTokenException
     * @throws \yii\base\Exception
     * @throws \yii\base\InvalidConfigException
     */
    public function actionIndex(): Response
    {
        $pluginStoreData = null;

        $craftIdConfig = Craft::$app->getConfig()->getConfigFromFile('craftid');
        $enablePluginStoreCache = $craftIdConfig['enablePluginStoreCache'];
        $cacheKey = 'pluginStoreData--' . $this->cmsVersion;

        if ($enablePluginStoreCache) {
            $pluginStoreData = Craft::$app->getCache()->get($cacheKey);
        }

        if (!$pluginStoreData) {
            $plugins = Plugin::find()
                ->withLatestReleaseInfo(true, $this->cmsVersion)
                ->with(['developer', 'categories', 'icon'])
                ->indexBy('id')
                ->all();

            $pluginStoreData = [
                'categories' => $this->_categories(),
                'featuredPlugins' => $this->_featuredPlugins(),
                'plugins' => $this->_plugins($plugins),
                'expiryDateOptions' => $this->_expiryDateOptions(),
            ];

            if ($enablePluginStoreCache) {
                Craft::$app->getCache()->set($cacheKey, $pluginStoreData, null, new FileDependency([
                    'fileName' => $this->module->getJsonDumper()->composerWebroot . '/packages.json',
                ]));
            }
        }

        return $this->asJson($pluginStoreData);
    }

    /**
     * Handles /v1/plugin-store/meta requests.
     *
     * @return Response
     * @throws \yii\base\Exception
     */
    public function actionMeta(): Response
    {
        return $this->asJson([
            'categories' => $this->_categories(),
            'featuredPlugins' => $this->_featuredPlugins(),
            'expiryDateOptions' => $this->_expiryDateOptions(),
        ]);
    }

    // Private Methods
    // =========================================================================

    private function _categories(): array
    {
        $ret = [];

        $categories = Category::find()
            ->group('pluginCategories')
            ->with('icon')
            ->all();

        foreach ($categories as $category) {
            /** @var Asset|null $icon */
            $icon = $category->icon[0] ?? null;
            $ret[] = [
                'id' => $category->id,
                'title' => $category->title,
                'description' => $category->description,
                'slug' => $category->slug,
                'iconUrl' => $icon ? $icon->getUrl() . '?' . $icon->dateModified->getTimestamp() : null,
            ];
        }

        return $ret;
    }

    /**
     * @return array
     * @throws \yii\base\Exception
     */
    private function _featuredPlugins(): array
    {
        $ret = [];

        $entries = Entry::find()
            ->site('craftId')
            ->section('featuredPlugins')
            ->all();

        foreach ($entries as $entry) {
            switch ($entry->getType()->handle) {
                case 'manual':
                    /** @var PluginQuery $query */
                    $query = $entry->plugins;
                    $pluginIds = $query
                        ->withLatestReleaseInfo(true, $this->cmsVersion)
                        ->ids();
                    break;
                case 'dynamic':
                    $pluginIds = $this->_dynamicPlugins($entry->slug);
                    break;
                default:
                    $pluginIds = null;
            }

            if (!empty($pluginIds)) {
                $ret[] = [
                    'id' => $entry->id,
                    'slug' => $entry->slug,
                    'title' => $entry->title,
                    'plugins' => $pluginIds,
                    'limit' => $entry->limit,
                ];
            }
        }

        return $ret;
    }

    /**
     * @param string $slug
     * @return int[]
     */
    private function _dynamicPlugins(string $slug): array
    {
        switch ($slug) {
            case 'recently-added':
                return $this->_recentlyAddedPlugins();
            case 'top-paid':
                return $this->_topPaidPlugins();
            default:
                return [];
        }
    }

    /**
     * @return int[]
     */
    private function _recentlyAddedPlugins(): array
    {
        return Plugin::find()
            ->andWhere(['not', ['craftnet_plugins.dateApproved' => null]])
            ->withLatestReleaseInfo(true, $this->cmsVersion)
            ->orderBy(['craftnet_plugins.dateApproved' => SORT_DESC])
            ->limit(20)
            ->ids();
    }

    /**
     * @return int[]
     */
    private function _topPaidPlugins(): array
    {
        return Plugin::find()
            ->andWhere(['not', ['craftnet_plugins.dateApproved' => null]])
            ->withLatestReleaseInfo(true, $this->cmsVersion)
            ->withTotalPurchases(true, (new \DateTime())->modify('-1 month'))
            ->andWhere(['not', ['elements.id' => 983]])
            ->orderBy(['totalPurchases' => SORT_DESC])
            ->limit(20)
            ->ids();
    }

    /**
     * @param Plugin[] $plugins
     * @return array
     * @throws \craftnet\errors\MissingTokenException
     * @throws \yii\base\InvalidConfigException
     */
    private function _plugins(array $plugins): array
    {
        $ret = [];

        foreach ($plugins as $plugin) {
            $ret[] = $this->transformPlugin($plugin, false);
        }

        return $ret;
    }

    /**
     * @return array`
     */
    private function _expiryDateOptions(): array
    {
        $dates = [];

        for ($i = 1; $i <= 5; $i++) {
            $date = (new \DateTime('now', new \DateTimeZone('UTC')))
                ->modify("+{$i} years");
            $dates[] = ["{$i}y", $date->format('Y-m-d')];
        }

        return $dates;
    }
}
