<?php

namespace craftnet\controllers\id;

use Craft;
use craft\elements\User;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use craftnet\behaviors\UserBehavior;
use yii\base\InvalidArgumentException;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class ClaimLicensesController extends Controller
{
    // Properties
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected $allowAnonymous = [
        'verify',
    ];

    /**
     * @inheritdoc
     */
    public $defaultAction = 'request';

    // Public Methods
    // =========================================================================

    /**
     * Requests to claim licenses for a given email.
     *
     * @param string $email
     * @return Response
     */
    public function actionRequest(): Response
    {
        /** @var User|UserBehavior $user */
        $user = Craft::$app->getUser()->getIdentity();

        try {
            $email = $this->request->getRequiredParam('email');

            $user->getEmailVerifier()->sendVerificationEmail($email);
        } catch (InvalidArgumentException $e) {
            return $this->asErrorJson($e->getMessage());
        }

        return $this->asJson(['success' => true]);
    }

    /**
     * Verifies a user's email.
     *
     * @param string $id
     * @param string $email
     * @param string $code
     * @return Response
     * @throws NotFoundHttpException
     */
    public function actionVerify(string $id, string $email, string $code): Response
    {
        /** @var User|UserBehavior $user */
        if (($user = User::find()->uid($id)->one()) === null) {
            throw new NotFoundHttpException("Invalid user ID: {$id}");
        }

        try {
            $num = $user->getEmailVerifier()->verify($email, $code);
        } catch (InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        Craft::$app->getSession()->setNotice("{$num} licenses claimed for the email {$email}.");
        return $this->redirect(UrlHelper::siteUrl());
    }
}
