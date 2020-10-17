<?php

namespace kicoe\core;

class Link
{
    protected bool $is_flush_route_cache = false;

    public function __construct($conf = [])
    {
        // 读取配置信息
        $config = new Config($conf);

        $cache = new Cache(
            $config->get('redis.host'),
            $config->get('redis.port')
        );

        if ($config->get('cache')) {
            // return []
            if ($route_tree = $cache->getArr('s:route')) {
                Route::setCache($route_tree);
            } else {
                $this->is_flush_route_cache = true;
            }
        }

        // 注入 Config Cache
        Route::scBind(Config::class, $config);
        Route::scBind(Cache::class, $cache);
    }


    public function start()
    {
        // 注入 Request Response
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
}