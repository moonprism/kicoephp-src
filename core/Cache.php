<?php

namespace kicoe\core;

use kicoe\core\traits\Singleton;

/**
 * Class Cache
 * Redis专用
 * @package kicoe\core
 * @method string get(string $key, ...$redis_same)
 * @method string set(string $key, ...$redis_same)
 */
class Cache
{
    use Singleton;

    const CACHE_PREFIX = 'aqua:';

    public \Redis|null $redis = null;

    protected function redisCase()
    {
        if ($this->redis === null) {
            $this->redis = new \Redis();
            $this->redis->connect(Config::getInstance()->get('redis'));
        }
        return $this->redis;
    }

    protected function realKey($key)
    {
        return self::CACHE_PREFIX.$key;
    }

    public function __call($name, $arguments)
    {
        $arguments[0] = $this->realKey($arguments[0]);
        return $this->redisCase()->$name(...$arguments);
    }

    public function getArr($key, ...$arguments)
    {
        return json_decode($this->get($key, ...$arguments));
    }

    public function setArr($key, $value, ...$arguments)
    {
        return json_decode($this->set($key, json_encode($value), ...$arguments));
    }
}