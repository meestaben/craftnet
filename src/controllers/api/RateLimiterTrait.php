<?php

namespace craftnet\controllers\api;

use Craft;
use craft\helpers\App;
use thamtech\ratelimiter\Context;
use thamtech\ratelimiter\handlers\RateLimitHeadersHandler;
use thamtech\ratelimiter\handlers\RetryAfterHeaderHandler;
use thamtech\ratelimiter\handlers\TooManyRequestsHttpExceptionHandler;
use thamtech\ratelimiter\limit\RateLimitResult;
use thamtech\ratelimiter\RateLimiter;
use thamtech\ratelimiter\RateLimitsCheckedEvent;
use yii\base\Component;

/**
 * @mixin Component
 */
trait RateLimiterTrait
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();

        $behaviors['rateLimiter'] = [
            'class' => RateLimiter::class,
            'components' => [
                'rateLimit' => [
                    'definitions' => [
                        'craftnetapi' => [
                            'limit' => App::env('API_RATE_LIMIT') ?: 1000,
                            'window' => App::env('API_RATE_LIMIT_WINDOW') ?: 3600,

                            // Rate limit is per IP address per controller action
                            'identifier' => function(Context $context, $rateLimitId) {
                                return $rateLimitId.':'.$context->request->getPathInfo().':'.$context->request->getUserIP();
                            },
                        ],
                    ],
                ],
                'allowanceStorage' => [
                    'cache' => 'cache', // use cache component
                ],
            ],

            // add X-Rate-Limit-* HTTP headers to the response
            'as rateLimitHeaders' => RateLimitHeadersHandler::class,

            // add Retry-After HTTP header to the response
            'as retryAfterHeader' => RetryAfterHeaderHandler::class,

            // throw TooManyRequestsHttpException when the limit is exceeded
            'as tooManyRequestsException' => TooManyRequestsHttpExceptionHandler::class,

            // custom action on check
            'on rateLimitsChecked' => function(RateLimitsCheckedEvent $event) {
                $ip = $event->context->request->getUserIP();

                /** @var RateLimitResult $rateLimitResult */
                $rateLimitResult = $event->rateLimitResults['craftnetapi'];
                $rateLimit = $rateLimitResult->rateLimit;

                Craft::info('Rate limit checked: ' . $ip . ' checked at '.$rateLimitResult->timestamp.'. '.$rateLimitResult->allowance.' out of '.$rateLimit->limit.' left. Exceeded? '.($rateLimitResult->isExceeded ? 'True' : 'False'));
            },

            // custom action on limits exceeded
            'on rateLimitsExceeded' => function(RateLimitsCheckedEvent $event) {
                $ip = $event->context->request->getUserIP();

                /** @var RateLimitResult $rateLimitResult */
                $rateLimitResult = $event->rateLimitResults['craftnetapi'];
                $rateLimit = $rateLimitResult->rateLimit;

                Craft::error('Rate limit exceeded: ' . $ip . ' checked at '.$rateLimitResult->timestamp.'. '.$rateLimitResult->allowance.' out of '.$rateLimit->limit.' left. Exceeded? '.($rateLimitResult->isExceeded ? 'True' : 'False'));
            },
        ];

        return $behaviors;
    }
}
