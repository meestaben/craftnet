<?php

namespace craftnet\composer\vcs;

use Craft;
use craft\helpers\Json;
use craftnet\composer\PackageRelease;
use GuzzleHttp\Exception\ClientException;
use yii\base\Exception;
use yii\base\NotSupportedException;

/**
 * @property array $versions
 */
class Packagist extends BaseVcs
{
    /**
     *
     */
    const BASE_URL = 'https://packagist.org/';

    /**
     * @var
     */
    private static $_rootComposerInfo;

    /**
     * @param string $uri
     * @param null $cacheDuration
     *
     * @return array
     */
    public static function fetchPackagistData(string $uri, $cacheDuration = null): array
    {
        if ($cacheDuration !== false) {
            $cacheService = Craft::$app->getCache();
            $cacheKey = __METHOD__ . '--' . $uri;
            if (($data = $cacheService->get($cacheKey)) !== false) {
                return $data;
            }
        }

        $url = self::BASE_URL . $uri;
        Craft::trace('Fetching ' . $uri);
        $response = Craft::createGuzzleClient()->request('get', $url);
        $data = Json::decode((string)$response->getBody());

        if ($cacheDuration !== false) {
            /** @noinspection PhpUndefinedVariableInspection */
            $cacheService->set($cacheKey, $data, $cacheDuration);
        }

        return $data;
    }

    /**
     * @return array
     */
    public static function rootComposerInfo(): array
    {
        return self::$_rootComposerInfo ?? self::$_rootComposerInfo = self::fetchPackagistData('packages.json', 60 * 60);
    }

    /**
     * @param string $name
     *
     * @return array
     * @throws Exception
     */
    public static function packageInfo(string $name): array
    {
        try {
            $info = Craft::$app->getCache()->getOrSet(__METHOD__ . '--' . $name, function() use ($name) {
                Craft::trace('Fetching package info for ' . $name);
                $root = self::rootComposerInfo();
                $includes = array_reverse($root['provider-includes'], true);
                foreach ($includes as $uri => $include) {
                    $uri = str_replace('%hash%', $include['sha256'], $uri);
                    $providers = self::fetchPackagistData($uri);
                    if (isset($providers['providers'][$name])) {
                        $uri = str_replace(['%package%', '%hash%'], [$name, $providers['providers'][$name]['sha256']], ltrim($root['providers-url'], '/'));
                        return self::fetchPackagistData($uri, false);
                    }
                }
                return null;
            }, 60 * 60);
        } catch (ClientException $e) {
            $response = $e->getResponse();
            if ($response && $response->getStatusCode() === 404) {
                // The JSON files probably shifted. Reload the composer root and try again
                Craft::warning($e->getMessage(), __METHOD__);
                self::$_rootComposerInfo = null;
                Craft::$app->getCache()->delete(self::class . '::fetchPackagistData--packages.json');
                return self::packageInfo($name);
            }
            throw $e;
        }
        if ($info === null) {
            throw new Exception('Package not found on Packagist: ' . $name);
        }
        return $info;
    }

    /**
     * @return array
     */
    public function getVersions(): array
    {
        $versions = [];

        try {
            $packageInfo = self::packageInfo($this->package->name);
        } catch (Exception $e) {
            return $versions;
        }

        if (!empty($packageInfo['packages'][$this->package->name])) {
            foreach ($packageInfo['packages'][$this->package->name] as $version => $info) {
                $sha = $info['source']['reference'] ?? $info['dist']['reference'] ?? null;
                if ($sha === null) {
                    Craft::warning('Skipping package version due to unknown hash: ' . $this->package->name . ':' . $version);
                    continue;
                }
                $versions[$version] = $sha;
            }
        }

        return $versions;
    }

    /**
     * @param PackageRelease $release
     */
    public function populateRelease(PackageRelease $release)
    {
        $packageInfo = self::packageInfo($this->package->name);

        if (!isset($packageInfo['packages'][$this->package->name][$release->version])) {
            Craft::warning("Ignoring package version {$this->package->name}:{$release->version} because it can't be found in the Packagist provider JSON.");
            $release->invalidate('not found on Packagist');
            return;
        }

        $config = $packageInfo['packages'][$this->package->name][$release->version];

        if ($this->populateReleaseFromComposerConfig($release, $config) === false) {
            return;
        }

        $release->source = $config['source'] ?? null;
        $release->dist = $config['dist'] ?? null;

        $this->package->setAbandoned($config['abandoned'] ?? false);
    }

    public function createWebhook()
    {
        throw new NotSupportedException("Packagist doesn't support webhooks");
    }

    public function deleteWebhook()
    {
        throw new NotSupportedException("Packagist doesn't support webhooks");
    }
}
