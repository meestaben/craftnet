<?php

namespace craftnet\errors;

use Throwable;
use yii\web\BadRequestHttpException;

class ValidationException extends BadRequestHttpException
{
    /**
     * @var array
     */
    public $errors;

    /**
     * Constructor
     *
     * @param array $validationErrors
     * @param string|null $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(array $validationErrors, string $message = null, int $code = 0, Throwable $previous = null)
    {
        $this->errors = $validationErrors;

        if ($message === null) {
            $message = 'Validation Error';
        }

        parent::__construct($message, $code, $previous instanceof \Exception ? $previous : null);
    }

    /**
     * @return string the user-friendly name of this exception
     */
    public function getName()
    {
        return 'Validation Error';
    }
}
