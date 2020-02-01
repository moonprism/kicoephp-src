<?php

namespace kicoe\core;

class Load
{
    public static function link_start()
    {
        // load config
        Config::load(APP_PATH.'config.php');
        // register exception
        Config::prpr('test') && Error::register();
        // route
        Request::getInstance()->route();
        Route::run();
    }
}