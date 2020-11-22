<?php

namespace craftnet\controllers\api;

use Craft;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

/**
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class WebhookController extends BaseApiController
{
    /**
     * @inheritdoc
     */
    public $checkCraftHeaders = false;

    /**
     * @throws NotFoundHttpException
     * @throws BadRequestHttpException
     */
    public function actionGithub()
    {
        $payload = $this->getPayload('github-webhook');

        $url = $payload->repository->html_url;
        Craft::info('Incoming payload from Github from ' . $url, __METHOD__);

        $packageManager = $this->module->getPackageManager();
        $name = $packageManager->getPackageNameByRepoUrl($url);

        if (!$name) {
            throw new NotFoundHttpException('No package exists for the repository ' . $url);
        }

        $package = $packageManager->getPackage($name);
        $this->_validateSecret($package->webhookSecret);

        Craft::info('Updating package: ' . $name, __METHOD__);
        $packageManager->updatePackage($name, false, true, true);
    }

    /**
     * @param string|null $secret
     *
     * @throws BadRequestHttpException
     */
    private function _validateSecret($secret)
    {
        if (($header = $this->request->getHeaders()->get('X-Hub-Signature')) === null) {
            throw new BadRequestHttpException('Invalid request body.');
        }

        [$algo, $hash] = explode('=', $header, 2);

        $payloadHash = hash_hmac($algo, $this->request->getRawBody(), $secret);

        if (!hash_equals($payloadHash, $hash)) {
            Craft::error('Invalid secret from Github payload. Payload Hash: ' . $payloadHash . ' Hash: ' . $hash, __METHOD__);
            throw new BadRequestHttpException('Invalid request body.');
        }
    }
}
