<?php

namespace kicoe\core;

use Redis;

/**
 * Class Cache
 * @package kicoe\core
 * @method string get(string $key, ...$redis_same)
 * @method string set(string $key, ...$redis_same)
 * Redis专用
 */
class Cache
{
    const CACHE_PREFIX = 'aqua:';

    public Redis|null $redis = null;

    protected array $connect_arguments = [];

    public function __construct(string $host = '127.0.0.1', int $port = 6379)
    {
        $this->connect_arguments = [$host, $port];
    }

    /**
     * 延迟加载
     * @return Redis
     */
    protected function redisCase(): Redis
    {
        if ($this->redis === null) {
            $this->redis = new Redis();
            $this->redis->connect(...$this->connect_arguments);
        }
        return $this->redis;
    }

    protected function realKey($key):string
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
        return json_decode($this->get($key, ...$arguments), true);
    }

    public function setArr($key, $value, ...$arguments)
    {
        return $this->set($key, json_encode($value), ...$arguments);
    }
}