<?php

namespace craftnet\helpers;

use Craft;
use craftnet\Module;
use yii\caching\FileDependency;
use yii\caching\TagDependency;

abstract class Cache
{
    /**
     * Returns a cached value, or `false` if it doesn’t exist.
     * @param string $key
     * @return mixed
     */
    public static function get(string $key)
    {
        if (!self::enabled()) {
            return false;
        }

        return Craft::$app->getCache()->get("cn-{$key}");
    }

    /**
     * Sets a cached value.
     *
     * @param string $key
     * @param mixed $value
     * @param string[]|null $tags
     * @return bool
     */
    public static function set(string $key, $value, array $tags = null)
    {
        if (!self::enabled()) {
            return false;
        }

        if ($tags !== null) {
            $dependency = new TagDependency([
                'tags' => $tags,
            ]);
        } else {
            $dependency = new FileDependency([
                'fileName' => Module::getInstance()->getJsonDumper()->composerWebroot . '/packages.json',
            ]);
        }

        return Craft::$app->getCache()->set("cn-{$key}", $value, null, $dependency);
    }

    /**
     * Invalidates a cache tag.
     *
     * @param string|string[] $tags
     */
    public static function invalidate($tags)
    {
        TagDependency::invalidate(Craft::$app->cache, $tags);
    }

    /**
     * Returns whether the cache is enabled.
     *
     * @return bool
     */
    private static function enabled(): bool
    {
        $craftIdConfig = Craft::$app->getConfig()->getConfigFromFile('craftid');
        return !empty($craftIdConfig['enablePluginStoreCache']);
    }
}
