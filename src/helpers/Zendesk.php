<?php

namespace craftnet\helpers;

use Zendesk\API\HttpClient;
use Zendesk\API\Utilities\Auth;

abstract class Zendesk
{
    public static function client(): HttpClient
    {
        $client = new HttpClient('craftcms');
        $client->setAuth(Auth::BASIC, [
            'username' => getenv('ZENDESK_USERNAME'),
            'token' => getenv('ZENDESK_TOKEN'),
        ]);
        return $client;
    }
}
