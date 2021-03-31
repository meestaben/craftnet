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
        $key = $this->request->getParam('key');
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
        $id = $this->request->getRequiredParam('id');

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

        $filter = $this->request->getParam('filter');
        $perPage = $this->request->getParam('per_page', 10);
        $page = (int)$this->request->getParam('page', 1);
        $orderBy = $this->request->getParam('orderBy');
        $ascending = (bool)$this->request->getParam('ascending');

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
        $pluginHandle = $this->request->getParam('handle');
        $key = $this->request->getParam('key');
        $user = Craft::$app->getUser()->getIdentity();
        $manager = $this->module->getPluginLicenseManager();
        $license = $manager->getLicenseByKey($key, $pluginHandle);

        try {
            if ($license && $user && $license->ownerId === $user->id) {
                $license->ownerId = null;

                if ($manager->saveLicense($license, true, ['ownerId'])) {
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
        $pluginHandle = $this->request->getRequiredBodyParam('pluginHandle');
        $key = $this->request->getRequiredBodyParam('key');
        $user = Craft::$app->getUser()->getIdentity();
        $manager = $this->module->getPluginLicenseManager();
        $license = $manager->getLicenseByKey($key, $pluginHandle);

        try {
            if ($user && $license->ownerId === $user->id) {
                $notes = $this->request->getParam('notes');

                if ($notes !== null) {
                    $license->notes = $this->request->getParam('notes');
                }

                $oldCmsLicenseId = $license->cmsLicenseId;
                if (($cmsLicenseId = $this->request->getParam('cmsLicenseId', false)) !== false) {
                    $license->cmsLicenseId = $cmsLicenseId ?: null;
                }

                // Did they change the auto renew setting?
                $autoRenew = $this->request->getParam('autoRenew', $license->autoRenew);

                if ($autoRenew != $license->autoRenew) {
                    $license->autoRenew = $autoRenew;
                    // If they've already received a reminder about the auto renewal, then update the locked price
                    if ($autoRenew && $license->reminded) {
                        $license->renewalPrice = $license->getEdition()->getRenewal()->getPrice();
                    }
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
