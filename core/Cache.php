<?php

namespace kicoe\core;

use Redis;

/**
 * Class Cache
 * @package kicoe\core
 * @method string get(string $key, ...$redis_same)
 * @method string set(string $key, ...$redis_same)
 * @method string lPush(string $key, ...$redis_same)
 * redis 专用
 */
class Cache
{
    protected string $prefix = 'aqua:';

    /**
     * @var Redis|null
     * public Redis|null $redis = null;
     */
    public ?Redis $redis = null;

    protected array $redis_conf = [
        'host' => '127.0.0.1',
        'port' => 6379,
        'timeout' => 0
    ];

    public function __construct($conf)
    {
        $this->redis_conf = array_merge($this->redis_conf, $conf);
    }

    /**
     * 延迟加载
     * @return Redis
     */
    protected function redisCase(): Redis
    {
        if ($this->redis === null) {
            $this->redis = new Redis();
            $this->redis->connect(...array_values($this->redis_conf));
        }
        return $this->redis;
    }

    protected function realKey(string $key):string
    {
        return $this->prefix.$key;
    }

    public function __call(string $name, $args)
    {
        $args[0] = $this->realKey($args[0]);
        return $this->redisCase()->$name(...$args);
    }

    public function getArr(string $key, ...$args)
    {
        return json_decode($this->get($key, ...$args), true);
    }

    public function setArr(string $key, $value, ...$args)
    {
        return $this->set($key, json_encode($value), ...$args);
    }
}