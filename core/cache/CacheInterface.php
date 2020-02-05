<?php

namespace kicoe\core\cache;

interface CacheInterface
{
    public function has($key);
    
    public function write($key, $data);
    
    public function read($key);
}