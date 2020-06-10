<?php

namespace craftnet\controllers\api\v1\utils;

use craftnet\controllers\api\BaseApiController;
use yii\web\Response;

/**
 * Class PingController
 */
class PingController extends BaseApiController
{
    // Public Methods
    // =========================================================================

    /**
     * Used for health checks.
     *
     * @return Response
     */
    public function actionIndex(): Response
    {
        return $this->asRaw('pong');
    }
}
