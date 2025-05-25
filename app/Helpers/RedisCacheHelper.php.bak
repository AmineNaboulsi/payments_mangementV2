<?php

namespace App\Helpers;

use App\Services\RedisService;

class RedisCacheHelper
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
     * @param string $key Cache key
     * @param int $minutes Minutes to keep in cache
     * @param \Closure $callback Callback that returns the value to cache
     * @return mixed
     */
    public static function remember(string $key, int $minutes, \Closure $callback)
    {
        $redis = app('redis.service');
        return $redis->remember($key, $minutes * 60, $callback);
    }
    
    /**
     * Store a value in cache
     *
     * @param string $key Cache key
     * @param mixed $value Value to store
     * @param int $minutes Minutes to keep in cache
     * @return void
     */
    public static function put(string $key, $value, int $minutes)
    {
        $redis = app('redis.service');
        $redis->set($key, $value, $minutes * 60);
    }
    
    /**
     * Get a value from cache
     *
     * @param string $key Cache key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        $redis = app('redis.service');
        return $redis->get($key, $default);
    }
    
    /**
     * Delete a value from cache
     *
     * @param string $key Cache key
     * @return void
     */
    public static function forget(string $key)
    {
        $redis = app('redis.service');
        $redis->delete($key);
    }
    
    /**
     * Generate user-specific cache key
     *
     * @param string $prefix Prefix for the key
     * @param int $userId User ID
     * @param array $params Additional parameters to include
     * @return string
     */
    public static function userKey(string $prefix, int $userId, array $params = [])
    {
        $key = "{$prefix}:{$userId}";
        
        foreach ($params as $name => $value) {
            $key .= ":{$name}:{$value}";
        }
        
        return $key;
    }
    
    /**
     * Generate group-specific cache key
     *
     * @param string $prefix Prefix for the key
     * @param int $groupId Group ID
     * @param int|null $userId Optional user ID
     * @param array $params Additional parameters to include
     * @return string
     */
    public static function groupKey(string $prefix, int $groupId, ?int $userId = null, array $params = [])
    {
        $key = "{$prefix}:{$groupId}";
        
        if ($userId !== null) {
            $key .= ":user:{$userId}";
        }
        
        foreach ($params as $name => $value) {
            $key .= ":{$name}:{$value}";
        }
        
        return $key;
    }
    
    /**
     * Generate expense-specific cache key
     *
     * @param string $prefix Prefix for the key
     * @param int $expenseId Expense ID
     * @param int|null $userId Optional user ID
     * @param array $params Additional parameters to include
     * @return string
     */
    public static function expenseKey(string $prefix, int $expenseId, ?int $userId = null, array $params = [])
    {
        $key = "{$prefix}:{$expenseId}";
        
        if ($userId !== null) {
            $key .= ":user:{$userId}";
        }
        
        foreach ($params as $name => $value) {
            $key .= ":{$name}:{$value}";
        }
        
        return $key;
    }
    
    /**
     * Delete multiple keys matching a pattern
     *
     * @param string $pattern Pattern to match keys
     * @return void
     */
    public static function deletePattern(string $pattern)
    {
        $redis = app('redis.service');
        $keys = $redis->client()->keys($pattern);
        
        if (count($keys) > 0) {
            $redis->client()->del($keys);
        }
    }
    
    /**
     * Clear user-related cache
     *
     * @param int $userId User ID
     * @return void
     */
    public static function clearUserCache(int $userId)
    {
        self::deletePattern("*:user:{$userId}*");
    }
    
    /**
     * Clear group-related cache
     *
     * @param int $groupId Group ID
     * @return void
     */
    public static function clearGroupCache(int $groupId)
    {
        self::deletePattern("*:group:{$groupId}*");
    }
    
    /**
     * Clear expense-related cache
     *
     * @param int $expenseId Expense ID
     * @return void
     */
    public static function clearExpenseCache(int $expenseId)
    {
        self::deletePattern("*:expense:{$expenseId}*");
    }
}
