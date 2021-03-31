<?php

namespace craftnet\controllers\api\v1;

use craftnet\controllers\api\BaseApiController;
use craftnet\plugins\Plugin;
use yii\base\Exception;
use yii\web\BadRequestHttpException;
use yii\web\Response;

/**
 * Class CmsLicensesController
 */
class CmsLicensesController extends BaseApiController
{
    // Properties
    // =========================================================================

    public $defaultAction = 'create';

    // Public Methods
    // =========================================================================

    /**
     * Creates a new CMS license.
     *
     * @return Response
     * @throws BadRequestHttpException
     * @throws Exception
     */
    public function actionCreate(): Response
    {
        $license = $this->createCmsLicense();

        $responseHeaders = $this->response->getHeaders()
            ->set('X-Craft-License-Status', self::LICENSE_STATUS_VALID)
            ->set('X-Craft-License-Domain', $license->domain)
            ->set('X-Craft-License-Edition', $license->editionHandle);

        // was a host provided with the request?
        if ($this->request->getHeaders()->has('X-Craft-Host')) {
            $responseHeaders->set('X-Craft-Allow-Trials', (string)($license->domain === null));
        }

        // include this license in the request log
        $this->cmsLicenses[] = $license;

        return $this->asJson([
            'license' => $license->toArray(),
        ]);
    }

    /**
     * Retrieves a CMS license.
     *
     * @param string|null $include
     * @return Response
     * @throws BadRequestHttpException
     */
    public function actionGet(string $include = null): Response
    {
        if (empty($this->cmsLicenses)) {
            throw new BadRequestHttpException('Missing or invalid X-Craft-License Header');
        }

        $license = reset($this->cmsLicenses);
        $licenseInfo = $license->toArray();

        if ($include !== null) {
            $include = array_flip(explode(',', $include));
            if (isset($include['plugins'])) {
                $pluginLicenses = [];
                foreach ($license->getPluginLicenses() as $pluginLicense) {
                    $pluginLicenseInfo = $pluginLicense->toArray([], ['plugin.icon']);
                    if ($pluginLicense->expired) {
                        $pluginLicenseInfo['renewalUrl'] = $pluginLicense->getEditUrl();
                        $pluginLicenseInfo['renewalPrice'] = $pluginLicense->getEdition()->renewalPrice;
                        $pluginLicenseInfo['renewalCurrency'] = 'USD';
                    }
                    if ($this->cmsVersion) {
                        // Get the latest release that's compatible with their current Craft version
                        $pluginLicenseInfo['plugin']['latestVersion'] = Plugin::find()
                            ->id($pluginLicense->pluginId)
                            ->withLatestReleaseInfo(true, $this->cmsVersion)
                            ->select(['latestVersion'])
                            ->asArray()
                            ->scalar();
                    }
                    $pluginLicenses[] = $pluginLicenseInfo;
                }
                $licenseInfo['pluginLicenses'] = $pluginLicenses;
            }
        }

        return $this->asJson([
            'license' => $licenseInfo,
        ]);
    }
}
