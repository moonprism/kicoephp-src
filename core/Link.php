<?php

namespace kicoe\core;

class Link
{
    private bool $is_flush_route_cache = false;

    public function __construct($app_config = [])
    {
        // 读取配置信息
        $config = Config::getInstance();
        $config->init([
            'cache' => true,
            'redis' => [
                'host' => 'localhost',
            ],
            'mysql' => [

            ],
            'annotation_space' => [

            ],
        ]);

        if ($config->get('cache')) {
            if ($route_tree = Cache::getInstance()->getArr('s:route')) {
                Route::setCache($route_tree);
            } else {
                $this->is_flush_route_cache = true;
            }
        }
        // 生成 Request 对象

        // 注入 Config Cache Request Response
    }


    public function start()
    {
        // 路由执行

        if ($this->is_flush_route_cache) {
            Cache::getInstance()->setArr('s:route', Route::getCache());
        }
        // 处理返回结果
    }

    /**
     * @param $path string
     * @param $call
     * @param $type string request method name
     */
    public function route(string $path, $call, string $type = 'get')
    {
        Route::add($type, $path, $call);
    }
}