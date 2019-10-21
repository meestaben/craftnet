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
use yii\base\InvalidArgumentException;
use yii\caching\FileDependency;
use yii\web\BadRequestHttpException;
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

    public function actionCoreData(): Response
    {
        $pluginStoreData = [
            'categories' => $this->_categories(),
            'expiryDateOptions' => $this->_expiryDateOptions(),
        ];

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

    public function actionPlugin($handle): Response
    {
        $plugin = Plugin::find()
            ->handle($handle)
            ->anyStatus()
            ->withLatestReleaseInfo(true, $this->cmsVersion)
            ->one();

        if (!$plugin) {
            return $this->asErrorJson("Couldn't find plugin");
        }

        return $this->asJson($this->transformPlugin($plugin, true));
    }

    public function actionPluginsByCategory($categoryId): Response
    {
        $data = null;

        $offset = Craft::$app->getRequest()->getParam('offset', 0);
        $limit = Craft::$app->getRequest()->getParam('limit', 10);
        $orderBy = Craft::$app->getRequest()->getParam('orderBy', 'activeInstalls');
        $direction = Craft::$app->getRequest()->getParam('direction', 'desc');
        $orderByDirection = $direction === 'asc' ? SORT_ASC : SORT_DESC;

        $craftIdConfig = Craft::$app->getConfig()->getConfigFromFile('craftid');
        $enablePluginStoreCache = $craftIdConfig['enablePluginStoreCache'];
        $cacheKey = 'pluginStore-featuredSections-'.$categoryId.'-'.$offset.'-'.$limit.'-'.$orderBy.'-'.$direction.'--' . $this->cmsVersion;

        if ($enablePluginStoreCache) {
            $data = Craft::$app->getCache()->get($cacheKey);
        }

        // activeInstalls
        // lastUpdate
        // name
        // price

        if(!$data) {
            $plugins = Plugin::find()
                ->categoryId($categoryId)
                ->withLatestReleaseInfo(true, $this->cmsVersion)
                ->with(['developer', 'categories', 'icon'])
                ->indexBy('id')
                ->orderBy([($orderBy === 'name' ? $orderBy = 'LOWER('.$orderBy.')' : $orderBy) => $orderByDirection])
                ->offset($offset)
                ->limit($limit)
                ->all();

            $data = $this->_plugins($plugins);
        }

        if ($enablePluginStoreCache) {
            Craft::$app->getCache()->set($cacheKey, $data, null, new FileDependency([
                'fileName' => $this->module->getJsonDumper()->composerWebroot . '/packages.json',
            ]));
        }

        return $this->asJson($data);
    }

    public function actionSearchPlugins()
    {
        $searchQuery = Craft::$app->getRequest()->getParam('searchQuery', 10);
        $limit = Craft::$app->getRequest()->getParam('limit', 10);
        $offset = Craft::$app->getRequest()->getParam('offset', 0);
        $orderBy = Craft::$app->getRequest()->getParam('orderBy', 'activeInstalls');
        $direction = Craft::$app->getRequest()->getParam('direction', 'desc');
        $orderByDirection = $direction === 'asc' ? SORT_ASC : SORT_DESC;

        $plugins = Plugin::find()
            ->withLatestReleaseInfo(true, $this->cmsVersion)
            ->with(['developer', 'categories', 'icon'])
            ->indexBy('id')
            ->orderBy([($orderBy === 'name' ? $orderBy = 'LOWER('.$orderBy.')' : $orderBy) => $orderByDirection])
            ->offset($offset)
            ->limit($limit)
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

        return $this->asJson($data);
    }

    public function actionPluginsByDeveloper($developerId): Response
    {
        $limit = Craft::$app->getRequest()->getParam('limit', 10);
        $offset = Craft::$app->getRequest()->getParam('offset', 0);
        $orderBy = Craft::$app->getRequest()->getParam('orderBy', 'activeInstalls');
        $direction = Craft::$app->getRequest()->getParam('direction', 'desc');
        $orderByDirection = $direction === 'asc' ? SORT_ASC : SORT_DESC;

        $plugins = Plugin::find()
            ->developerId($developerId)
            ->withLatestReleaseInfo(true, $this->cmsVersion)
            ->with(['developer', 'categories', 'icon'])
            ->indexBy('id')
            ->orderBy([($orderBy === 'name' ? $orderBy = 'LOWER('.$orderBy.')' : $orderBy) => $orderByDirection])
            ->offset($offset)
            ->limit($limit)
            ->all();

        $data = $this->_plugins($plugins);

        return $this->asJson($data);
    }

    public function actionFeaturedSections(): Response
    {
        $featuredSections = null;

        $craftIdConfig = Craft::$app->getConfig()->getConfigFromFile('craftid');
        $enablePluginStoreCache = $craftIdConfig['enablePluginStoreCache'];
        $cacheKey = 'pluginStore-featuredSections--' . $this->cmsVersion;

        if ($enablePluginStoreCache) {
            $featuredSections = Craft::$app->getCache()->get($cacheKey);
        }
        
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
        }

        if ($enablePluginStoreCache) {
            Craft::$app->getCache()->set($cacheKey, $featuredSections, null, new FileDependency([
                'fileName' => $this->module->getJsonDumper()->composerWebroot . '/packages.json',
            ]));
        }
        
        return $this->asJson($featuredSections);
    }

    public function actionPluginsByFeaturedSection($handle): Response
    {
        $featuredSectionEntry = $this->featuredSectionQuery()
            ->slug($handle)
            ->one();

        $plugins = $this->getFeaturedSectionPlugins($featuredSectionEntry);

        return $this->asJson($plugins);
    }


    public function actionPluginsByHandles(): Response
    {
        $pluginHandles = Craft::$app->getRequest()->getParam('pluginHandles', '');
        $pluginHandles = explode(',', $pluginHandles);

        $plugins = Plugin::find()
            ->withLatestReleaseInfo(true, $this->cmsVersion)
            ->with(['developer', 'categories', 'icon'])
            ->indexBy('id')
            ->andWhere(['craftnet_plugins.handle' => $pluginHandles])
            ->all();

        $data = $this->_transformPlugins($plugins);

        return $this->asJson($data);
    }

    public function actionFeaturedSection($handle): Response
    {
        $featuredSectionEntry = $this->featuredSectionQuery()
            ->slug($handle)
            ->one();

        $featuredSection = $this->transformFeaturedSection($featuredSectionEntry);

        return $this->asJson($featuredSection);
    }

    // Private Methods
    // =========================================================================

    private function featuredSectionQuery()
    {
        return Entry::find()
            ->site('craftId')
            ->section('featuredPlugins');
    }

    private function transformFeaturedSection($featuredSectionEntry)
    {
        return [
            'id' => $featuredSectionEntry->id,
            'slug' => $featuredSectionEntry->slug,
            'title' => $featuredSectionEntry->title,
            'limit' => $featuredSectionEntry->limit,
        ];
    }

    private function getFeaturedSectionPlugins($featuredSectionEntry, $limit = null)
    {
        $limit = $limit ?? Craft::$app->getRequest()->getParam('limit', 10);
        $offset = Craft::$app->getRequest()->getParam('offset', 0);

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

        $pluginQuery = Plugin::find()
            ->withLatestReleaseInfo(true, $this->cmsVersion)
            ->with(['developer', 'categories', 'icon'])
            ->indexBy('id')
            ->offset($offset)
            ->limit($limit);

        $pluginQuery->andWhere(['craftnet_plugins.id' => $pluginIds]);

        $plugins = $pluginQuery->all();

        return $this->_transformPlugins($plugins);
    }

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
