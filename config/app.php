<?php

use craft\helpers\App;
use craftnet\services\Oauth;

return [
    '*' => [
        'id' => 'craftnet',
        'bootstrap' => [
            'craftnet',
            'oauth-server',
            'queue',
        ],
        'modules' => [
            'craftnet' => [
                'class' => \craftnet\Module::class,
                'components' => [
                    'cmsLicenseManager' => [
                        'class' => craftnet\cms\CmsLicenseManager::class,
                        'devDomains' => require __DIR__ . '/dev-domains.php',
                        'publicDomainSuffixes' => require __DIR__ . '/public-domain-suffixes.php',
                        'devSubdomainWords' => [
                            'acc',
                            'acceptance',
                            'craftdemo',
                            'dev',
                            'integration',
                            'loc',
                            'local',
                            'qa',
                            'sandbox',
                            'stage',
                            'staging',
                            'systest',
                            'test',
                            'testing',
                            'uat',
                        ]
                    ],
                    'invoiceManager' => [
                        'class' => craftnet\invoices\InvoiceManager::class,
                    ],
                    'pluginLicenseManager' => [
                        'class' => craftnet\plugins\PluginLicenseManager::class,
                    ],
                    'packageManager' => [
                        'class' => craftnet\composer\PackageManager::class,
                        'githubFallbackTokens' => App::env('GITHUB_FALLBACK_TOKENS'),
                        'requirePluginVcsTokens' => false,
                    ],
                    'payoutManager' => [
                        'class' => \craftnet\payouts\PayoutManager::class,
                    ],
                    'jsonDumper' => [
                        'class' => craftnet\composer\JsonDumper::class,
                        'composerWebroot' => App::env('COMPOSER_WEBROOT'),
                    ],
                    'oauth' => [
                        'class' => Oauth::class,
                        'appTypes' => [
                            Oauth::PROVIDER_GITHUB => [
                                'class' => 'Github',
                                'oauthClass' => League\OAuth2\Client\Provider\Github::class,
                                'clientIdKey' => App::env('GITHUB_APP_CLIENT_ID'),
                                'clientSecretKey' => App::env('GITHUB_APP_CLIENT_SECRET'),
                                'scope' => ['user:email', 'write:repo_hook', 'public_repo'],
                            ],
                            Oauth::PROVIDER_BITBUCKET => [
                                'class' => 'Bitbucket',
                                'oauthClass' => Stevenmaguire\OAuth2\Client\Provider\Bitbucket::class,
                                'clientIdKey' => App::env('BITBUCKET_APP_CLIENT_ID'),
                                'clientSecretKey' => App::env('BITBUCKET_APP_CLIENT_SECRET'),
                                'scope' => 'account',
                            ],
                        ]
                    ],
                    'saleManager' => [
                        'class' => craftnet\sales\SaleManager::class,
                    ]
                ]
            ],
            'oauth-server' => [
                'class' => craftnet\oauthserver\Module::class,
            ],
        ],
        'components' => [
            'errorHandler' => [
                'memoryReserveSize' => 1024000
            ],
            'schedule' => [
                'class' => omnilight\scheduling\Schedule::class,
                'cliScriptName' => 'craft',
            ],
        ],
    ],
    'prod' => [
        'bootstrap' => [
            'dlq',
            '\superbig\bugsnag\Bootstrap',
        ],
        'components' => [
            'redis' => [
                'class' => yii\redis\Connection::class,
                'hostname' => App::env('ELASTICACHE_HOSTNAME'),
                'port' => App::env('ELASTICACHE_PORT'),
                'database' => 0,
            ],
            'cache' => [
                'class' => yii\redis\Cache::class,
            ],
            'mutex' => [
                'class' => \yii\redis\Mutex::class,
            ],
            'queue' => [
                'class' => \yii\queue\sqs\Queue::class,
                'url' => App::env('SQS_URL'),
                'key' => App::env('AWS_ACCESS_KEY_ID'),
                'secret' => App::env('AWS_SECRET_ACCESS_KEY'),
                'region' => App::env('REGION'),
            ],
            'dlq' => [
                'class' => \yii\queue\sqs\Queue::class,
                'url' => App::env('SQS_DEAD_LETTER_URL'),
                'key' => App::env('AWS_ACCESS_KEY_ID'),
                'secret' => App::env('AWS_SECRET_ACCESS_KEY'),
                'region' => App::env('REGION'),
            ],
            'partnerQueue' => [
                'class' => \yii\queue\sqs\Queue::class,
                'url' => App::env('PARTNER_QUEUE_URL'),
                'region' => App::env('PARTNER_QUEUE_REGION')
            ],
            'session' => function() {
                $config = craft\helpers\App::sessionConfig();
                $config['class'] = yii\redis\Session::class;
                $stateKeyPrefix = md5('Craft.' . craft\web\Session::class . '.' . Craft::$app->id);
                $config['flashParam'] = $stateKeyPrefix . '__flash';
                $config['authAccessParam'] = $stateKeyPrefix . '__auth_access';
                return Craft::createObject($config);
            },
            'log' => function() {
                $logFileName = Craft::$app->getRequest()->getIsConsoleRequest() ? 'console.log' : 'web.log';
                if (!YII_DEBUG) {
                    $levels = yii\log\Logger::LEVEL_ERROR | yii\log\Logger::LEVEL_WARNING;
                } else {
                    $levels = yii\log\Logger::LEVEL_ERROR | yii\log\Logger::LEVEL_WARNING | yii\log\Logger::LEVEL_INFO | yii\log\Logger::LEVEL_TRACE | yii\log\Logger::LEVEL_PROFILE;
                }
                return Craft::createObject([
                    'class' => yii\log\Dispatcher::class,
                    'targets' => [
                        [
                            'class' => craftnet\logs\DbTarget::class,
                            'logTable' => 'apilog.logs',
                            'levels' => $levels,
                        ],
                        [
                            'class' => craft\log\FileTarget::class,
                            'logFile' => App::env('CRAFT_STORAGE_PATH') . '/logs/' . $logFileName,
                            'levels' => $levels,
                        ],
                    ],
                ]);
            },
            'db' => function() {
                // Get the default component config
                $config = craft\helpers\App::dbConfig();

                // Use read/write query splitting
                // (https://www.yiiframework.com/doc/guide/2.0/en/db-dao#read-write-splitting)

                // Define the default config for replica DB connections
                $config['slaveConfig'] = [
                    'username' => App::env('DB_USER'),
                    'password' => App::env('DB_PASSWORD'),
                    'tablePrefix' => App::env('DB_TABLE_PREFIX'),
                    'attributes' => [
                        // Use a smaller connection timeout
                        PDO::ATTR_TIMEOUT => 10,
                    ],
                    'charset' => 'utf8',
                ];

                // Define the replica DB connections
                $config['slaves'] = [
                    [
                        'dsn' => App::env('DB_READ_DSN_1'),
                        'dsn' => App::env('DB_READ_DSN_2'),
                    ],
                ];

                // Instantiate and return it
                return Craft::createObject($config);
            },
        ],
    ],
    'dev' => [
        'components' => [
            'api' => function() {
                $client = Craft::createGuzzleClient([
                    'base_uri' => 'https://api.craftcms.test/v1/',
                    'verify' => false,
                    'query' => ['XDEBUG_SESSION_START' => 14076],
                ]);
                return new \craft\services\Api([
                    'client' => $client,
                ]);
            },
        ]
    ],
    'next' => [
        'components' => [
            'api' => function() {
                $client = Craft::createGuzzleClient([
                    'base_uri' => 'https://api.craftcms.next/v1/',
                    'verify' => false,
                    'query' => ['XDEBUG_SESSION_START' => 14076],
                ]);
                return new \craft\services\Api([
                    'client' => $client,
                ]);
            },
        ]
    ]
];
