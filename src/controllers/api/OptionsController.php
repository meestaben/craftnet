<?php

namespace craftnet\controllers\api;

use yii\web\Response;

class OptionsController extends BaseApiController
{
    /**
     * @inheritdoc
     */
    public $checkCraftHeaders = false;

    /**
     * Handles pre-flight OPTIONS requests.
     *
     * @return Response
     */
    public function actionIndex(): Response
    {
        // https://stackoverflow.com/a/12320736/1688568
        $this->response->getHeaders()
            //->set('Access-Control-Max-Age', 3628800)
            ->set('Access-Control-Allow-Methods', $this->request->getHeaders()->get('Access-Control-Request-Method'))
            ->set('Access-Control-Allow-Headers', 'Content-Type,x-craft-host,x-craft-plugin-licenses,x-craft-user-ip,x-craft-system,x-craft-license,x-craft-platform,x-craft-user-email,authorization')
            ->set('Access-Control-Max-Age', '31536000');
        return $this->response;
    }
}
