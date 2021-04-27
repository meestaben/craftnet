<?php

namespace craftnet\partners\jobs;

use Craft;
use craft\helpers\App;
use craft\queue\BaseJob;
use GuzzleHttp\RequestOptions;

class UpdatePartner extends BaseJob
{
    /**
     * @var int
     */
    public $partnerId;

    /**
     * @inheritdoc
     */
    public function execute($queue)
    {
        Craft::createGuzzleClient()->post(App::env('CRAFTCOM_PARTNER_ENDPOINT'), [
            RequestOptions::HEADERS => [
                'X-Partner-Secret' => App::env('PARTNER_SECRET'),
            ],
            RequestOptions::QUERY => [
                'partnerId' => $this->partnerId,
            ],
        ]);
    }
}
