<?php

namespace kicoe\core\traits;

Trait Singleton
{
    private static ?object $instance = null;

    private function __construct(){}

    /**
     * @return self
     */
    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}