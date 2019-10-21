<?php

namespace craftnet\controllers\api;

use Composer\Semver\Comparator;
use Craft;
use craft\elements\User;
use craft\errors\InvalidPluginException;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use craft\helpers\HtmlPurifier;
use craft\helpers\Json;
use craft\web\Controller;
use craftnet\cms\CmsLicense;
use craftnet\cms\CmsLicenseManager;
use craftnet\developers\UserBehavior;
use craftnet\errors\ExpiredTokenException;
use craftnet\errors\LicenseNotFoundException;
use craftnet\errors\ValidationException;
use craftnet\helpers\KeyHelper;
use craftnet\Module;
use craftnet\oauthserver\Module as OauthServer;
use craftnet\plugins\Plugin;
use craftnet\plugins\PluginEdition;
use craftnet\plugins\PluginLicense;
use JsonSchema\Validator;
use stdClass;
use yii\base\Exception;
use yii\base\InvalidArgumentException;
use yii\base\Model;
use yii\base\UserException;
use yii\db\Expression;
use yii\helpers\Markdown;
use yii\validators\EmailValidator;
use yii\web\BadRequestHttpException;
use yii\web\Controller as YiiController;
use yii\web\HttpException;
use yii\web\Response;

/**
 * Class BaseController
 *
 * @property Module $module
 */
abstract class BaseApiController extends Controller
{
    const ERROR_CODE_INVALID = 'invalid';
    const ERROR_CODE_MISSING = 'missing';
    const ERROR_CODE_MISSING_FIELD = 'missing_field';
    const ERROR_CODE_EXISTS = 'already_exists';

    const LICENSE_STATUS_VALID = 'valid';
    const LICENSE_STATUS_INVALID = 'invalid';
    const LICENSE_STATUS_MISMATCHED = 'mismatched';
    const LICENSE_STATUS_ASTRAY = 'astray';

    /**
     * @inheritdoc
     */
    protected $allowAnonymous = true;

    /**
     * @inheritdoc
     */
    public $enableCsrfValidation = false;

    /**
     * The API request ID, if there is one.
     *
     * @var int|null
     */
    public $requestId;

    /**
     * The installed Craft version.
     *
     * @var string|null
     */
    public $cmsVersion;

    /**
     * The installed Craft edition.
     *
     * @var string|null
     */
    public $cmsEdition;

    /**
     * The installed plugins.
     *
     * @var Plugin[]
     */
    public $plugins = [];

    /**
     * The installed plugin versions.
     *
     * @var string[]
     */
    public $pluginVersions = [];

    /**
     * The installed plugin editions.
     *
     * @var string[]
     */
    public $pluginEditions = [];

    /**
     * The Craft license associated with this request.
     *
     * @var CmsLicense[]
     */
    public $cmsLicenses = [];

    /**
     * The plugin licenses associated with this request.
     *
     * @var PluginLicense[]
     */
    public $pluginLicenses = [];

    /**
     * The plugin editions the plugin licenses are set to.
     *
     * @var PluginEdition[]
     */
    public $pluginLicenseEditions = [];

    /**
     * The plugin license statuses.
     *
     * @var string[]
     */
    public $pluginLicenseStatuses = [];

    /**
     * @var array
     */
    private $_logRequestKeys = [];

    /**
     * @return array
     */
    public function getLogRequestKeys(): array
    {
        return $this->_logRequestKeys;
    }

    /**
     * @param $key
     * @param null $pluginHandle
     */
    public function addLogRequestKey($key, $pluginHandle = null)
    {
        if (!$pluginHandle) {
            $pluginHandle = 'craft';
        }

        $this->_logRequestKeys[$pluginHandle] = $key;
    }

    /**
     * @inheritdoc
     */
    public function beforeAction($action)
    {
        // if the request is authenticated, set their identity
        if (($user = $this->getAuthUser()) !== null) {
            Craft::$app->getUser()->setIdentity($user);
        }

        return parent::beforeAction($action);
    }

