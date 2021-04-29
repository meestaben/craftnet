<?php
/**
 * Craft web bootstrap file
 */

use craft\helpers\App;

switch ($_SERVER['HTTP_HOST']) {
    case 'api.craftcms.com':
    case 'api.craftcms.test':
    case 'api.craftcms.nitro':
    case 'api.craftcms.next':
    case 'staging.api.craftcms.com':
    case 'craftcmsapi.com':
        define('CRAFT_SITE', 'api');
        break;
    case 'composer.craftcms.com':
    case 'composer.craftcms.test':
    case 'composer.craftcms.niro':
        define('CRAFT_SITE', 'composer');
        break;
    case 'id.craftcms.com':
    case 'id.craftcms.test':
    case 'id.craftcms.nitro':
    case 'id.craftcms.next':
    case 'staging.id.craftcms.com':
        define('CRAFT_SITE', 'craftId');
        break;
    case 'plugins.craftcms.com':
    case 'staging.plugins.craftcms.com':
    case 'plugins.craftcms.test':
    case 'plugins.craftcms.nitro':
    case 'plugins.craftcms.next':
        define('CRAFT_SITE', 'plugins');
        break;
}

define('CRAFT_BASE_PATH', __DIR__);
define('CRAFT_VENDOR_PATH', CRAFT_BASE_PATH . '/vendor');

// Composer autoloader
require_once CRAFT_VENDOR_PATH . '/autoload.php';

// dotenv
if (file_exists(CRAFT_BASE_PATH . '/.env')) {
    $dotenv = new Dotenv\Dotenv(CRAFT_BASE_PATH);
    $dotenv->load();
}

if ($storagePath = App::env('CRAFT_STORAGE_PATH')) {
    define('CRAFT_STORAGE_PATH', $storagePath);
}
if ($keyPath = App::env('LICENSE_KEY_PATH')) {
    define('CRAFT_LICENSE_KEY_PATH', $keyPath);
}

define('CRAFT_ENVIRONMENT', App::env('CRAFT_ENV') ?: 'prod');

return require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/web.php';
