<?php

namespace craftnet\controllers\id;

use Craft;
use craft\errors\UploadFailedException;
use craft\web\UploadedFile;
use craftnet\errors\LicenseNotFoundException;
use craftnet\Module;
use Exception;
use Throwable;
use yii\web\ForbiddenHttpException;
use yii\web\Response;
use yii\web\UnauthorizedHttpException;

/**
 * Class CmsLicensesController
 *
 * @property Module $module
 */
class CmsLicensesController extends BaseController
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
        $key = $this->request->getBodyParam('key');
        $licenseFile = UploadedFile::getInstanceByName('licenseFile');

        try {
            $user = Craft::$app->getUser()->getIdentity();

            if ($licenseFile) {
                if ($licenseFile->getHasError()) {
                    throw new UploadFailedException($licenseFile->error);
                }

                $licenseFilePath = $licenseFile->tempName;

                $key = file_get_contents($licenseFilePath);
            }

            if ($key) {
                $this->module->getCmsLicenseManager()->claimLicense($user, $key);
                return $this->asJson(['success' => true]);
            }

            throw new Exception("No license key provided.");
        } catch (Throwable $e) {
            return $this->asErrorJson($e->getMessage());
        }
    }

    /**
     * Download license file.
     *
     * @return Response
     * @throws ForbiddenHttpException
     * @throws \yii\web\HttpException
     */
    public function actionDownload(): Response
    {
        $user = Craft::$app->getUser()->getIdentity();
        $licenseId = $this->request->getParam('id');
        $license = $this->module->getCmsLicenseManager()->getLicenseById($licenseId);

        if ($license->ownerId === $user->id) {
            return $this->response->sendContentAsFile(chunk_split($license->key, 50), 'license.key');
        }

        throw new ForbiddenHttpException('User is not authorized to perform this action');
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
            $total = Module::getInstance()->getCmsLicenseManager()->getExpiringLicensesTotal($user);

            return $this->asJson($total);
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
            $license = Module::getInstance()->getCmsLicenseManager()->getLicenseById($id);

            if ($license->ownerId !== $user->id) {
                throw new UnauthorizedHttpException('Not Authorized');
            }

            $licenseArray = Module::getInstance()->getCmsLicenseManager()->transformLicenseForOwner($license, $user, ['pluginLicenses']);

            return $this->asJson($licenseArray);
        } catch (Throwable $e) {
            return $this->asErrorJson($e->getMessage());
        }
    }

    /**
     * Returns the current user’s licenses for `vue-tables-2`.
     *
     * @return Response
     */
    public function actionGetLicenses(): Response
    {
        $user = Craft::$app->getUser()->getIdentity();

        $filter = $this->request->getParam('filter');
        $perPage = $this->request->getParam('per_page', 10);
        $page = (int)$this->request->getParam('page', 1);
        $orderBy = $this->request->getParam('orderBy');
        $ascending = (bool)$this->request->getParam('ascending');
        $byColumn = $this->request->getParam('byColumn');

        try {
            $licenses = Module::getInstance()->getCmsLicenseManager()->getLicensesByOwner($user, $filter, $perPage, $page, $orderBy, $ascending, $byColumn);
            $totalLicenses = Module::getInstance()->getCmsLicenseManager()->getTotalLicensesByOwner($user, $filter);

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
     * Releases a license.
     *
     * @return Response
     * @throws LicenseNotFoundException
     */
    public function actionRelease(): Response
    {
        $key = $this->request->getParam('key');
        $user = Craft::$app->getUser()->getIdentity();
        $manager = $this->module->getCmsLicenseManager();
        $license = $manager->getLicenseByKey($key);

        try {
            if ($user && $license->ownerId === $user->id) {
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
     */
    public function actionSave(): Response
    {
        $key = $this->request->getParam('key');
        $user = Craft::$app->getUser()->getIdentity();
        $manager = $this->module->getCmsLicenseManager();
        $license = $manager->getLicenseByKey($key);

        try {
            if ($user && $license->ownerId === $user->id) {
                $domain = $this->request->getParam('domain');
                $notes = $this->request->getParam('notes');

                if ($domain !== null) {
                    $oldDomain = $license->domain;
                    $license->domain = $domain ?: null;
                }

                if ($notes !== null) {
                    $license->notes = $notes;
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

                if (!$manager->saveLicense($license)) {
                    throw new Exception("Couldn't save license.");
                }

                if ($domain !== null && $license->domain !== $oldDomain) {
                    $note = $license->domain ? "tied to domain {$license->domain}" : "untied from domain {$oldDomain}";
                    $manager->addHistory($license->id, "{$note} by {$user->email}");
                }

                return $this->asJson([
                    'success' => true,
                    'license' => $manager->transformLicenseForOwner($license, $user),
                ]);
            }

            throw new LicenseNotFoundException($key);
        } catch (Throwable $e) {
            return $this->asErrorJson($e->getMessage());
        }
    }
}
