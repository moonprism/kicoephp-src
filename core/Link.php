<?php

namespace kicoe\core;

class Link
{
    protected static array $bindings = [];

    protected bool $is_flush_route_cache = false;

    public function __construct($conf = [])
    {
        // 读取配置信息
        $config = new Config($conf);

        if ($redis_conf = $config->get('redis')) {
            $cache = new Cache($redis_conf);
            if ($config->get('cache')) {
                if ($route_tree = $cache->getArr('s:route')) {
                    Route::setCache($route_tree);
                } else {
                    $this->is_flush_route_cache = true;
                }
            }
            self::bind(Cache::class, $cache);
        }

        if ($mysql_conf = $config->get('mysql')) {
            DB::setInstance(new DB($mysql_conf));
            self::bind(DB::class, DB::getInstance());
        }

        self::bind(Config::class, $config);

        // 基础类型绑定
        self::bind('int', function (string $value):int {
            return (int)$value;
        });
        self::bind('float', function (string $value):float {
            return (float)$value;
        });
        self::bind('array', function (string $value):array {
            return explode(',', $value);
        });
    }

    public function start()
    {
        // todo
        $view_path = $this->make(Config::class)->get('space.view') ?? '';
        // merge bindings
        $route = new Route(self::$bindings);
        $request = new Request();
        $response = new Response($view_path);
        $route->scBind(Request::class, $request);
        $route->scBind(Response::class, $response);
        $this->run($route);
    }

    public function run(Route $route)
    {
        // 路由执行
        if ($this->is_flush_route_cache) {
            /** @var Cache $cache */
            $cache = $this->make(Cache::class);
            $cache->setArr('s:route', Route::getCache());
        }

        $request = $route->scMake(Request::class);
        $response = $route->scMake(Response::class);

        $route_res = Route::search($request->path(), $request->method());
        if ($route_res === []) {
            $response->status(404);
            $response->send();
            return;
        }

        list($handler, $real_param, $param_map) = $route->prepare($route_res['handler'], $route_res['params']);
        $request->setRouteParams($param_map);

        $res = call_user_func_array($handler, $real_param);

        if ($res instanceof Response) {
            $response = $res;
        } else if (is_array($res) || is_object($res)) {
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

    public function __call(string $name, $args)
    {
        if ($name === 'bind' || $name === 'make') {
            static::$name(...$args);
        }
    }

    public static function bind(string $name, $instance)
    {
        self::$bindings[$name] = $instance;
    }

    public static function make(string $name)
    {
        return self::$bindings[$name] ?? null;
    }
}