<?php

namespace craftnet\controllers\id;

use craft\helpers\ArrayHelper;
use craft\web\Controller;
use craftnet\plugins\Plugin;
use yii\helpers\Markdown;

/**
 * Class BaseController
 *
 * @property array $apps
 */
abstract class BaseController extends Controller
{
    // Protected Methods
    // =========================================================================

    /**
     * @param Plugin $plugin
     *
     * @return array
     * @throws \yii\base\InvalidConfigException
     */
    protected function pluginTransformer(Plugin $plugin): array
    {
        $icon = $plugin->getIcon();
        $developer = $plugin->getDeveloper();

        // Screenshots
        $screenshotUrls = [];
        $screenshotIds = [];

        foreach ($plugin->getScreenshots() as $screenshot) {
            $screenshotUrls[] = $screenshot->getUrl() . '?' . $screenshot->dateModified->getTimestamp();
            $screenshotIds[] = $screenshot->getId();
        }

        // Last history note
        $lastHistoryNote = null;
        $history = $plugin->getHistory();

        if (count($history) > 0) {
            $lastHistoryNote = $history[0];

            if ($lastHistoryNote['devComments']) {
                $lastHistoryNote['devComments'] = Markdown::process($lastHistoryNote['devComments']);
            }
        }

        $editions = [];
        foreach ($plugin->getEditions() as $edition) {
            $editions[] = [
                'id' => $edition->id,
                'name' => $edition->name,
                'handle' => $edition->handle,
                'price' => $edition->price,
                'renewalPrice' => $edition->renewalPrice,
                'features' => $edition->features ?? [],
            ];
        }

        // Latest version
        $latestVersion = Plugin::find()
            ->id($plugin->id)
            ->withLatestReleaseInfo()
            ->select(['latestVersion'])
            ->asArray()
            ->scalar();

        $replacement = $plugin->getReplacement();

        return [
            'id' => $plugin->id,
            'enabled' => $plugin->enabled,
            'pendingApproval' => $plugin->pendingApproval,
            'status' => $plugin->status,
            'iconId' => $plugin->iconId,
            'iconUrl' => $icon ? $icon->getUrl() . '?' . $icon->dateModified->getTimestamp() : null,
            'packageName' => $plugin->packageName,
            'handle' => $plugin->handle,
            'name' => $plugin->name,
            'shortDescription' => $plugin->shortDescription,
            'longDescription' => $plugin->longDescription,
            'documentationUrl' => $plugin->documentationUrl,
            'changelogPath' => $plugin->changelogPath,
            'repository' => $plugin->repository,
            'license' => $plugin->license,
            'editions' => $editions,
            'keywords' => $plugin->keywords,
            'latestVersion' => $latestVersion ?: null,

            // 'iconUrl' => $iconUrl,
            'developerId' => $developer->id,
            'developerName' => $developer->getDeveloperName(),
            'developerUrl' => $developer->developerUrl,

            'screenshotUrls' => $screenshotUrls,
            'screenshotIds' => $screenshotIds,
            'categoryIds' => ArrayHelper::getColumn($plugin->getCategories(), 'id'),

            'lastHistoryNote' => $lastHistoryNote,
            'activeInstalls' => $plugin->activeInstalls,
            'abandoned' => $plugin->abandoned,
            'replacementHandle' => $replacement->handle ?? null,
        ];
    }

    /**
     * Get expiry date options.
     *
     * @param \DateTime $expiryDate
     * @return array
     * @throws \Exception
     */
    protected function getExpiryDateOptions(\DateTime $expiryDate): array
    {
        $now = new \DateTime('now', new \DateTimeZone('UTC'));
        $dates = [];

        for ($i = 1; $i <= 5; $i++) {
            if ($expiryDate < $now) {
                $date = (new \DateTime('now', new \DateTimeZone('UTC')))
                    ->modify("+{$i} years");
                $dates[] = ["{$i}y", $date->format('Y-m-d')];
            } else {
                $date = clone $expiryDate;
                $date = $date->modify("+{$i} years");
                $dates[] = ["{$date->format('Y-m-d')}", $date->format('Y-m-d')];
            }
        }

        return $dates;
    }
}
