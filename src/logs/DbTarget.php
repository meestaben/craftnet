<?php

namespace craftnet\logs;

use Craft;
use craftnet\controllers\api\BaseApiController;
use yii\helpers\VarDumper;
use yii\log\LogRuntimeException;

/**
 */
class DbTarget extends \yii\log\DbTarget
{
    /**
     * @inheritdoc
     */
    public function export()
    {
        // get the request ID if this is an API request
        $controller = Craft::$app->controller;
        if ($controller instanceof BaseApiController) {
            $requestId = $controller->requestId;
        } else {
            $requestId = null;
        }

        $tableName = $this->db->quoteTableName($this->logTable);
        $sql = "INSERT INTO $tableName ([[requestId]], [[level]], [[category]], [[timestamp]], [[prefix]], [[message]])
                VALUES (:requestId, :level, :category, :timestamp, :prefix, :message)";
        $command = $this->db->createCommand($sql);
        foreach ($this->messages as $message) {
            [$text, $level, $category, $timestamp] = $message;
            if (!is_string($text)) {
                // exceptions may not be serializable if in the call stack somewhere is a Closure
                if ($text instanceof \Throwable || $text instanceof \Exception) {
                    $text = (string)$text;
                } else {
                    $text = VarDumper::export($text);
                }
            }
            if ($command->bindValues([
                    ':requestId' => $requestId,
                    ':level' => $level,
                    ':category' => $category,
                    ':timestamp' => $timestamp,
                    ':prefix' => $this->getMessagePrefix($message),
                    ':message' => $text,
                ])->execute() > 0) {
                continue;
            }
            throw new LogRuntimeException('Unable to export log through database!');
        }
    }
}
