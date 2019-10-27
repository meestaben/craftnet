<?php

namespace craftnet\controllers\api\v1;

use Craft;
use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\Entry;
use craft\helpers\Json;
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
        $cacheKey = __METHOD__;
        $pluginStoreData = $this->getCache($cacheKey);

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

            $this->setCache($cacheKey, $pluginStoreData);
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
            'expiryDateOptions' => $this->_expiryDateOptions(),
        ];

        return $this->asJson($pluginStoreData);
    }

    /**
     * Handles /v1/plugin-store/featured-section/<handle:{slug}> requests.
     *
     * @param $handle
     * @return Response
     */
    public function actionFeaturedSection($handle): Response
    {
        $cacheKey = __METHOD__ . $handle;
        $data = $this->getCache($cacheKey);

        if (!$data) {
            $featuredSectionEntry = $this->featuredSectionQuery()
                ->slug($handle)
                ->one();

            $data = $this->transformFeaturedSection($featuredSectionEntry);

            $this->setCache($cacheKey, $data);
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
        $featuredSections = $this->getCache($cacheKey);

        if(!$featuredSections) {
            $featuredSections = [];

            foreach ($this->featuredSectionQuery()->all() as $featuredSectionEntry) {
                $plugins = $this->getFeaturedSectionPlugins($featuredSectionEntry, 8);

                $featuredSections[] = [
                    'id' => $featuredSectionEntry->id,
                    'slug' => $featuredSectionEntry->slug,
                    'title' => $featuredSectionEntry->title,
                    'limit' => $featuredSectionEntry->limit,
                    'plugins' => $plugins,
                ];
            }

            $this->setCache($cacheKey, $featuredSections);
        }

        return $this->asJson($featuredSections);
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

    /**
     * Handles /v1/plugin-store/plugin/<handle:{slug}> requests.
     *
     * @param $handle
     * @return Response
     * @throws \craftnet\errors\MissingTokenException
     * @throws \yii\base\InvalidConfigException
     */
    public function actionPlugin($handle): Response
    {
        $cacheKey = __METHOD__ . $handle;
        $data = $this->getCache($cacheKey);

        if (!$data) {
            $plugin = Plugin::find()
                ->handle($handle)
                ->anyStatus()
                ->withLatestReleaseInfo(true, $this->cmsVersion)
                ->one();

            if (!$plugin) {
                return $this->asErrorJson("Couldn't find plugin");
            }

            $data = $this->transformPlugin($plugin, true);

            $this->setCache($cacheKey, $data);
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
     * Handles /v1/plugin-store/plugins-by-featured-section/<handle:{slug}> requests.
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
        $data = $this->getCache($cacheKey);

        if (!$data) {
            $pluginHandles = explode(',', $pluginHandles);

            $plugins = Plugin::find()
                ->withLatestReleaseInfo(true, $this->cmsVersion)
                ->with(['developer', 'categories', 'icon'])
                ->indexBy('id')
                ->andWhere(['craftnet_plugins.handle' => $pluginHandles])
                ->all();

            $data = $this->_transformPlugins($plugins);

            $this->setCache($cacheKey,$data);
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
            $plugins = $this->getPluginIndexQuery()
                ->andWhere([
                    'or',
                    ['like', 'name', $searchQuery . '%', false],
                    ['like', 'packageName', $searchQuery],
                    ['like', 'shortDescription', $searchQuery],
                    ['like', 'description', $searchQuery],
                    // ['like', 'developerName', $searchQuery],
                    // ['like', 'developerUrl', $searchQuery],
                    // ['like', 'keywords', $searchQuery],
                ])
                ->all();

            $data = $this->_plugins($plugins);

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
        $perPage = (int) $params['perPage'];

        $total = ceil($totalResults / $perPage);

        $currentPage = (int) $params['page'];

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
    private function getCache(string $key)
    {
        $key = 'pluginStore-' . $key . '-' . $this->cmsVersion;

        $craftIdConfig = Craft::$app->getConfig()->getConfigFromFile('craftid');
        $enablePluginStoreCache = $craftIdConfig['enablePluginStoreCache'];

        if (!$enablePluginStoreCache) {
            return null;
        }

        return Craft::$app->getCache()->get($key);
    }

    /**
     * @param string $key
     * @param $value
     * @return bool|null
     */
    public function setCache(string $key, $value)
    {
        $key = 'pluginStore-' . $key . '-' . $this->cmsVersion;

        $craftIdConfig = Craft::$app->getConfig()->getConfigFromFile('craftid');
        $enablePluginStoreCache = $craftIdConfig['enablePluginStoreCache'];

        if (!$enablePluginStoreCache) {
            return null;
        }

        return Craft::$app->getCache()->set($key, $value, null, new FileDependency([
            'fileName' => $this->module->getJsonDumper()->composerWebroot . '/packages.json',
        ]));
    }

    /**
     * @param string $key
     * @return mixed|null
     */
    private function getPluginIndexCache(string $key)
    {
        $cacheKey = $this->getPluginIndexCacheKey($key);

        return $this->getCache($cacheKey);
    }

    /**
     * @param string $key
     * @param $value
     * @return bool|null
     */
    private function setPluginIndexCache(string $key, $value)
    {
        $cacheKey = $this->getPluginIndexCacheKey($key);

        return $this->setCache($cacheKey, $value);
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
        $perPage = Craft::$app->getRequest()->getParam('perPage', 10);
        $page = Craft::$app->getRequest()->getParam('page', 1);
        $orderBy = Craft::$app->getRequest()->getParam('orderBy', 'activeInstalls');
        $direction = Craft::$app->getRequest()->getParam('direction', 'desc');
        $direction = $direction === 'asc' ? SORT_ASC : SORT_DESC;

        return [
            'perPage' => $perPage,
            'page' => $page,
            'orderBy' => $orderBy,
            'direction' => $direction,
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
            ->withLatestReleaseInfo(true, $this->cmsVersion)
            ->with(['developer', 'categories', 'icon'])
            ->indexBy('id')
            ->orderBy([($params['orderBy'] === 'name' ? $params['orderBy'] = 'LOWER('.$params['orderBy'].')' : $params['orderBy']) => $params['direction']])
            ->offset($offset)
            ->limit($limit);

        return $query;
    }

    /**
     * @return \craft\elements\db\ElementQueryInterface|\craft\elements\db\EntryQuery
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
        $data = $this->getCache($cacheKey);

        if (!$data) {
            $pluginIds = null;

            switch ($featuredSectionEntry->getType()->handle) {
                case 'manual':
                    /** @var PluginQuery $query */
                    $query = $featuredSectionEntry->plugins;
                    $pluginIds = $query
                        ->withLatestReleaseInfo(true, $this->cmsVersion)
                        ->ids();
                    break;
                case 'dynamic':
                    $pluginIds = $this->_dynamicPlugins($featuredSectionEntry->slug);
                    break;
                default:
                    $pluginIds = null;
            }

            if (!$pluginIds) {
                return null;
            }

            $query = Plugin::find()
                ->withLatestReleaseInfo(true, $this->cmsVersion)
                ->with(['developer', 'categories', 'icon'])
                ->indexBy('id')
                ->offset($offset)
                ->limit($limit);

            $query->andWhere(['craftnet_plugins.id' => $pluginIds]);

            $data = $this->getPluginIndexResponse($query);

            $this->setCache($cacheKey, $data);
        }

        return $data;
    }

    /**
     * @return array
     */
    private function _categories(): array
    {
        $cacheKey = __METHOD__;
        $data = $this->getCache($cacheKey);

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

            $this->setCache($cacheKey, $data);
        }

        return $data;
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
