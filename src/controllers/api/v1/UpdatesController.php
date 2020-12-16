<?php

namespace craftnet\controllers\api\v1;

use Composer\Semver\Comparator;
use Composer\Semver\VersionParser;
use craft\db\Query;
use craft\models\Update;
use craftnet\composer\PackageRelease;
use craftnet\controllers\api\BaseApiController;
use craftnet\errors\ValidationException;
use craftnet\plugins\Plugin;
use yii\web\BadRequestHttpException;
use yii\web\Response;

/**
 * Class UpdatesController
 */
class UpdatesController extends BaseApiController
{
    public $defaultAction = 'get';

    public function runAction($id, $params = []): Response
    {
        // BC support for old POST /v1/updates requests
        if ($id === 'old') {
            try {
                $payload = $this->getPayload('updates-request-old');
                $headers = $this->request->getHeaders();
                $scheme = $payload->request->port === 443 ? 'https' : 'http';
                $port = !in_array($payload->request->port, [80, 443]) ? ":{$payload->request->port}" : '';
                $headers->set('X-Craft-Host', "{$scheme}://{$payload->request->hostname}{$port}");
                $headers->set('X-Craft-User-Ip', $payload->request->ip);
                $headers->set('X-Craft-User-Email', $payload->user->email);
                $platform = [];
                foreach ($payload->platform as $name => $value) {
                    $platform[] = "{$name}:{$value}";
                }
                $headers->set('X-Craft-Platform', implode(',', $platform));
                $headers->set('X-Craft-License', $payload->cms->licenseKey);
                $system = ["craft:{$payload->cms->version};{$payload->cms->edition}"];
                $pluginLicenses = [];
                if (!empty($payload->plugins)) {
                    foreach ($payload->plugins as $pluginHandle => $pluginInfo) {
                        $system[] = "plugin-{$pluginHandle}:{$pluginInfo->version}";
                        if ($pluginInfo->licenseKey !== null) {
                            $pluginLicenses[] = "{$pluginHandle}:{$pluginInfo->licenseKey}";
                        }
                    }
                }
                $headers->set('X-Craft-System', implode(',', $system));
                if (!empty($pluginLicenses)) {
                    $headers->set('X-Craft-Plugin-Licenses', implode(',', $pluginLicenses));
                }
            } catch (ValidationException $e) {
                // let actionGet() throw the validation error
            }
            $id = 'get';
        }

        return parent::runAction($id, $params);
    }

    /**
     * Retrieves available system updates.
     *
     * @param string|null $maxVersions The max versions to retrieve
     * @return Response
     * @throws \Throwable
     */
    public function actionGet(string $maxVersions = null): Response
    {
        if ($this->cmsVersion === null) {
            throw new BadRequestHttpException('Unable to determine the current Craft version.');
        }

        $includePackageName = (
            $this->cmsVersion &&
            Comparator::greaterThanOrEqualTo($this->cmsVersion, '3.1.21') &&
            Comparator::notEqualTo($this->cmsVersion, '3.2.0-alpha.1')
        );

        $maxVersionsArr = [];
        if ($maxVersions) {
            foreach (explode(',', $maxVersions) as $pair) {
                [$name, $version] = explode(':', $pair);
                $maxVersionsArr[$name] = $version;
            }
        }

        return $this->asJson([
            'cms' => $this->_getCmsUpdateInfo($includePackageName, $maxVersionsArr['cms'] ?? null),
            'plugins' => $this->_getPluginUpdateInfo($includePackageName, $maxVersionsArr),
        ]);
    }

