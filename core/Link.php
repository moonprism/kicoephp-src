<?php

namespace kicoe\core;

class Link
{
    public static function start()
    {
        // load config
        Config::load(APP_PATH.'config.php');
        // register exception
        Config::prpr('test') && Error::register();
        // uri parse
        $url_info = parse_url($_SERVER['REQUEST_URI']);
        $req = Request::getInstance();
        if (isset($url_info['query'])) {
            parse_str($url_info['query'], $data);
            $req->setGet($data);
        }
        // route
        Route::init($url_info['path']);
        Route::run();
    }
}
