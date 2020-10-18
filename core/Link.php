<?php

namespace kicoe\core;

class Link
{
    protected bool $is_flush_route_cache = false;

    public function __construct($conf = [])
    {
        // 读取配置信息
        $config = new Config($conf);

        // Config & Cache 绑定
        if ($redis_conf = $config->get('redis')) {
            $cache = new Cache($redis_conf);
            if ($config->get('cache')) {
                // return []
                if ($route_tree = $cache->getArr('s:route')) {
                    Route::setCache($route_tree);
                } else {
                    $this->is_flush_route_cache = true;
                }
            }
            Route::scBind(Cache::class, $cache);
        }
        Route::scBind(Config::class, $config);

        // 基础类型绑定
        Route::scBind('int', function ($value) {
            return (int)$value;
        });
        Route::scBind('float', function ($value) {
            return (float)$value;
        });
        Route::scBind('array', function ($value) {
            return explode(',', $value);
        });
        Route::scBind('bool', function ($value) {
            if ($value === 'true') {
                return true;
            } else if ($value === 'false') {
                return false;
            }
            return (bool)$value;
        });
    }


    public function start()
    {
        // Request Response 绑定
        $request = new Request();
        $response = new Response();
        Route::scBind(Request::class, $request);
        Route::scBind(Response::class, $response);

        // 路由执行
        if ($this->is_flush_route_cache) {
            /**
             * @var $cache Cache
             */
            $cache = Route::scMake(Cache::class);
            $cache->setArr('s:route', Route::getCache());
        }
        $res = Route::searchAndExecute($request->path());

        // 处理返回结果
        if ($res instanceof Response) {
            $response = $res;
        } else if (is_array($res)) {
            $response->json($res);
        } else {
            $response->text($res);
        }
        $response->send();
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

    public function make(string $name)
    {
        return Route::scMake($name);
    }

    public function bind(string $name, $instance)
    {
        Route::scBind($name, $instance);
    }
}