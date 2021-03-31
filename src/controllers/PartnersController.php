<?php

namespace craftnet\controllers;

use Craft;
use craft\base\Element;
use craft\elements\User;
use craft\web\Controller;
use craftnet\behaviors\UserBehavior;
use craftnet\Module;
use craftnet\partners\Partner;
use craftnet\partners\PartnerAsset;
use craftnet\partners\PartnerCapabilitiesQuery;
use craftnet\partners\PartnerHistory;
use craftnet\partners\PartnerService;
use GuzzleHttp\Exception\RequestException;
use yii\base\Exception;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * @property Module $module
 */
class PartnersController extends Controller
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        return true;
    }

    /**
     * Fetches the parter for the currently logged in user.
     *
     * @return Response
     * @throws ForbiddenHttpException
     * @throws NotFoundHttpException
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionFetchPartner()
    {
        $this->requireLogin();
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        /** @var User|UserBehavior $user */
        $user = Craft::$app->getUser()->getIdentity();
        $partner = $user->getPartner();
        $data = PartnerService::getInstance()->serializePartner($partner);

        return $this->asJson(['partner' => $data]);
    }

    /**
     * From Craft ID, only allowed to edit own Partner Profile
     *
     * @return Response
     * @throws Exception
     * @throws ForbiddenHttpException
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionPatchPartner()
    {
        $this->requireLogin();
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $partnerId = $this->request->getBodyParam('id');

        /** @var User|UserBehavior $user */
        $user = Craft::$app->getUser()->getIdentity();
        $partner = $user->getPartner();

        if ($partner->id !== (int)$partnerId) {
            throw new ForbiddenHttpException();
        }

        switch ($this->request->getBodyParam('scenario')) {
            case Partner::SCENARIO_BASE_INFO:
                $partner->setScenario(Partner::SCENARIO_BASE_INFO);
                PartnerService::getInstance()->mergeRequestParams($partner, $this->request, [
                    'logoAssetId',
                    'businessName',
                    'primaryContactName',
                    'primaryContactEmail',
                    'primaryContactPhone',
                    'region',
                    'isRegisteredBusiness',
                    'hasFullTimeDev',
                    'capabilities',
                    'expertise',
                    'agencySize',
                    'fullBio',
                    'shortBio',
                    'websiteSlug',
                    'website',
                ]);

                if (substr((string)$partner->logoAssetId, 0, 3) === 'new') {
                    $logo = PartnerService::getInstance()->handleUploadedLogo($partner);
                    $partner->setLogo($logo);
                }

                break;

            case Partner::SCENARIO_LOCATIONS:
                $partner->setScenario(Partner::SCENARIO_LOCATIONS);
                PartnerService::getInstance()->mergeRequestParams($partner, $this->request, [
                    'locations',
                ]);
                break;

            case Partner::SCENARIO_PROJECTS:
                $partner->setScenario(Partner::SCENARIO_PROJECTS);
                PartnerService::getInstance()->mergeRequestParams($partner, $this->request, [
                    'projects',
                ]);
                break;

            default:
                throw new BadRequestHttpException('Invalid partner scenario');
                break;
        }

        // Errors added to partner on invalid logo upload
        if ($partner->hasErrors() || !Craft::$app->getElements()->saveElement($partner)) {
            $errors = PartnerService::getInstance()->getSerializedPartnerErrors($partner);

            return $this->asJson([
                'errors' => $errors,
                'success' => false,
            ]);
        }

        // Attempt to enable disabled entries.
        // It goes live when it fully validates on Craft ID
        if (!$partner->enabled) {
            $partner->enabled = true;
            $partner->setScenario(Partner::SCENARIO_LIVE);
            if (!Craft::$app->getElements()->saveElement($partner)) {
                $partner->enabled = false;
            }
        }

        $data = PartnerService::getInstance()->serializePartner($partner);

        return $this->asJson([
            'partner' => $data,
            'success' => true,
        ]);
    }

    /**
     * @return Response
     * @throws BadRequestHttpException
     * @throws Exception
     * @throws \Throwable
     * @throws \craft\errors\ElementNotFoundException
     * @throws \craft\errors\UploadFailedException
     */
    public function actionUploadScreenshots()
    {
        $this->requireLogin();
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        /** @var User|UserBehavior $user */
        $user = Craft::$app->getUser()->getIdentity();
        $partner = $user->getPartner();

        $screenshots = PartnerService::getInstance()->handleUploadedScreenshots($partner);

        $screenshots = array_map(function($sreenshot) {
            return [
                'id' => $sreenshot->id,
                'url' => $sreenshot->url,
            ];
        }, $screenshots);

        return $this->asJson(compact('screenshots'));
    }

    /**
     * @param int|null $partnerId
     * @param Partner|null $partner
     * @return Response
     * @throws ForbiddenHttpException
     * @throws NotFoundHttpException
     */
    public function actionEdit(int $partnerId = null, Partner $partner = null): Response
    {
        if ($partner === null) {
            if ($partnerId !== null) {
                $partner = Partner::find()->id($partnerId)->status(null)->one();

                if ($partner === null) {
                    throw new NotFoundHttpException('Invalid partner ID: ' . $partnerId);
                }
            } else {
                $partner = new Partner([
                    'enabled' => false,
                ]);
            }
        }

        $allCapabilities = (new PartnerCapabilitiesQuery())->asIndexedTitles()->all();
        $title = $partner->id ? $partner->businessName : 'Add a new partner';
        $folderIds = PartnerService::getInstance()->getVolumeFolderIds();

        $this->view->registerAssetBundle(PartnerAsset::class);

        return $this->renderTemplate('craftnet/partners/_edit', compact(
            'partner',
            'title',
            'allCapabilities',
            'folderIds'
        ));
    }

    // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    /**
     * TODO: Implement user permissions for editing partners
     *
     * @return Response
     * @throws Exception
     * @throws ForbiddenHttpException
     * @throws NotFoundHttpException
     */
    public function actionSave()
    {
        $partnerId = $this->request->getBodyParam('partnerId');
        $isNew = $partnerId === null;

        // Get existing or new Partner
        if ($partnerId) {
            $partner = Partner::find()->id($partnerId)->status(null)->one();

            if ($partner === null) {
                throw new NotFoundHttpException('Invalid partner ID: ' . $partnerId);
            }
        } else {
            $partner = new Partner();
        }

        PartnerService::getInstance()->mergeRequestParams($partner, $this->request, [
                'enabled',
                'ownerId',
                'logoAssetId',
                'businessName',
                'primaryContactName',
                'primaryContactEmail',
                'primaryContactPhone',
                'fullBio',
                'shortBio',
                'hasFullTimeDev',
                'isCraftVerified',
                'isCommerceVerified',
                'isEnterpriseVerified',
                'isRegisteredBusiness',
                'agencySize',
                'hasFullTimeDev',
                'region',
                'expertise',
                'capabilities',
                'locations',
                'projects',
                'verificationStartDate',
                'websiteSlug',
                'website',
            ]
        );

        if ($partner->enabled) {
            $partner->setScenario(Element::SCENARIO_LIVE);
        }

        if (!Craft::$app->getElements()->saveElement($partner)) {
            if ($this->request->getAcceptsJson()) {
                return $this->asJson([
                    'errors' => $partner->getErrors(),
                ]);
            }

            Craft::$app->getSession()->setError('Couldn’t save partner.');
            Craft::$app->getUrlManager()->setRouteParams([
                'partner' => $partner,
            ]);
            return null;
        }

        // "Save & continue" on a new entry tries to go to `partners/-`
        if ($isNew) {
            return $this->redirect('partners/' . $partner->id);
        }

        return $this->redirectToPostedUrl($partner);
    }

    /**
     * @return null|Response
     * @throws NotFoundHttpException
     */
    public function actionDelete()
    {
        $partnerId = $this->request->getBodyParam('partnerId');
        $partner = Partner::find()->id($partnerId)->status(null)->one();

        if (!$partner) {
            throw new NotFoundHttpException('Partner not found');
        }

        Craft::$app->getElements()->deleteElement($partner);

        return $this->redirect('partners');
    }

    public function actionFetchHistory($partnerId)
    {
        $history = PartnerHistory::findByPartnerId($partnerId);
        return $this->asJson(compact('history', 'partnerId'));
    }

    public function actionSaveHistory()
    {
        $params = [
            'id' => $this->request->getBodyParam('id'),
            'message' => $this->request->getBodyParam('message'),
            'partnerId' => $this->request->getBodyParam('partnerId'),
            'authorId' => Craft::$app->getUser()->id,
        ];

        $partnerHistory = PartnerHistory::firstOrNew($params);

        $success = $partnerHistory->save();

        if (!$success) {
            return $this->asJson([
                'success' => false,
                'payload' => $params,
                'errors' => $partnerHistory->getErrors(),
            ]);
        }

        return $this->asJson([
            'success' => true,
            'history' => $partnerHistory,
        ]);
    }

    /**
     * @param $id
     * @return Response
     * @throws RequestException
     */
    public function actionDeleteHistory($id)
    {
        $rowsAffected = PartnerHistory::deleteById((int)$id);

        return $this->asJson([
            'success' => (bool)$rowsAffected,
        ]);
    }

    public function actionFoo()
    {
        return '';
    }

    // Private Methods
    // =========================================================================

}
