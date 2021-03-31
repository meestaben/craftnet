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
     *
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
     * Returns a cached value, or sets the value to the result of a callback if it doesn’t exist.
     *
     * @param string $key
     * @param callable $callback
     * @param string[]|null $tags
     * @return mixed
     */
    public static function getOrSet(string $key, callable $callback, ?array $tags = null)
    {
        if (!self::enabled()) {
            return $callback();
        }

        if (($value = static::get($key)) !== false) {
            return $value;
        }

        // Obtain a lock before we go through the trouble of generating the value,
        // in case something else is already generating it
        $mutex = Craft::$app->getMutex();
        $lockName = "cn-$key";
        if (!$mutex->acquire($lockName, 5)) {
            Craft::warning("Unable to obtain a lock for cache key '$key'", __METHOD__);
            return $callback();
        }

        // Maybe the value is cached now?
        if (($value = static::get($key)) !== false) {
            $mutex->release($lockName);
            return $value;
        }

        // Get it and cache it
        try {
            $value = $callback();
        } catch (\Throwable $e) {
            $mutex->release($lockName);
            throw $e;
        }

        static::set($key, $value, $tags);
        $mutex->release($lockName);
        return $value;
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
