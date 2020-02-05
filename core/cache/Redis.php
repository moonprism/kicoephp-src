<?php

namespace kicoe\core\cache;

use kicoe\core\Config;

class Redis implements CacheInterface
{

    protected $redis = '';

    public function __construct() 
    {
        $redisConf = Config::prpr('redis');
        $this->redis = new \Redis();
        $this->redis->connect($redisConf['host'], $redisConf['port'] ?? 6379);
    }

    public function has($key)
    {
        return $this->redis->exists($key);
    }

    public function write($key, $data)
    {
        return $this->redis->set($key, $data, 114514);
    }

    public function read($key)
    {
        return $this->redis->get($key);
    }
}