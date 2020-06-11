<?php

namespace craftnet\controllers\id;

use Craft;
use craft\web\Controller;
use craftnet\errors\LicenseNotFoundException;
use craftnet\Module;
use Exception;
use Throwable;
use yii\web\Response;
use yii\web\UnauthorizedHttpException;

/**
 * Class PluginLicensesController
 *
 * @property Module $module
 */
class PluginLicensesController extends Controller
{
    // Public Methods
    // =========================================================================

    /**
     * Claims a license.
     *
     * @return Response
     */
    public function actionClaim(): Response
    {
        $key = Craft::$app->getRequest()->getParam('key');
        $user = Craft::$app->getUser()->getIdentity();

        try {
            $this->module->getPluginLicenseManager()->claimLicense($user, $key);
            return $this->asJson(['success' => true]);
        } catch (Throwable $e) {
            return $this->asErrorJson($e->getMessage());
        }
    }

    /**
     * Get license by ID.
     *
     * @return Response
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionGetLicenseById(): Response
    {
        $user = Craft::$app->getUser()->getIdentity();
        $id = Craft::$app->getRequest()->getRequiredParam('id');

        try {
            $license = Module::getInstance()->getPluginLicenseManager()->getLicenseById($id);

            if ($license->ownerId !== $user->id) {
                throw new UnauthorizedHttpException('Not Authorized');
            }

            $licenseArray = Module::getInstance()->getPluginLicenseManager()->transformLicenseForOwner($license, $user);

            return $this->asJson($licenseArray);
        } catch (Throwable $e) {
            return $this->asErrorJson($e->getMessage());
        }
    }

    /**
     * Returns licenses for the current user.
     *
     * @return Response
     * @throws LicenseNotFoundException
     */
    public function actionGetLicenses(): Response
    {
        $user = Craft::$app->getUser()->getIdentity();

        $filter = Craft::$app->getRequest()->getParam('filter');
        $perPage = Craft::$app->getRequest()->getParam('per_page', 10);
        $page = (int)Craft::$app->getRequest()->getParam('page', 1);
        $orderBy = Craft::$app->getRequest()->getParam('orderBy');
        $ascending = (bool)Craft::$app->getRequest()->getParam('ascending');

        try {
            $licenses = Module::getInstance()->getPluginLicenseManager()->getLicensesByOwner($user, $filter, $perPage, $page, $orderBy, $ascending);
            $totalLicenses = Module::getInstance()->getPluginLicenseManager()->getTotalLicensesByOwner($user, $filter);

            $lastPage = ceil($totalLicenses / $perPage);
            $nextPageUrl = '?next';
            $prevPageUrl = '?prev';
            $from = ($page - 1) * $perPage;
            $to = ($page * $perPage) - 1;

            return $this->asJson([
                'total' => $totalLicenses,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => $lastPage,
                'next_page_url' => $nextPageUrl,
                'prev_page_url' => $prevPageUrl,
                'from' => $from,
                'to' => $to,
                'data' => $licenses,
            ]);
        } catch (Throwable $e) {
            return $this->asErrorJson($e->getMessage());
        }
    }

    /**
     * Get the number of expiring licenses.
     *
     * @return Response
     */
    public function actionGetExpiringLicensesTotal(): Response
    {
        $user = Craft::$app->getUser()->getIdentity();

        try {
            $total = Module::getInstance()->getPluginLicenseManager()->getExpiringLicensesTotal($user);

            return $this->asJson($total);
        } catch (Throwable $e) {
            return $this->asErrorJson($e->getMessage());
        }
    }

    /**
     * Releases a license.
     *
     * @return Response
     * @throws LicenseNotFoundException
     */
    public function actionRelease(): Response
    {
        $pluginHandle = Craft::$app->getRequest()->getParam('handle');
        $key = Craft::$app->getRequest()->getParam('key');
        $user = Craft::$app->getUser()->getIdentity();
        $manager = $this->module->getPluginLicenseManager();
        $license = $manager->getLicenseByKey($key, $pluginHandle);

        try {
            if ($license && $user && $license->ownerId === $user->id) {
                $license->ownerId = null;

                if ($manager->saveLicense($license)) {
                    $manager->addHistory($license->id, "released by {$user->email}");
                    return $this->asJson(['success' => true]);
                }

                throw new Exception("Couldn't save license.");
            }

            throw new LicenseNotFoundException($key);
        } catch (Throwable $e) {
            return $this->asErrorJson($e->getMessage());
        }
    }

    /**
     * Saves a license.
     *
     * @return Response
     * @throws LicenseNotFoundException
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionSave(): Response
    {
        $pluginHandle = Craft::$app->getRequest()->getRequiredBodyParam('pluginHandle');
        $key = Craft::$app->getRequest()->getRequiredBodyParam('key');
        $user = Craft::$app->getUser()->getIdentity();
        $manager = $this->module->getPluginLicenseManager();
        $license = $manager->getLicenseByKey($key, $pluginHandle);

        try {
            if ($user && $license->ownerId === $user->id) {
                $license->notes = Craft::$app->getRequest()->getParam('notes');

                $oldCmsLicenseId = $license->cmsLicenseId;
                if (($cmsLicenseId = Craft::$app->getRequest()->getParam('cmsLicenseId', false)) !== false) {
                    $license->cmsLicenseId = $cmsLicenseId ?: null;
                }

                if (($autoRenew = Craft::$app->getRequest()->getParam('autoRenew', false)) !== false) {
                    $license->autoRenew = $autoRenew ? true : false;
                }

                if ($manager->saveLicense($license)) {
                    if ($oldCmsLicenseId != $license->cmsLicenseId) {
                        if ($oldCmsLicenseId) {
                            $oldCmsLicense = $this->module->getCmsLicenseManager()->getLicenseById($oldCmsLicenseId);
                            $manager->addHistory($license->id, "detached from Craft license {$oldCmsLicense->shortKey} by {$user->email}");
                        }

                        if ($license->cmsLicenseId) {
                            $newCmsLicense = $this->module->getCmsLicenseManager()->getLicenseById($license->cmsLicenseId);
                            $manager->addHistory($license->id, "attached to Craft license {$newCmsLicense->shortKey} by {$user->email}");
                        }
                    }

                    return $this->asJson(['success' => true]);
                }

                throw new Exception("Couldn't save license.");
            }

            throw new LicenseNotFoundException($key);
        } catch (Throwable $e) {
            return $this->asErrorJson($e->getMessage());
        }
    }
}