    /**
     * Returns CMS update info.
     *
     * @param bool $includePackageName
     * @param string|null $maxVersion
     * @return array
     */
    private function _getCmsUpdateInfo(bool $includePackageName, string $maxVersion = null): array
    {
        $constraints = [];
        $breakpoint = false;

        if ($maxVersion) {
            $constraints[] = "<=$maxVersion";
        }

        if (version_compare($this->cmsVersion, '3.0.0-alpha.1', '>=')) {
            if (version_compare($this->cmsVersion, '3.0.41.1', '<')) {
                // Treat ~3.0.41.1 as a breakpoint for 3.0 releases
                $constraints[] = '~3.0.41.1';
                $breakpoint = true;
            } else if (version_compare($this->cmsVersion, '3.1.20', '>=') && version_compare($this->cmsVersion, '3.1.34.3', '<')) {
                // Treat ~3.1.34.3 as a breakpoint for ~3.1.20 releases (where project-config/rebuild was added)
                $constraints[] = '~3.1.34.3';
                $breakpoint = true;
            }
        }

        $constraint = $constraints ? implode(' ', $constraints) : null;
        /** @var array $releases */
        /** @var PackageRelease|null $latest */
        [$releases, $latest] = $this->_releases('craftcms/cms', $this->cmsVersion, $constraint);
        $info = [
            'status' => $breakpoint ? Update::STATUS_BREAKPOINT : Update::STATUS_ELIGIBLE,
            'releases' => $releases,
        ];

        if (!empty($this->cmsLicenses)) {
            $cmsLicense = reset($this->cmsLicenses);
            if ($cmsLicense->expired) {
                $info['status'] = Update::STATUS_EXPIRED;
                $info['renewalUrl'] = $cmsLicense->getEditUrl();
                $info['renewalPrice'] = $cmsLicense->getEdition()->renewalPrice;
                $info['renewalCurrency'] = 'USD';
            }
        }

        // Update::$phpConstraint wasn't added until 3.5.15
        if ($latest !== null && version_compare($this->cmsVersion, '3.5.15', '>=')) {
            $info['phpConstraint'] = (new Query())
                ->select(['constraints'])
                ->from(['craftnet_packagedeps'])
                ->where(['versionId' => $latest->id, 'name' => 'php'])
                ->scalar() ?: null;
        }

        if ($includePackageName) {
            // Send the package name just in case it has changed
            $info['packageName'] = 'craftcms/cms';
        }

        return $info;
    }

    /**
     * Returns plugin update info.
     *
     * @param bool $includePackageName
     * @param string[] $maxVersions
     * @return array
     */
    private function _getPluginUpdateInfo(bool $includePackageName, array $maxVersions): array
    {
        $updateInfo = [];

        foreach ($this->plugins as $handle => $plugin) {
            // Get the latest release that's compatible with their current Craft version
            $toVersion = Plugin::find()
                ->id($plugin->id)
                ->withLatestReleaseInfo(true, $this->cmsVersion)
                ->select(['latestVersion'])
                ->asArray()
                ->scalar();

            if ($toVersion) {
                $constraints = ["<=$toVersion"];
                if (isset($maxVersions[$handle])) {
                    $constraints[] = "<={$maxVersions[$handle]}";
                }
                [$releases] = $this->_releases($plugin->packageName, $this->pluginVersions[$handle], implode(' ', $constraints));
            } else {
                $releases = [];
            }

            $info = [
                'status' => Update::STATUS_ELIGIBLE,
                'releases' => $releases,
            ];

            if (isset($this->pluginLicenses[$handle])) {
                $pluginLicense = $this->pluginLicenses[$handle];
                if ($pluginLicense->expired) {
                    $info['status'] = Update::STATUS_EXPIRED;
                    $info['renewalUrl'] = $pluginLicense->getEditUrl();
                    $info['renewalPrice'] = $pluginLicense->getEdition()->renewalPrice;
                    $info['renewalCurrency'] = 'USD';
                }
            }

            if ($includePackageName) {
                // Send the package name just in case it has changed
                $info['packageName'] = $plugin->packageName;
            }

            $updateInfo[$handle] = $info;
        }

        return $updateInfo;
    }

    /**
     * Transforms releases for inclusion in [[actionIndex()]] response JSON.
     *
     * @param string $name The package name
     * @param string $fromVersion The version that is already installed
     * @param string|null $constraint The version constraint
     * @return array
     */
    private function _releases(string $name, string $fromVersion, string $constraint = null): array
    {
        // Ignore if a dev version is currently installed
        $stability = VersionParser::parseStability($fromVersion);

        if ($stability === 'dev') {
            return [[], null];
        }

        $packageManager = $this->module->getPackageManager();
        $releases = $packageManager->getReleasesAfter($name, $fromVersion, $stability, $constraint);

        // Sort descending
        $releases = array_reverse($releases);

        return [
            array_map(function(PackageRelease $release): array {
                return $release->toArray(['version', 'critical', 'date', 'notes']);
            }, $releases),
            reset($releases) ?: null,
        ];
    }
}
