<?php

namespace App\Services;

use Predis\Client as RedisClient;

class RedisService
{
    protected $redis;
    
    public function __construct()
    {
        $this->redis = new RedisClient([
            'scheme' => 'tcp',
            'host'   => env('REDIS_HOST', '127.0.0.1'),
            'port'   => (int) env('REDIS_PORT', 6379),
            'password' => env('REDIS_PASSWORD', null) ?: null,
            'database' => (int) env('REDIS_CACHE_DB', 1),
        ]);
    }
    
    /**
     * Store a value in Redis
     */
    public function set($key, $value, $expireInSeconds = null)
    {
        // For objects/arrays, serialize to JSON
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value);
        }
        
        if ($expireInSeconds) {
            return $this->redis->setex($key, $expireInSeconds, $value);
        }
        
        return $this->redis->set($key, $value);
    }
    
    /**
     * Get a value from Redis
     */
    public function get($key, $default = null)
    {
        $value = $this->redis->get($key);
        
        if ($value === null) {
            return $default;
        }
        
        // Try to decode JSON if the value looks like JSON
        if (is_string($value) && strpos($value, '{') === 0) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }
        
        return $value;
    }
    
    /**
     * Delete a value from Redis
     */
    public function delete($key)
    {
        return $this->redis->del($key);
    }
    
    /**
     * Check if a key exists
     */
    public function exists($key)
    {
        return (bool) $this->redis->exists($key);
    }
    
    /**
     * Remember a value using a callback
     */
    public function remember($key, $expireInSeconds, $callback)
    {
        // Check if value exists
        $value = $this->get($key);
        
        // If not, generate and store it
        if ($value === null) {
            $value = $callback();
            $this->set($key, $value, $expireInSeconds);
        }
        
        return $value;
    }
    
    /**
     * Flush all keys in the current database
     */
    public function flush()
    {
        return $this->redis->flushdb();
    }
    
    /**
     * Get the raw Redis client
     */
    public function client()
    {
        return $this->redis;
    }
}
