<?php

namespace craftnet\composer;

use Aws\CloudFront\CloudFrontClient;
use Aws\CloudFront\Exception\CloudFrontException;
use Aws\Credentials\Credentials;
use Aws\Handler\GuzzleV6\GuzzleHandler;
use Aws\Sts\StsClient;
use Composer\Util\MetadataMinifier;
use Craft;
use craft\db\Query;
use craft\helpers\Console;
use craft\helpers\DateTimeHelper;
use craft\helpers\FileHelper;
use craft\helpers\Json;
use craftnet\composer\jobs\DeletePaths;
use craftnet\composer\jobs\DumpJson;
use craftnet\Module;
use yii\base\Component;

/**
 * Composer repository JSON generator
 */
class JsonDumper extends Component
{
    // How long to cache AWS creds
    const AWS_CREDENTIAL_CACHE_DURATION = 21600;

    /**
     * @var string The path that packages.json, etc., should be saved
     * @see dumpProviderJson()
     */
    public $composerWebroot;

    /*
     * @var null
     */
    public $cfDistributionId;

    /**
     *
     */
    public function init()
    {
        if ($id = (getenv('CLOUDFRONT_COMPOSER_DISTRIBUTION_ID'))) {
            $this->cfDistributionId = $id;
        }
    }

    /**
     * Dumps out packages.json, and all the provider JSON files.
     *
     * @param bool $queue Whether to queue the dump
     * @throws \yii\base\ErrorException
     */
    public function dump(bool $queue = false)
    {
        if ($queue) {
            Craft::$app->getQueue()->push(new DumpJson());
            return;
        }

        $isConsole = Craft::$app->getRequest()->getIsConsoleRequest();

        Craft::info('Dumping JSON.', __METHOD__);

        if ($isConsole) {
            Console::stdout('Dumping JSON ...' . PHP_EOL);
            Console::stdout('> Fetching package versions ... ');
        }

        // Fetch all the data
        $packages = (new Query())
            ->select(['id', 'name', 'abandoned', 'replacementPackage'])
            ->from(['craftnet_packages'])
            ->indexBy('id')
            ->all();

        $versions = (new Query())
            ->select([
                'id',
                'packageId',
                'description',
                'version',
                'normalizedVersion',
                'type',
                'keywords',
                'homepage',
                'time',
                'license',
                'authors',
                'support',
                'conflict',
                'replace',
                'provide',
                'suggest',
                'autoload',
                'includePaths',
                'targetDir',
                'extra',
                'binaries',
                //'source',
                'dist',
            ])
            ->from(['craftnet_packageversions'])
            ->where([
                'packageId' => array_keys($packages),
                'valid' => true,
            ])
            ->indexBy('id')
            ->all();

        // Sort by version DESC
        Module::getInstance()->getPackageManager()->sortVersions($versions, SORT_DESC);

        $deps = (new Query())
            ->select(['versionId', 'name', 'constraints'])
            ->from(['craftnet_packagedeps'])
            ->all();

        if ($isConsole) {
            Console::stdout('done' . PHP_EOL, Console::FG_GREEN);
            Console::stdout('> Preparing data ... ');
        }

        // Assemble the data
        $depsByVersion = [];
        foreach ($deps as $dep) {
            $depsByVersion[$dep['versionId']][] = $dep;
        }

        $v1PackageData = [];
        $v2PackageData = [];
        $v2ProviderData = [];

        foreach ($versions as $version) {
            $package = $packages[$version['packageId']];
            $name = $package['name'];

            if (isset($depsByVersion[$version['id']])) {
                $require = [];
                foreach ($depsByVersion[$version['id']] as $dep) {
                    $require[$dep['name']] = $dep['constraints'];
                }
            } else {
                $require = null;
            }

            // Assemble in the same order as \Packagist\WebBundle\Entity\Version::toArray()
            // `source` is intentionally ignored for now.
            $data = [
                'name' => $name,
                'description' => (string)$version['description'],
                'keywords' => $version['keywords'] ? Json::decode($version['keywords']) : [],
                'homepage' => (string)$version['homepage'],
                'version' => $version['version'],
                'version_normalized' => $version['normalizedVersion'],
                'license' => $version['license'] ? Json::decode($version['license']) : [],
                'authors' => $version['authors'] ? Json::decode($version['authors']) : [],
                'support' => $version['support'] ? Json::decode($version['support']) : [],
                'dist' => $version['dist'] ? Json::decode($version['dist']) : null,
                'type' => $version['type'],
            ];

            if ($version['time'] !== null) {
                $data['time'] = $version['time'];
            }
            if ($version['autoload'] !== null) {
                $data['autoload'] = Json::decode($version['autoload']);
            }
            if ($version['extra'] !== null) {
                $data['extra'] = Json::decode($version['extra']);
            }
            if ($version['targetDir'] !== null) {
                $data['target-dir'] = $version['targetDir'];
            }
            if ($version['includePaths'] !== null) {
                $data['include-path'] = Json::decode($version['includePaths']);
            }
            if ($version['binaries'] !== null) {
                $data['bin'] = Json::decode($version['binaries']);
            }
            if ($require !== null) {
                $data['require'] = $require;
            }
            if ($version['suggest'] !== null) {
                $data['suggest'] = Json::decode($version['suggest']);
            }
            if ($version['conflict'] !== null) {
                $data['conflict'] = Json::decode($version['conflict']);
            }
            if ($version['provide'] !== null) {
                $data['provide'] = Json::decode($version['provide']);
            }
            if ($version['replace'] !== null) {
                $data['replace'] = Json::decode($version['replace']);
            }
            if ($package['abandoned']) {
                $data['abandoned'] = $package['replacementPackage'] ?: true;
            }
            $data['uid'] = (int)$version['id'];

            // Composer 1 allows multiple packages to be listed in the same file (main package + any provider packages),
            // but Composer 2 does not.
            $v1PackageData[$name][$name][$data['version']] = $data;
            $v2PackageData[$name][] = $data;

            // Does this package provide any other packages?
            if (!empty($data['provide'])) {
                foreach (array_keys($data['provide']) as $provideName) {
                    // Add it to the provided package’s Composer 1 data
                    $v1PackageData[$provideName][$name][$data['version']] = $data;

                    // Add it to the provided package’s providers-api file
                    // e.g. https://packagist.org/providers/monolog/monolog.json
                    if (!isset($v2ProviderData[$provideName][$name])) {
                        $v2ProviderData[$provideName][$name] = [
                            'name' => $name,
                            'description' => $data['description'],
                            'type' => $data['type'],
                        ];
                    }
                }
            }
        }

        if ($isConsole) {
            Console::stdout('done' . PHP_EOL, Console::FG_GREEN);
        }

        // Create the JSON files
        $v1OldPaths = [];
        $v1ProviderData = [];

        foreach ($v1PackageData as $name => $data) {
            $providerHash = $this->_writeHashedJsonFile("p/$name/%hash%.json", [
                'packages' => $data,
            ], $v1OldPaths, $isConsole);
            $v1ProviderData[$name] = ['sha256' => $providerHash];
        }

        $v1IndexPath = 'p/provider/%hash%.json';
        $v1IndexHash = $this->_writeHashedJsonFile($v1IndexPath, [
            'providers' => $v1ProviderData,
        ], $v1OldPaths, $isConsole);

        Craft::info("Writing JSON file to packages.json", __METHOD__);
        $this->_writeJsonFile('packages.json', [
            'packages' => [],
            'providers-url' => '/p/%package%/%hash%.json',
            'metadata-url' => '/p2/%package%.json',
            'providers-api' => '/providers/%package%.json',
            'provider-includes' => [
                $v1IndexPath => ['sha256' => $v1IndexHash],
            ],
        ], $isConsole);

        foreach ($v2PackageData as $name => $data) {
            $this->_writeJsonFile("p2/$name.json", [
                'packages' => [
                    $name => MetadataMinifier::minify($data),
                ],
                'minified' => 'composer/2.0',
            ], $isConsole);
            $this->_writeJsonFile("p2/$name~dev.json", [
                'packages' => [
                    $name => [],
                ],
            ], $isConsole);
        }

        foreach ($v2ProviderData as $name => $data) {
            $this->_writeJsonFile("providers/$name.json", [
                'providers' => array_values($data),
            ], $isConsole);
        }

        if ($isConsole) {
            Console::stdout('Finished dumping JSON' . PHP_EOL, Console::FG_GREEN);
        }

        if (!empty($v1OldPaths)) {
            Craft::$app->getQueue()->delay(900)->push(new DeletePaths([
                'paths' => $v1OldPaths,
            ]));
        }
    }

