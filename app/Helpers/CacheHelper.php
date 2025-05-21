<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Cache;

class CacheHelper
{
    /**
     * Cache expiration times in minutes
     */
    const SHORT_TERM = 5;    // For frequently changing data
    const MEDIUM_TERM = 15;  // For moderately changing data
    const LONG_TERM = 60;    // For rarely changing data

    /**
     * Remember a value in cache with proper tagging
     *
     * @param array $tags Array of cache tags
     * @param string $key Cache key
     * @param int $minutes Minutes to keep in cache
     * @param \Closure $callback Callback that returns the value to cache
     * @return mixed
     */
    public static function remember(array $tags, string $key, int $minutes, \Closure $callback)
    {
        return Cache::tags($tags)->remember($key, now()->addMinutes($minutes), $callback);
    }

    /**
     * Flush cache for specific tags
     *
     * @param array $tags Array of cache tags to flush
     * @return void
     */
    public static function flush(array $tags)
    {
        foreach ($tags as $tag) {
            Cache::tags([$tag])->flush();
        }
    }

    /**
     * Generate a user-specific cache key
     *
     * @param string $prefix Prefix for the key
     * @param int $userId User ID
     * @param array $params Additional parameters to include in the key
     * @return string
     */
    public static function userKey(string $prefix, int $userId, array $params = [])
    {
        $key = "{$prefix}_{$userId}";
        
        foreach ($params as $name => $value) {
            $key .= "_{$name}_{$value}";
        }
        
        return $key;
    }
}
