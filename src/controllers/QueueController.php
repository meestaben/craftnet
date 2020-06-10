<?php
/**
 * @link      https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license   MIT
 */

namespace craftnet\controllers;

use yii\di\Instance;
use yii\queue\sqs\Queue;
use yii\web\Controller;
use yii\web\ServerErrorHttpException;

/**
 * Manages the SQS queue
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class QueueController extends Controller
{
    /**
     * @var Queue|array|string
     */
    public $queue = 'queue';

    public $enableCsrfValidation = false;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        $this->queue = Instance::ensure($this->queue, Queue::class);
    }

    /**
     * Handles an incoming SQS message.
     *
     * @throws ServerErrorHttpException
     */
    public function actionHandleMessage()
    {
        $request = \Yii::$app->getRequest();
        $headers = $request->getHeaders();

        $id = $headers->get('X-Aws-Sqsd-Msgid');
        $ttr = $headers->get('X-Aws-Sqsd-Attr-TTR');
        $attempt = $headers->get('X-Aws-Sqsd-Receive-Count');
        $message = $request->getRawBody();

        if (!$this->queue->handle($id, $message, $ttr, $attempt)) {
            throw new ServerErrorHttpException('Unable to handle the queue message');
        }
    }
}