    /**
     * @param $path
     * @return bool
     */
    private function _invalidateCloudFrontPath($path): bool
    {
        $client = $this->_getCloudFrontClient();

        try {
            $client->createInvalidation(
                [
                    'DistributionId' => $this->cfDistributionId,
                    'InvalidationBatch' => [
                        'Paths' =>
                            [
                                'Quantity' => 1,
                                'Items' => [$path]
                            ],
                        'CallerReference' => 'craftnet-' . DateTimeHelper::currentTimeStamp()
                    ]
                ]
            );
        } catch (CloudFrontException $exception) {
            Craft::warning($exception->getMessage(), __METHOD__);
        }

        return true;
    }

    /**
     * @return CloudFrontClient
     */
    private function _getCloudFrontClient(): CloudFrontClient
    {
        $awsKeyId = getenv('AWS_ACCESS_KEY_ID');
        $awsSecretKey = getenv('AWS_SECRET_ACCESS_KEY');
        $awsRegion = getenv('REGION');

        $config = [
            'region' => $awsRegion,
            'version' => 'latest'
        ];

        $client = Craft::createGuzzleClient();
        $config['http_handler'] = new GuzzleHandler($client);
        $tokenKey = 'craftnet.' . md5($awsKeyId . $awsSecretKey);

        $credentials = new Credentials($awsKeyId, $awsSecretKey);

        // See if they're cached first.
        if (Craft::$app->cache->exists($tokenKey)) {
            $cached = Craft::$app->cache->get($tokenKey);
            $credentials->unserialize($cached);
        } else {
            $config['credentials'] = $credentials;
            $stsClient = new StsClient($config);

            // Cache for 6 hours
            $result = $stsClient->getSessionToken(['DurationSeconds' => static::AWS_CREDENTIAL_CACHE_DURATION]);
            $credentials = $stsClient->createCredentials($result);
            $cacheDuration = $credentials->getExpiration() - time();
            $cacheDuration = $cacheDuration > 0 ?: static::AWS_CREDENTIAL_CACHE_DURATION;
            Craft::$app->cache->set($tokenKey, $credentials->serialize(), $cacheDuration);
        }

        $config['credentials'] = $credentials;

        return new CloudFrontClient($config);
    }

