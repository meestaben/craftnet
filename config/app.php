<?php

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
                            'loc',
                            'local',
                            'sandbox',
                            'stage',
                            'staging',
                            'test',
                            'testing',
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
                        'githubFallbackTokens' => getenv('GITHUB_FALLBACK_TOKENS'),
                        'requirePluginVcsTokens' => false,
                    ],
                    'jsonDumper' => [
                        'class' => craftnet\composer\JsonDumper::class,
                        'composerWebroot' => getenv('COMPOSER_WEBROOT'),
                    ],
                    'oauth' => [
                        'class' => Oauth::class,
                        'appTypes' => [
                            Oauth::PROVIDER_GITHUB => [
                                'class' => 'Github',
                                'oauthClass' => League\OAuth2\Client\Provider\Github::class,
                                'clientIdKey' => getenv('GITHUB_APP_CLIENT_ID'),
                                'clientSecretKey' => getenv('GITHUB_APP_CLIENT_SECRET'),
                                'scope' => ['user:email', 'write:repo_hook', 'public_repo'],
                            ],
                            Oauth::PROVIDER_BITBUCKET => [
                                'class' => 'Bitbucket',
                                'oauthClass' => Stevenmaguire\OAuth2\Client\Provider\Bitbucket::class,
                                'clientIdKey' => getenv('BITBUCKET_APP_CLIENT_ID'),
                                'clientSecretKey' => getenv('BITBUCKET_APP_CLIENT_SECRET'),
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
        'components' => [
            'redis' => [
                'class' => yii\redis\Connection::class,
                'hostname' => getenv('ELASTICACHE_HOSTNAME'),
                'port' => getenv('ELASTICACHE_PORT'),
                'database' => 0,
            ],
            'cache' => [
                'class' => yii\redis\Cache::class,
                'redis' => [
                    'hostname' => getenv('ELASTICACHE_HOSTNAME'),
                    'port' => getenv('ELASTICACHE_PORT'),
                    'database' => 0,
                ],
            ],
            'mutex' => [
                'class' => \yii\redis\Mutex::class,
            ],
            'queue' => [
                'class' => pixelandtonic\yii\queue\sqs\Queue::class,
                'url' => getenv('SQS_URL'),
                'client' => [
                    'region' => getenv('REGION'),
                    'version' => '2012-11-05',
                ]
            ],
            'partnerQueue' => [
                'class' => \yii\queue\sqs\Queue::class,
                'url' => getenv('PARTNER_QUEUE_URL'),
                'region' => getenv('PARTNER_QUEUE_REGION')
            ],
            'session' => function() {
                $stateKeyPrefix = md5('Craft.' . craft\web\Session::class . '.' . Craft::$app->id);

                /** @var yii\redis\Session $session */
                $session = Craft::createObject([
                    'class' => yii\redis\Session::class,
                    'flashParam' => $stateKeyPrefix . '__flash',
                    'name' => Craft::$app->getConfig()->getGeneral()->phpSessionName,
                    'cookieParams' => Craft::cookieConfig(),
                ]);

                $session->attachBehaviors([craft\behaviors\SessionBehavior::class]);
                $session->authAccessParam = $stateKeyPrefix . '__auth_access';
                return $session;
            },
            'log' => function() {
                $log = Craft::createObject([
                    'class' => yii\log\Dispatcher::class,
                    'targets' => [
                        [
                            'class' => craftnet\logs\DbTarget::class,
                            'logTable' => 'apilog.logs',
                            'levels' => !YII_DEBUG ? yii\log\Logger::LEVEL_ERROR | yii\log\Logger::LEVEL_WARNING : yii\log\Logger::LEVEL_ERROR | yii\log\Logger::LEVEL_WARNING | yii\log\Logger::LEVEL_INFO | yii\log\Logger::LEVEL_TRACE | yii\log\Logger::LEVEL_PROFILE,
                        ],
                        [
                            'class' => craft\log\FileTarget::class,
                            'logFile' => getenv('CRAFT_STORAGE_PATH') . '/logs/web.log',
                            'levels' => !YII_DEBUG ? yii\log\Logger::LEVEL_ERROR | yii\log\Logger::LEVEL_WARNING : yii\log\Logger::LEVEL_ERROR | yii\log\Logger::LEVEL_WARNING | yii\log\Logger::LEVEL_INFO | yii\log\Logger::LEVEL_TRACE | yii\log\Logger::LEVEL_PROFILE,
                        ],
                    ],
                ]);

                return $log;
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
