<?php

namespace kicoe\core;

class Link
{
    protected bool $is_flush_route_cache = false;

    public function __construct($conf = [])
    {
        // 读取配置信息
        $config = new Config($conf);

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

        if ($mysql_conf = $config->get('mysql')) {
            DB::setInstance(new DB($mysql_conf));
            Route::scBind(DB::class, DB::getInstance());
        }

        Route::scBind(Config::class, $config);

        // 基础类型绑定
        Route::scBind('int', function (string $value) {
            return (int)$value;
        });
        Route::scBind('float', function (string $value) {
            return (float)$value;
        });
        Route::scBind('array', function (string $value) {
            return explode(',', $value);
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
        $search_res = Route::search($request->path());
        if ($search_res !== []) {
            $request->setRouteParams($search_res['param_map']);
            $res = call_user_func_array($search_res['handler'], $search_res['param_arr']);
            if ($res instanceof Response) {
                $response = $res;
            } else if (is_array($res)) {
                $response->json($res);
            } else {
                $response->text($res);
            }
            $response->send();
        } else {
            $response->status(404);
            $response->send();
        }
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