    /**
     * Writes a new JSON file and returns its hash.
     *
     * @param string $path The path to save the content (can contain a %hash% tag)
     * @param array $data The data to write
     * @param array $oldPaths Array of existing files that should be deleted
     * @param bool $isConsole Whether this is a console request
     *
     * @return string
     */
    private function _writeHashedJsonFile(string $path, array $data, &$oldPaths, bool $isConsole): string
    {
        $content = Json::encode($data);
        $hash = hash('sha256', $content);
        $path = str_replace('%hash%', $hash, $path);
        $fullPath = "$this->composerWebroot/$path";

        // If nothing's changed, we're done
        if (file_exists($fullPath)) {
            return $hash;
        }

        // Mark any existing files in there for deletion
        $dir = dirname($fullPath);
        if (is_dir($dir) && ($handle = opendir($dir))) {
            while (($file = readdir($handle)) !== false) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                $oldPaths[] = $dir . '/' . $file;
            }
            closedir($handle);
        }

        $this->_writeFile($path, $content, $isConsole);

        return $hash;
    }

    /**
     * Writes a JSON file.
     *
     * @param string $path The path relative to the webroot
     * @param array $data The data to be JSON-encoded and saved
     * @param bool $isConsole Whether this is a console request
     */
    private function _writeJsonFile(string $path, array $data, bool $isConsole)
    {
        $fullPath = "$this->composerWebroot/$path";
        $content = Json::encode($data);

        // Don't overwrite it if nothing is changing
        $exists = file_exists($fullPath);
        if ($exists && sha1($content) === sha1_file($fullPath)) {
            return;
        }

        $this->_writeFile($path, $content, $isConsole);

        if ($exists && $this->cfDistributionId) {
            $this->_invalidateCloudFrontPath("/$path");
        }
    }

    /**
     * Writes a file
     *
     * @param string $path
     * @param string $content
     * @param bool $isConsole
     */
    private function _writeFile(string $path, string $content, bool $isConsole)
    {
        $fullPath = "$this->composerWebroot/$path";

        Craft::info("Writing JSON file to $path", __METHOD__);
        if ($isConsole) {
            Console::stdout("> Writing ");
            Console::stdout($path, Console::FG_CYAN);
            Console::stdout(' ... ');
        }

        try {
            FileHelper::writeToFile($fullPath, $content);
            if ($isConsole) {
                Console::stdout('done' . PHP_EOL, Console::FG_GREEN);
            }
        } catch (\Throwable $e) {
            Craft::error($e->getMessage(), __METHOD__);
            if ($isConsole) {
                Console::stdout("error: {$e->getMessage()}" . PHP_EOL, Console::FG_RED);
            }
        }
    }
}
