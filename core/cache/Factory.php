<?php

namespace kicoe\core\cache;

class Factory
{
    private static $instanceMap = [];

    public static function getInstance(string $className)
    {
        $class = "\kicoe\core\cache\\".$className;
        if (!isset(self::$instanceMap[$class])) {
            self::$instanceMap[$class] = new $class();
        }
        return self::$instanceMap[$class];
    }
}