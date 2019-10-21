<?php

namespace craftnet\controllers\api;

use Craft;
use yii\web\Response;

class OptionsController extends BaseApiController
{
    /**
     * Handles pre-flight OPTIONS requests.
     *
     * @return Response
     */
    public function actionIndex(): Response
    {
        // https://stackoverflow.com/a/12320736/1688568
        $response = Craft::$app->getResponse();
        $response->getHeaders()
            //->set('Access-Control-Max-Age', 3628800)
            ->set('Access-Control-Allow-Methods', Craft::$app->getRequest()->getHeaders()->get('Access-Control-Request-Method'))
            ->set('Access-Control-Allow-Headers', 'Content-Type')
            ->set('Access-Control-Max-Age', '31536000');
        return $response;
    }
}
