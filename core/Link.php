<?php

namespace kicoe\core;

class Link
{
    protected static array $bindings = [];

    public bool $is_flush_route_cache = false;

    public function __construct($conf = [])
    {
        // 读取配置信息
        /** @var Config $config */
        $config = self::makeWithArgs(Config::class, $conf);

        if ($redis_conf = $config->get('redis')) {
            /** @var Cache $cache */
            $cache = self::makeWithArgs(Cache::class, $redis_conf);
            if ($config->get('cache')) {
                if ($route_tree = $cache->getArr('s:route')) {
                    Route::setCache($route_tree);
                } else {
                    $this->is_flush_route_cache = true;
                }
            }
        }

        if ($mysql_conf = $config->get('mysql')) {
            /** @var DB $db_instance */
            $db_instance = self::makeWithArgs(DB::class, $mysql_conf);
            DB::setInstance($db_instance);
        }

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
        // 构造专属　request response
        $request = self::makeWithArgs(Request::class);
        $response = self::makeWithArgs(Response::class, $view_path);
        // merge bindings
        $route = new Route(self::$bindings);
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

    /**
     * @param string $name
     * @param $instance string|object
     */
    public static function bind(string $name, $instance)
    {
        self::$bindings[$name] = $instance;
    }

    public static function make(string $name)
    {
        return self::$bindings[$name] ?? null;
    }

    /**
     * 内部生成用户自定义的重载系统类, 不要直接绑定对象
     * @param string $name
     * @param $args
     * @return object
     */
    protected static function makeWithArgs(string $name, ...$args):object
    {
        if ($instance = self::make($name)) {
            if (is_string($instance) && class_exists($instance)) {
                $class = $instance;
                $instance = new $class(...$args);
                self::bind($class, $instance);
            } /** else {
                throw new \Exception();
            } */
        } else {
            $instance = new $name(...$args);
        }
        self::bind($name, $instance);
        return $instance;
    }
}