    /**
     * @inheritdoc
     */
    public function runAction($id, $params = []): Response
    {
        $request = Craft::$app->getRequest();
        $requestHeaders = $request->getHeaders();
        $response = Craft::$app->getResponse();
        $responseHeaders = $response->getHeaders();
        $identity = $requestHeaders->get('X-Craft-User-Email') ?: 'anonymous';
        $db = Craft::$app->getDb();

        // was system info provided?
        if ($requestHeaders->has('X-Craft-System')) {
            foreach (explode(',', $requestHeaders->get('X-Craft-System')) as $info) {
                list($name, $installed) = array_pad(explode(':', $info, 2), 2, null);
                if ($installed !== null) {
                    list($version, $edition) = array_pad(explode(';', $installed, 2), 2, null);
                } else {
                    $version = null;
                    $edition = null;
                }

                if ($name === 'craft') {
                    $this->cmsVersion = $version;
                    $this->cmsEdition = $edition;
                } else if (strncmp($name, 'plugin-', 7) === 0) {
                    $pluginHandle = substr($name, 7);
                    $this->pluginVersions[$pluginHandle] = $version;
                    $this->pluginEditions[$pluginHandle] = $edition;
                }
            }

            if (!empty($this->pluginVersions)) {
                $this->plugins = Plugin::find()
                    ->handle(array_keys($this->pluginVersions))
                    ->with(['editions'])
                    ->indexBy('handle')
                    ->all();
            }
        }

        $e = null;
        /** @var CmsLicense|null $cmsLicense */
        $cmsLicense = null;

        try {
            $cmsLicenseKey = $requestHeaders->get('X-Craft-License');
            if ($cmsLicenseKey === '🙏') {
                $cmsLicense = $this->cmsLicenses[] = $this->createCmsLicense();
                $responseHeaders
                    ->set('X-Craft-License', $cmsLicense->key)
                    ->set('X-Craft-License-Status', self::LICENSE_STATUS_VALID)
                    ->set('X-Craft-License-Domain', $cmsLicense->domain)
                    ->set('X-Craft-License-Edition', $cmsLicense->editionHandle);

                // was a host provided with the request?
                if ($requestHeaders->has('X-Craft-Host')) {
                    $responseHeaders->set('X-Craft-Allow-Trials', (string)($cmsLicense->domain === null));
                }
            } else if ($cmsLicenseKey !== null) {
                try {
                    $cmsLicenseManager = $this->module->getCmsLicenseManager();
                    $cmsLicense = $this->cmsLicenses[] = $cmsLicenseManager->getLicenseByKey($cmsLicenseKey);
                    $cmsLicenseStatus = self::LICENSE_STATUS_VALID;
                    $cmsLicenseDomain = $oldCmsLicenseDomain = $cmsLicense->domain ? $cmsLicenseManager->normalizeDomain($cmsLicense->domain) : null;

                    // was a host provided with the request?
                    if (($host = $requestHeaders->get('X-Craft-Host')) !== null) {
                        // is it a public domain?
                        if (($domain = $cmsLicenseManager->normalizeDomain($host)) !== null) {
                            if ($cmsLicenseDomain !== null) {
                                if ($domain !== $cmsLicenseDomain) {
                                    $cmsLicenseStatus = self::LICENSE_STATUS_MISMATCHED;
                                }
                            } else {
                                // tie the license to this domain
                                $cmsLicense->domain = $cmsLicenseDomain = $domain;
                            }
                        }

                        $responseHeaders->set('X-Craft-Allow-Trials', (string)($domain === null));
                    }

                    // has Craft gone past its current allowed version?
                    if (
                        $this->cmsVersion !== null &&
                        $cmsLicense->expirable &&
                        $cmsLicenseStatus === self::LICENSE_STATUS_VALID &&
                        (
                            !$cmsLicense->lastAllowedVersion ||
                            Comparator::greaterThan($this->cmsVersion, $cmsLicense->lastAllowedVersion)
                        )
                    ) {
                        // we only have a problem with that if the license is expired
                        if ($cmsLicense->expired) {
                            $cmsLicenseStatus = self::LICENSE_STATUS_ASTRAY;
                        } else {
                            $cmsLicense->lastAllowedVersion = $this->cmsVersion;
                        }
                    }

                    $responseHeaders->set('X-Craft-License-Status', $cmsLicenseStatus);
                    $responseHeaders->set('X-Craft-License-Domain', $cmsLicenseDomain);
                    $responseHeaders->set('X-Craft-License-Edition', $cmsLicense->editionHandle);

                    if ($cmsLicense->expirable) {
                        $responseHeaders->set('X-Craft-License-Expired', (string)(int)$cmsLicense->expired);

                        $expiryDate = $cmsLicense->getExpiryDate();

                        if ($expiryDate) {
                            $responseHeaders->set('X-Craft-License-Expires-On', $expiryDate->format(\DateTime::ATOM));
                        }
                    }

                    // update the license
                    $cmsLicense->lastActivityOn = new \DateTime('now', new \DateTimeZone('UTC'));
                    if ($this->cmsVersion !== null) {
                        $cmsLicense->lastVersion = $this->cmsVersion;
                    }
                    if ($this->cmsEdition !== null) {
                        $cmsLicense->lastEdition = $this->cmsEdition;
                    }
                    $cmsLicenseManager->saveLicense($cmsLicense, false);

                    // update the history
                    if ($cmsLicenseDomain !== $oldCmsLicenseDomain) {
                        $cmsLicenseManager->addHistory($cmsLicense->id, "tied to domain {$cmsLicenseDomain} by {$identity}");
                    }
                } catch (LicenseNotFoundException $e) {
                    $responseHeaders->set('X-Craft-License-Status', self::LICENSE_STATUS_INVALID);
                    $e = null;
                }
            }

            // collect the plugin licenses & their editions
            if (($pluginLicenseKeys = $requestHeaders->get('X-Craft-Plugin-Licenses')) !== null) {
                $pluginLicenseManager = $this->module->getPluginLicenseManager();
                foreach (explode(',', $pluginLicenseKeys) as $pluginLicenseInfo) {
                    list($pluginHandle, $pluginLicenseKey) = explode(':', $pluginLicenseInfo);
                    try {
                        $pluginLicense = $pluginLicenseManager->getLicenseByKey($pluginLicenseKey, $pluginHandle, true);
                        // Ignore it if for a disabled edition
                        /** @var PluginEdition $pluginEdition */
                        $pluginEdition = $pluginLicense->getEdition();
                        if ($pluginEdition->enabled) {
                            $this->pluginLicenses[$pluginHandle] = $pluginLicense;
                            $this->pluginLicenseEditions[$pluginHandle] = $pluginLicense->getEdition();
                        }
                    } catch (LicenseNotFoundException $e) {
                        $this->pluginLicenseStatuses[$pluginHandle] = self::LICENSE_STATUS_INVALID;
                        $e = null;
                    } catch (InvalidPluginException $e) {
                        // Just ignore it
                        $e = null;
                    }
                }
            }

            // set the plugin license statuses
            foreach ($this->plugins as $pluginHandle => $plugin) {
                // ignore if they're using an invalid license key
                if (isset($this->pluginLicenseStatuses[$pluginHandle]) && $this->pluginLicenseStatuses[$pluginHandle] === self::LICENSE_STATUS_INVALID) {
                    continue;
                }

                // no license key yet?
                if (!isset($this->pluginLicenses[$pluginHandle])) {
                    // should there be?
                    $edition = $plugin->getEditions()[0];
                    if (isset($this->pluginEditions[$pluginHandle])) {
                        try {
                            $edition = $plugin->getEdition($this->pluginEditions[$pluginHandle]);
                        } catch (InvalidArgumentException $e) {
                            // just assume the first
                            $e = null;
                        }
                    }
                    if ($edition->price != 0) {
                        $this->pluginLicenseStatuses[$pluginHandle] = self::LICENSE_STATUS_INVALID;
                    }
                    continue;
                }

                $pluginLicense = $this->pluginLicenses[$pluginHandle];
                $pluginVersion = $this->pluginVersions[$pluginHandle];
                $pluginLicenseStatus = self::LICENSE_STATUS_VALID;
                $oldCmsLicenseId = $pluginLicense->cmsLicenseId;

                if ($cmsLicense !== null) {
                    if ($pluginLicense->cmsLicenseId) {
                        if ($pluginLicense->cmsLicenseId != $cmsLicense->id) {
                            $pluginLicenseStatus = self::LICENSE_STATUS_MISMATCHED;
                        }
                    } else {
                        // tie the license to this Craft license
                        $pluginLicense->cmsLicenseId = $cmsLicense->id;
                    }
                }

                // has the plugin gone past its current allowed version?
                if (
                    $pluginVersion !== null &&
                    $pluginLicense->expirable &&
                    $pluginLicenseStatus === self::LICENSE_STATUS_VALID &&
                    (
                        !$pluginLicense->lastAllowedVersion ||
                        Comparator::greaterThan($pluginVersion, $pluginLicense->lastAllowedVersion)
                    )
                ) {
                    // we only have a problem with that if the license is expired
                    if ($pluginLicense->expired) {
                        $pluginLicenseStatus = self::LICENSE_STATUS_ASTRAY;
                    } else {
                        $pluginLicense->lastAllowedVersion = $pluginVersion;
                    }
                }

                $this->pluginLicenseStatuses[$pluginHandle] = $pluginLicenseStatus;

                // update the license
                $pluginLicense->lastActivityOn = new \DateTime('now', new \DateTimeZone('UTC'));
                if ($pluginVersion !== null) {
                    $pluginLicense->lastVersion = $pluginVersion;
                }
                $pluginLicenseManager->saveLicense($pluginLicense, false);

                // update the history
                if ($pluginLicense->cmsLicenseId !== $oldCmsLicenseId) {
                    $pluginLicenseManager->addHistory($pluginLicense->id, "attached to Craft license {$cmsLicense->shortKey} by {$identity}");
                }
            }

            // set the X-Craft-Plugin-License-Statuses header
            if (!empty($this->pluginLicenseStatuses)) {
                $pluginLicenseStatuses = [];
                foreach ($this->pluginLicenseStatuses as $pluginHandle => $pluginLicenseStatus) {
                    $pluginLicenseStatuses[] = "{$pluginHandle}:{$pluginLicenseStatus}";
                }
                $responseHeaders->set('X-Craft-Plugin-License-Statuses', implode(',', $pluginLicenseStatuses));
            }

            // set the X-Craft-Plugin-License-Editions header
            if (!empty($this->pluginLicenseEditions)) {
                $pluginLicenseEditions = [];
                foreach ($this->pluginLicenseEditions as $pluginHandle => $pluginEdition) {
                    // Treat all Freeform < v3 & Sprout Forms < v3.2 licenses as "standard" edition
                    if (
                        ($pluginHandle === 'freeform' && Comparator::lessThan($this->pluginVersions[$pluginHandle], 3)) ||
                        ($pluginHandle === 'sprout-forms' && Comparator::lessThan($this->pluginVersions[$pluginHandle], '3.2')) ||
                        ($pluginHandle === 'sprout-seo' && Comparator::lessThan($this->pluginVersions[$pluginHandle], '4.1')) ||
                        ($pluginHandle === 'guide' && Comparator::lessThan($this->pluginVersions[$pluginHandle], 2))
                    ) {
                        $pluginLicenseEditions[] = "{$pluginHandle}:standard";
                    } else {
                        $pluginLicenseEditions[] = "{$pluginHandle}:{$pluginEdition->handle}";
                    }
                }
                $responseHeaders->set('X-Craft-Plugin-License-Editions', implode(',', $pluginLicenseEditions));
            }

            if (($result = YiiController::runAction($id, $params)) instanceof Response) {
                $response = $result;
            }
        } catch (\Throwable $e) {
            // log it and keep going
            Craft::$app->getErrorHandler()->logException($e);
            $response->setStatusCode($e instanceof HttpException && $e->statusCode ? $e->statusCode : 500);
        }

        $timestamp = Db::prepareDateForDb(new \DateTime());

        // should we update our installed plugin records?
        if ($this->cmsVersion !== null && $cmsLicense !== null) {
            // delete any installedplugins rows where lastActivity > 30 days ago
            $db->createCommand()
                ->delete('craftnet_installedplugins', [
                    'and',
                    ['craftLicenseKey' => $cmsLicense->key],
                    ['<', 'lastActivity', Db::prepareDateForDb(new \DateTime('-30 days'))],
                ])
                ->execute();

            foreach ($this->plugins as $plugin) {
                $db->createCommand()
                    ->upsert('craftnet_installedplugins', [
                        'craftLicenseKey' => $cmsLicense->key,
                        'pluginId' => $plugin->id,
                    ], [
                        'lastActivity' => $timestamp,
                    ], [], false)
                    ->execute();

                // Update the plugin's active installs count
                $db->createCommand()
                    ->update('craftnet_plugins', [
                        'activeInstalls' => new Expression('(select count(*) from [[craftnet_installedplugins]] where [[pluginId]] = :pluginId)', ['pluginId' => $plugin->id]),
                    ], [
                        'id' => $plugin->id,
                    ])
                    ->execute();
            }
        }

        // log the request
        $db->createCommand()
            ->insert('apilog.requests', [
                'method' => $request->getMethod(),
                'uri' => $request->getUrl(),
                'ip' => $request->getUserIP(),
                'action' => $this->getUniqueId() . '/' . $id,
                'body' => $request->getRawBody(),
                'system' => $requestHeaders->get('X-Craft-System'),
                'platform' => $requestHeaders->get('X-Craft-Platform'),
                'host' => $requestHeaders->get('X-Craft-Host'),
                'userEmail' => $requestHeaders->get('X-Craft-User-Email'),
                'userIp' => $requestHeaders->get('X-Craft-User-Ip'),
                'timestamp' => $timestamp,
                'responseCode' => $response->getStatusCode(),
            ], false)
            ->execute();

        // get the request ID
        $this->requestId = (int)$db->getLastInsertID('apilog.requests');

        // log any licenses associated with the request
        foreach ($this->cmsLicenses as $cmsLicense) {
            $db->createCommand()
                ->insert('apilog.request_cmslicenses', [
                    'requestId' => $this->requestId,
                    'licenseId' => $cmsLicense->id,
                ], false)
                ->execute();
        }
        foreach ($this->pluginLicenses as $pluginLicense) {
            $db->createCommand()
                ->insert('apilog.request_pluginlicenses', [
                    'requestId' => $this->requestId,
                    'licenseId' => $pluginLicense->id,
                ], false)
                ->execute();
        }

        // if there was an exception, log it and return the error response
        if ($e !== null) {
            /** @var \Throwable $logException */
            $logException = $e;

            $statusCode = $response->getStatusCode();

            // Don't ever send an error response with a status code of 200
            if ($statusCode === 200) {
                $response->setStatusCode($statusCode = 500);
            }

            $sendErrorEmail = $statusCode >= 500 && $statusCode < 600;

            if ($sendErrorEmail) {
                $body = <<<EOL
- Request ID: {$this->requestId}
- Method: {$request->getMethod()}
- URI: {$request->getUrl()}
- IP: {$request->getUserIP()}
- Action: {$this->getUniqueId()}
- System: {$requestHeaders->get('X-Craft-System')}
- Platform: {$requestHeaders->get('X-Craft-Platform')}
- Host: {$requestHeaders->get('X-Craft-Host')}
- User email: {$requestHeaders->get('X-Craft-User-Email')}
- User IP: {$requestHeaders->get('X-Craft-User-Ip')}
- Response code: {$response->getStatusCode()}

Body:

```
{$request->getRawBody()}
```
EOL;
            } else {
                $body = '';
            }

            do {
                $exceptionType = get_class($logException);
                $stackTrace = $logException->getTraceAsString();

                if ($logException instanceof ValidationException) {
                    $errorJson = Json::encode($logException->errors, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                } else {
                    $errorJson = null;
                }

                $db->createCommand()
                    ->insert('apilog.request_errors', [
                        'requestId' => $this->requestId,
                        'type' => $exceptionType,
                        'message' => $logException->getMessage() . ($errorJson ? "\n\n" . $errorJson : ''),
                        'stackTrace' => $stackTrace,
                    ], false)
                    ->execute();

                if ($sendErrorEmail) {
                    $body .= <<<EOL


---

- Type: {$exceptionType}
- Message: {$logException->getMessage()}

Stack trace:

```
{$stackTrace}
```
EOL;

                    if ($errorJson) {
                        $body .= <<<EOD


Validation errors:

```
{$errorJson}
```
EOD;
                    }
                }

                // Cue up the previous exception
                $logException = $logException->getPrevious();

                if ($logException && $sendErrorEmail) {
                    $body .= <<<EOL


---

**Previous exception:**


EOL;
                }
            } while ($logException !== null);

            if ($sendErrorEmail) {
                try {
                    Craft::$app->getMailer()->compose()
                        ->setSubject('Craftnet API Error')
                        ->setTextBody($body)
                        ->setHtmlBody(Markdown::process($body, 'gfm'))
                        ->setTo(explode(',', getenv('API_ERROR_RECIPIENTS')))
                        ->send();
                } catch (\Throwable $e) {
                    // Just log and move on.
                    Craft::error('There was a problem sending the API error email: ' . $e->getMessage(), __METHOD__);
                }
            }

            // assemble and return the response
            $data = [
                'message' => $e instanceof UserException && $e->getMessage() ? $e->getMessage() : 'Server Error',
            ];
            if ($e instanceof ValidationException) {
                $data['errors'] = $e->errors;
            }

            return $this->asJson($data);
        }

        return $response;
    }

    /**
     * Returns the JSON-decoded request body.
     *
     * @param string|null $schema JSON schema to validate the body with (optional)
     *
     * @return stdClass|array
     * @throws BadRequestHttpException if the request body isn't valid JSON
     * @throws ValidationException if the data doesn't validate
     */
    protected function getPayload(string $schema = null)
    {
        $body = Craft::$app->getRequest()->getRawBody();
        try {
            $payload = (object)Json::decode($body, false);
        } catch (InvalidArgumentException $e) {
            throw new BadRequestHttpException('Request body is not valid JSON', 0, $e);
        }

        if ($schema !== null && !$this->validatePayload($payload, $schema, $errors)) {
            throw new ValidationException($errors);
        }

        return $payload;
    }

    /**
     * Validates a payload against a JSON schema.
     *
     * @param stdClass $payload
     * @param string $schema
     * @param array $errors
     * @param string|null $paramPrefix
     * @return bool
     */
    protected function validatePayload(stdClass $payload, string $schema, &$errors = [], string $paramPrefix = null)
    {
        $validator = new Validator();
        $path = Craft::getAlias("@root/json-schemas/{$schema}.json");
        $validator->validate($payload, (object)['$ref' => 'file://' . $path]);

        if (!$validator->isValid()) {
            foreach ($validator->getErrors() as $error) {
                $errors[] = [
                    'param' => ($paramPrefix ? $paramPrefix . '.' : '') . $error['property'],
                    'message' => $error['message'],
                    'code' => self::ERROR_CODE_INVALID,
                ];
            }

            return false;
        }

        return true;
    }

    /**
     * Returns an array of validation errors for a ValdiationException based on a model's validation errors
     *
     * @param Model $model
     * @param string|null $paramPrefix
     * @return array
     */
    protected function modelErrors(Model $model, string $paramPrefix = null): array
    {
        $errors = [];

        foreach ($model->getErrors() as $attr => $attrErrors) {
            foreach ($attrErrors as $error) {
                $errors[] = [
                    'param' => ($paramPrefix !== null ? $paramPrefix . '.' : '') . $attr,
                    'message' => $error,
                    'code' => self::ERROR_CODE_INVALID,
                ];
            }
        }

        return $errors;
    }

    /**
     * @param Plugin $plugin
     * @param bool $fullDetails
     *
     * @return array
     * @throws \craftnet\errors\MissingTokenException
     * @throws \yii\base\InvalidConfigException
     */
    protected function transformPlugin(Plugin $plugin, bool $fullDetails = true): array
    {
        $icon = $plugin->getIcon();
        $developer = $plugin->getDeveloper();

        // Return data
        $data = [
            'id' => $plugin->id,
            'iconUrl' => $icon ? $icon->getUrl() . '?' . $icon->dateModified->getTimestamp() : null,
            'handle' => $plugin->handle,
            'name' => strip_tags($plugin->name),
            'shortDescription' => $plugin->shortDescription,
            'currency' => 'USD',
            'developerId' => $developer->id,
            'developerName' => strip_tags($developer->getDeveloperName()),
            'categoryIds' => ArrayHelper::getColumn($plugin->getCategories(), 'id'),
            'keywords' => ($plugin->keywords ? array_map('trim', explode(',', $plugin->keywords)) : []),
            'version' => $plugin->latestVersion,
            'activeInstalls' => $plugin->activeInstalls,
            'packageName' => $plugin->packageName,
            'lastUpdate' => ($plugin->latestVersionTime ?? $plugin->dateUpdated)->format(\DateTime::ATOM),
        ];

        foreach ($plugin->getEditions() as $edition) {
            $data['editions'][] = [
                'id' => $edition->id,
                'name' => $edition->name,
                'handle' => $edition->handle,
                'price' => (float)$edition->price ?: null,
                'renewalPrice' => (float)$edition->renewalPrice ?: null,
                'features' => $edition->features ?? [],
            ];
        }

        if ($fullDetails) {
            // Screenshots
            $screenshotUrls = [];
            $thumbnailUrls = [];
            $screenshotIds = [];

            foreach ($plugin->getScreenshots() as $screenshot) {
                $screenshotUrls[] = $screenshot->getUrl() . '?' . $screenshot->dateModified->getTimestamp();
                $thumbnailUrls[] = $screenshot->getUrl([
                        'height' => 300,
                    ]) . '?' . $screenshot->dateModified->getTimestamp();
                $screenshotIds[] = $screenshot->getId();
            }

            $longDescription = Markdown::process($plugin->longDescription, 'gfm');
            $longDescription = HtmlPurifier::process($longDescription);

            $data['compatibility'] = 'Craft 3';
            $data['status'] = $plugin->status;
            $data['iconId'] = $plugin->iconId;
            $data['longDescription'] = $longDescription;
            $data['documentationUrl'] = $plugin->documentationUrl;
            $data['changelogUrl'] = $plugin->getPackage()->getVcs()->getChangelogUrl();
            $data['repository'] = $plugin->repository;
            $data['license'] = $plugin->license;
            $data['developerUrl'] = $developer->developerUrl;
            $data['screenshotUrls'] = $screenshotUrls;
            $data['thumbnailUrls'] = $thumbnailUrls;
            $data['screenshotIds'] = $screenshotIds;
        }

        return $data;
    }

    /**
     * Returns the authorized user, if any.
     *
     * @return User|UserBehavior|null
     * @throws BadRequestHttpException
     */
    protected function getAuthUser()
    {
        if ($user = Craft::$app->getUser()->getIdentity()) {
            return $user;
        }

        try {
            if (
                ($accessToken = OauthServer::getInstance()->getAccessTokens()->getAccessTokenFromRequest()) &&
                $accessToken->userId &&
                $user = User::findOne($accessToken->userId)
            ) {
                return $user;
            }
        } catch (ExpiredTokenException $e) {
            throw new BadRequestHttpException($e->getMessage());
        } catch (\InvalidArgumentException $e) {
        }

        list ($username, $password) = Craft::$app->getRequest()->getAuthCredentials();

        if (!$username) {
            return null;
        }

        if (!$password) {
            throw new BadRequestHttpException('Invalid Credentials');
        }

        /** @var User|UserBehavior|null $user */
        $user = Craft::$app->getUsers()->getUserByUsernameOrEmail($username);

        if (
            $user === null ||
            $user->apiToken === null ||
            $user->getStatus() !== User::STATUS_ACTIVE ||
            Craft::$app->getSecurity()->validatePassword($password, $user->apiToken) === false
        ) {
            throw new BadRequestHttpException('Invalid Credentials');
        }

        return $user;
    }

    /**
     * Returns the offset and limit that should be used for list requests.
     *
     * @param int $page
     * @param int $perPage
     * @return int[]
     */
    protected function page2offset(int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = min(max(1, $perPage), 100);
        $offset = ($page - 1) * $perPage;
        return [$offset, $perPage];
    }

    /**
     * Creates a new CMS license.
     *
     * @return CmsLicense
     * @throws BadRequestHttpException
     * @throws Exception
     */
    protected function createCmsLicense(): CmsLicense
    {
        $headers = Craft::$app->getRequest()->getHeaders();
        if (($email = $headers->get('X-Craft-User-Email')) === null) {
            throw new BadRequestHttpException('Missing X-Craft-User-Email Header');
        }
        if ((new EmailValidator())->validate($email, $error) === false) {
            throw new BadRequestHttpException($error);
        }

        $license = new CmsLicense([
            'expirable' => true,
            'expired' => false,
            'autoRenew' => false,
            'editionHandle' => CmsLicenseManager::EDITION_SOLO,
            'email' => $email,
            'domain' => $headers->get('X-Craft-Host'),
            'key' => KeyHelper::generateCmsKey(),
            'lastEdition' => $this->cmsEdition,
            'lastVersion' => $this->cmsVersion,
            'lastActivityOn' => new \DateTime('now', new \DateTimeZone('UTC')),
        ]);

        $manager = $this->module->getCmsLicenseManager();
        if (!$manager->saveLicense($license)) {
            throw new Exception('Could not create CMS license: ' . implode(', ', $license->getErrorSummary(true)));
        }

        $note = "created by {$license->email}";
        if ($license->domain !== null) {
            $note .= " for domain {$license->domain}";
        }
        $this->module->getCmsLicenseManager()->addHistory($license->id, $note);

        return $license;
    }
}
