<?php

// 不再写文件相关缓存了
namespace kicoe\core\cache;

use kicoe\core\Config;

class File implements CacheInterface
{

    protected $cachePath = '';

    public function __construct() 
    {
        $this->cachePath = APP_PATH.Config::prpr('cp').'/';
    }

    /**
     * 获取文件名
     * @param string $key 文件名
     * @return string 文件全路径
     * @throws
     */
    public function getFile($key)
    {
        $file = $this->cachePath.$key;
        if (!is_file($file)) {
            touch($file, 0755, true);
        }
        return $file;
    }

    public function has($key)
    {
        return is_file($this->getFile($key));
    }

    public function write($key, $data)
    {
        file_put_contents($this->getFile($key), serialize($data), LOCK_EX);
    }

    public function read($key)
    {
        return unserialize(file_get_contents($this->getFile($key)));
    }
}