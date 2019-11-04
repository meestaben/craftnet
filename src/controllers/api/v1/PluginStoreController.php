<?php

namespace craftnet\controllers\api\v1;

use Craft;
use craft\db\Query;
use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\db\EntryQuery;
use craft\elements\Entry;
use craft\helpers\Json;
use craftnet\controllers\api\BaseApiController;
use craftnet\helpers\Cache;
use craftnet\Module;
use craftnet\plugins\Plugin;
use craftnet\plugins\PluginQuery;
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
        $cacheKey = __METHOD__ . '-' . $this->cmsVersion;
        $pluginStoreData = Cache::get($cacheKey);

        if (!$pluginStoreData) {
            $plugins = Plugin::find()
                ->withLatestReleaseInfo(true, $this->cmsVersion)
                ->with(['developer', 'categories', 'icon'])
                ->indexBy('id')
                ->all();

            $pluginStoreData = [
                'categories' => $this->_categories(),
                'featuredPlugins' => $this->_featuredPlugins(true),
                'plugins' => $this->_plugins($plugins),
                'expiryDateOptions' => $this->_expiryDateOptions(),
            ];

            Cache::set($cacheKey, $pluginStoreData);
        }

        return $this->asJson($pluginStoreData);
    }

    /**
     * Handles /v1/plugin-store/core-data requests.
     *
     * @return Response
     */
    public function actionCoreData(): Response
    {
        $pluginStoreData = [
            'categories' => $this->_categories(),
            'sortOptions' => [
                'dateUpdated' => 'desc',
                'name' => 'asc',
                'activeInstalls' => 'desc',
            ],
            'expiryDateOptions' => $this->_expiryDateOptions(),
        ];

        return $this->asJson($pluginStoreData);
    }

    /**
     * Handles /v1/plugin-store/featured-section/<handle> requests.
     *
     * @param $handle
     * @return Response
     */
    public function actionFeaturedSection($handle): Response
    {
        $cacheKey = __METHOD__ . $handle;
        $data = Cache::get($cacheKey);

        if (!$data) {
            $featuredSectionEntry = $this->featuredSectionQuery()
                ->slug($handle)
                ->one();

            $data = $this->transformFeaturedSection($featuredSectionEntry);

            Cache::set($cacheKey, $data);
        }

        return $this->asJson($data);
    }

    /**
     * Handles /v1/plugin-store/featured-sections requests.
     *
     * @return Response
     */
    public function actionFeaturedSections(): Response
    {
        $cacheKey = __METHOD__;
        $featuredSections = Cache::get($cacheKey);

        if (!$featuredSections) {
            $featuredSections = [];

            foreach ($this->featuredSectionQuery()->all() as $featuredSectionEntry) {
                $data = $this->getFeaturedSectionPlugins($featuredSectionEntry, 8);
                $plugins = $data['plugins'];

                $featuredSections[] = [
                    'id' => $featuredSectionEntry->id,
                    'slug' => $featuredSectionEntry->slug,
                    'title' => $featuredSectionEntry->title,
                    'limit' => $featuredSectionEntry->limit,
                    'plugins' => $plugins,
                ];
            }

            Cache::set($cacheKey, $featuredSections);
        }

        return $this->asJson($featuredSections);
    }

    /**
     * Handles /v1/plugin-store/plugin/<handle> requests.
     *
     * @param string $handle
     * @return Response
     * @throws \craftnet\errors\MissingTokenException
     * @throws \yii\base\InvalidConfigException
     */
    public function actionPlugin(string $handle): Response
    {
        $cacheKey = __METHOD__ . '-' . $handle;
        $data = Cache::get($cacheKey);

        if (!$data) {
            $plugin = Plugin::find()
                ->handle($handle)
                ->anyStatus()
                ->withLatestReleaseInfo()
                ->one();

            if (!$plugin) {
                return $this->asErrorJson("Couldn't find plugin");
            }

            $data = $this->transformPlugin($plugin, true);
            Cache::set($cacheKey, $data);
        }

        // Add the latest compatible version
        if ($this->cmsVersion) {
            $cmsRelease = Module::getInstance()->getPackageManager()->getRelease('craftcms/cms', $this->cmsVersion);
            if ($cmsRelease) {
                $data['latestCompatibleVersion'] = (new Query)
                    ->select(['v.version'])
                    ->from(['v' => 'craftnet_packageversions'])
                    ->innerJoin(['craftnet_pluginversionorder vo'], '[[vo.versionId]] = [[v.id]]')
                    ->where(['v.packageId' => $data['packageId']])
                    ->andWhere([
                        'vo.stableOrder' => (new Query())
                            ->select(['max([[s_vo.stableOrder]])'])
                            ->from(['s_vo' => 'craftnet_pluginversionorder'])
                            ->innerJoin(['craftnet_packageversions s_v'], '[[s_v.id]] = [[s_vo.versionId]]')
                            ->innerJoin(['craftnet_pluginversioncompat s_vc'], '[[s_vc.pluginVersionId]] = [[s_v.id]]')
                            ->where(['s_v.packageId' => $data['packageId']])
                            ->andWhere(['s_vc.cmsVersionId' => $cmsRelease->id])
                            ->groupBy(['s_v.packageId'])
                    ])
                    ->scalar();
            } else {
                $data['latestCompatibleVersion'] = null;
            }
        }

        return $this->asJson($data);
    }

    /**
     * Handles /v1/plugin-store/plugins-by-category/<categoryId:\d+> requests.
     *
     * @param $categoryId
     * @return Response
     * @throws \craftnet\errors\MissingTokenException
     * @throws \yii\base\InvalidConfigException
     */
    public function actionPluginsByCategory($categoryId): Response
    {
        $cacheKey = __METHOD__ . $categoryId;
        $data = $this->getPluginIndexCache($cacheKey);

        if (!$data) {
            $query = $this->getPluginIndexQuery()
                ->categoryId($categoryId);

            $data = $this->getPluginIndexResponse($query);

            $this->setPluginIndexCache($cacheKey, $data);
        }

        return $this->asJson($data);
    }

    /**
     * Handles /v1/plugin-store/plugins-by-developer/<developerId:\d+> requests.
     *
     * @param $developerId
     * @return Response
     * @throws \craftnet\errors\MissingTokenException
     * @throws \yii\base\InvalidConfigException
     */
    public function actionPluginsByDeveloper($developerId): Response
    {
        $cacheKey = __METHOD__ . $developerId;
        $data = $this->getPluginIndexCache($cacheKey);

        if (!$data) {
            $query = $this->getPluginIndexQuery()
                ->developerId($developerId);

            $data = $this->getPluginIndexResponse($query);

            $this->setPluginIndexCache($cacheKey, $data);
        }

        return $this->asJson($data);
    }

    /**
     * Handles /v1/plugin-store/plugins-by-featured-section/<handle> requests.
     *
     * @param $handle
     * @return Response
     */
    public function actionPluginsByFeaturedSection($handle): Response
    {
        $featuredSectionEntry = $this->featuredSectionQuery()
            ->slug($handle)
            ->one();

        $plugins = $this->getFeaturedSectionPlugins($featuredSectionEntry);

        return $this->asJson($plugins);
    }

    /**
     * Handles /v1/plugin-store/plugins-by-handles requests.
     *
     * @return Response
     * @throws \craftnet\errors\MissingTokenException
     * @throws \yii\base\InvalidConfigException
     */
    public function actionPluginsByHandles(): Response
    {
        $pluginHandles = Craft::$app->getRequest()->getParam('pluginHandles', '');

        $cacheKey = __METHOD__ . $pluginHandles;
        $data = Cache::get($cacheKey);

        if (!$data) {
            $pluginHandles = explode(',', $pluginHandles);

            $plugins = Plugin::find()
                ->withLatestReleaseInfo()
                ->with(['developer', 'categories', 'icon'])
                ->indexBy('id')
                ->andWhere(['craftnet_plugins.handle' => $pluginHandles])
                ->all();

            $data = $this->_transformPlugins($plugins);

            Cache::set($cacheKey,$data);
        }

        return $this->asJson($data);
    }

    /**
     * Handles /v1/plugin-store/search-plugins requests.
     *
     * @return Response
     * @throws \craftnet\errors\MissingTokenException
     * @throws \yii\base\InvalidConfigException
     */
    public function actionSearchPlugins()
    {
        $searchQuery = Craft::$app->getRequest()->getParam('searchQuery', '');

        $cacheKey = __METHOD__ . $searchQuery;
        $data = $this->getPluginIndexCache($cacheKey);

        if (!$data) {
            $query = $this->getPluginIndexQuery()
                ->andWhere([
                    'or',
                    ['like', 'name', $searchQuery . '%', false],
                    ['like', 'packageName', $searchQuery],
                    ['like', 'shortDescription', $searchQuery],
                    ['like', 'description', $searchQuery],
                    ['like', 'craftnet_plugins.keywords', $searchQuery],
                    // ['like', 'developerUrl', $searchQuery],
                    // ['like', 'developerName', $searchQuery],
                ]);

            $data = $this->getPluginIndexResponse($query);

            $this->setPluginIndexCache($cacheKey, $data);
        }

        return $this->asJson($data);
    }

    // Private Methods
    // =========================================================================

    private function getPluginIndexResponse(PluginQuery $query): array
    {
        $totalResults = $query->total();

        $pluginResults = $query->all();
        $plugins = $this->_plugins($pluginResults);

        $params = $this->getPluginIndexParams();
        $perPage = (int)$params['perPage'];

        $total = ceil($totalResults / $perPage);

        $currentPage = (int)$params['page'];

        return [
            'plugins' => $plugins,
            'currentPage' => $currentPage,
            'perPage' => $perPage,
            'total' => $total,
            'totalResults' => $totalResults,
        ];
    }

    /**
     * @param string $key
     * @return mixed|null
     */
    private function getPluginIndexCache(string $key)
    {
        $cacheKey = $this->getPluginIndexCacheKey($key);
        return Cache::get($cacheKey);
    }

    /**
     * @param string $key
     * @param $value
     * @return bool|null
     */
    private function setPluginIndexCache(string $key, $value)
    {
        $cacheKey = $this->getPluginIndexCacheKey($key);
        return Cache::set($cacheKey, $value);
    }

    /**
     * @param string $key
     * @return string
     */
    private function getPluginIndexCacheKey(string $key): string
    {
        $params = $this->getPluginIndexParams();

        $identifiers = [
            $key,
            $params,
        ];

        $string = Json::encode($identifiers);

        return md5($string);
    }

    /**
     * @return array
     */
    private function getPluginIndexParams(): array
    {
        return [
            'perPage' => min(Craft::$app->getRequest()->getParam('perPage', 10), 100),
            'page' => max(Craft::$app->getRequest()->getParam('page', 1), 1),
            'orderBy' => Craft::$app->getRequest()->getParam('orderBy', 'activeInstalls'),
            'direction' => Craft::$app->getRequest()->getParam('direction') === 'asc' ? SORT_ASC : SORT_DESC,
            'searchQuery' => Craft::$app->getRequest()->getParam('searchQuery'),
        ];
    }

    /**
     * @return \craft\db\Query|\craft\elements\db\ElementQueryInterface|PluginQuery|self
     */
    private function getPluginIndexQuery()
    {
        $params = $this->getPluginIndexParams();

        $limit = $params['perPage'];
        $offset = ($params['page'] - 1) * $limit;

        $query = Plugin::find()
            ->withLatestReleaseInfo()
            ->with(['developer', 'categories', 'icon'])
            ->indexBy('id')
            ->orderBy([($params['orderBy'] === 'name' ? $params['orderBy'] = 'LOWER('.$params['orderBy'].')' : $params['orderBy']) => $params['direction']])
            ->offset($offset)
            ->limit($limit);

        return $query;
    }

    /**
     * @return EntryQuery
     */
    private function featuredSectionQuery()
    {
        return Entry::find()
            ->site('craftId')
            ->section('featuredPlugins');
    }

    /**
     * @param $featuredSectionEntry
     * @return array
     */
    private function transformFeaturedSection($featuredSectionEntry): array
    {
        return [
            'id' => $featuredSectionEntry->id,
            'slug' => $featuredSectionEntry->slug,
            'title' => $featuredSectionEntry->title,
            'limit' => $featuredSectionEntry->limit,
        ];
    }

    /**
     * @param $featuredSectionEntry
     * @param null $limit
     * @return array|null
     * @throws \craftnet\errors\MissingTokenException
     * @throws \yii\base\InvalidConfigException
     */
    private function getFeaturedSectionPlugins($featuredSectionEntry, $limit = null)
    {
        $params = $this->getPluginIndexParams();

        $limit = $limit ?? $params['perPage'];
        $offset = ($params['page'] - 1) * $limit;

        $cacheKey = __METHOD__ . $featuredSectionEntry->id . '-' . $limit . '-' . $offset;
        $data = Cache::get($cacheKey);

        if (!$data) {
            $pluginIds = null;

            switch ($featuredSectionEntry->getType()->handle) {
                case 'manual':
                    /** @var PluginQuery $query */
                    $query = $featuredSectionEntry->plugins;
                    $pluginIds = $query
                        ->withLatestReleaseInfo()
                        ->ids();
                    break;
                case 'dynamic':
                    $pluginIds = $this->_dynamicPlugins($featuredSectionEntry->slug, false);
                    break;
                default:
                    $pluginIds = null;
            }

            if (!$pluginIds) {
                return null;
            }

            $query = Plugin::find()
                ->withLatestReleaseInfo()
                ->with(['developer', 'categories', 'icon'])
                ->indexBy('id')
                ->offset($offset)
                ->limit($limit);

            $query->andWhere(['craftnet_plugins.id' => $pluginIds]);

            $data = $this->getPluginIndexResponse($query);

            Cache::set($cacheKey, $data);
        }

        return $data;
    }

    /**
     * @return array
     */
    private function _categories(): array
    {
        $cacheKey = __METHOD__;
        $data = Cache::get($cacheKey);

        if (!$data) {
            $data = [];

            $categories = Category::find()
                ->group('pluginCategories')
                ->with('icon')
                ->all();

            foreach ($categories as $category) {
                /** @var Asset|null $icon */
                $icon = $category->icon[0] ?? null;
                $data[] = [
                    'id' => $category->id,
                    'title' => $category->title,
                    'description' => $category->description,
                    'slug' => $category->slug,
                    'iconUrl' => $icon ? $icon->getUrl() . '?' . $icon->dateModified->getTimestamp() : null,
                ];
            }

            Cache::set($cacheKey, $data);
        }

        return $data;
    }

    /**
     * @param bool $compatibleOnly
     * @return array
     * @throws \yii\base\Exception
     */
    private function _featuredPlugins(bool $compatibleOnly): array
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
                        ->withLatestReleaseInfo(true, $compatibleOnly ? $this->cmsVersion : null)
                        ->ids();
                    break;
                case 'dynamic':
                    $pluginIds = $this->_dynamicPlugins($entry->slug, $compatibleOnly);
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
     * @param bool $compatibleOnly
     * @param string $slug
     * @return int[]
     */
    private function _dynamicPlugins(string $slug, bool $compatibleOnly): array
    {
        switch ($slug) {
            case 'recently-added':
                return $this->_recentlyAddedPlugins($compatibleOnly);
            case 'top-paid':
                return $this->_topPaidPlugins($compatibleOnly);
            default:
                return [];
        }
    }

    /**
     * @param bool $compatibleOnly
     * @return int[]
     */
    private function _recentlyAddedPlugins(bool $compatibleOnly): array
    {
        return Plugin::find()
            ->andWhere(['not', ['craftnet_plugins.dateApproved' => null]])
            ->withLatestReleaseInfo(true, $compatibleOnly ? $this->cmsVersion : null)
            ->orderBy(['craftnet_plugins.dateApproved' => SORT_DESC])
            ->limit(20)
            ->ids();
    }

    /**
     * @param bool $compatibleOnly
     * @return int[]
     */
    private function _topPaidPlugins(bool $compatibleOnly): array
    {
        return Plugin::find()
            ->andWhere(['not', ['craftnet_plugins.dateApproved' => null]])
            ->withLatestReleaseInfo(true, $compatibleOnly ? $this->cmsVersion : null)
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
