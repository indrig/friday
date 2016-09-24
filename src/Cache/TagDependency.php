<?php
namespace Friday\Cache;
use Friday\Base\Awaitable;
use Friday\Base\Deferred;


/**
 * TagDependency associates a cached data item with one or multiple [[tags]].
 *
 * By calling [[invalidate()]], you can invalidate all cached data items that are associated with the specified tag name(s).
 *
 * ```php
 * // setting multiple cache keys to store data forever and tagging them with "user-123"
 * Friday::$app->cache->set('user_42_profile', '', 0, new TagDependency(['tags' => 'user-123']));
 * Friday::$app->cache->set('user_42_stats', '', 0, new TagDependency(['tags' => 'user-123']));
 *
 * // invalidating all keys tagged with "user-123"
 * TagDependency::invalidate(Friday::$app->cache, 'user-123');
 * ```
 *
 */
class TagDependency extends AbstractDependency {
    /**
     * @var string|array a list of tag names for this dependency. For a single tag, you may specify it as a string.
     */
    public $tags = [];


    /**
     * Generates the data needed to determine if dependency has been changed.
     * This method does nothing in this class.
     * @param AbstractCache $cache the cache component that is currently evaluating this dependency
     * @return mixed the data needed to determine if dependency has been changed.
     */
    protected function generateDependencyData($cache) : Awaitable
    {
        $deferred = new Deferred();

        $this->getTimestamps($cache, (array) $this->tags)->await(function ($timestamps) use($cache, $deferred) {
            $newKeys = [];
            foreach ($timestamps as $key => $timestamp) {
                if ($timestamp === false) {
                    $newKeys[] = $key;
                }
            }
            if (!empty($newKeys)) {
                $timestamps = array_merge($timestamps, static::touchKeys($cache, $newKeys));
            }

            $deferred->result($timestamps);
        });

        return $deferred->awaitable();
    }

    /**
     * Performs the actual dependency checking.
     * @param AbstractCache $cache the cache component that is currently evaluating this dependency
     * @return Awaitable
     */
    public function getHasChanged($cache) : Awaitable
    {
        $deferred = new Deferred();

        $this->getTimestamps($cache, (array) $this->tags)->await(function ($timestamps) use ($deferred) {
            $deferred->result($timestamps !== $this->data);
        });
        return $deferred->awaitable();
    }

    /**
     * Invalidates all of the cached data items that are associated with any of the specified [[tags]].
     * @param AbstractCache $cache the cache component that caches the data items
     * @param Awaitable string|array $tags
     */
    public static function invalidate($cache, $tags) : Awaitable
    {
        $keys = [];
        foreach ((array) $tags as $tag) {
            $keys[] = $cache->buildKey([__CLASS__, $tag]);
        }
        return static::touchKeys($cache, $keys);
    }

    /**
     * Generates the timestamp for the specified cache keys.
     * @param AbstractCache $cache
     * @param string[] $keys
     * @return Awaitable array the timestamp indexed by cache keys
     */
    protected static function touchKeys($cache, $keys) : Awaitable
    {
        $items = [];
        $time = microtime();
        foreach ($keys as $key) {
            $items[$key] = $time;
        }
        return $cache->multiSet($items);
    }

    /**
     * Returns the timestamps for the specified tags.
     * @param AbstractCache $cache
     * @param string[] $tags
     * @return Awaitable
     */
    protected function getTimestamps($cache, $tags) : Awaitable
    {
        $deferred = new Deferred();
        if (empty($tags)) {
            $deferred->result([]);
            return $deferred->awaitable();
        }

        $keys = [];
        foreach ($tags as $tag) {
            $keys[] = $cache->buildKey([__CLASS__, $tag]);
        }

        $cache->multiGet($keys)->await(function($data) use ($deferred){
            $deferred->result([$data]);
        });

        return $deferred->awaitable();
    }
}
