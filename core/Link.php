<?php

namespace kicoe\core;

use ReflectionClass;
use ReflectionFunction;

class Link
{
    protected static array $bindings = [];

    public function __construct($conf = [])
    {
        // 读取配置信息
        /** @var Config $config */
        $config = self::makeWithArgs(Config::class, $conf);

        // 初始化 redis
        if ($redis_conf = $config->get('redis')) {
            self::makeWithArgs(Cache::class, $redis_conf);
        }

        // 初始化 mysql
        if ($mysql_conf = $config->get('mysql')) {
            /** @var DB $db_instance */
            $db_instance = self::makeWithArgs(DB::class, $mysql_conf);
            DB::setInstance($db_instance);
        }

        // 基础类型绑定
        self::bind('string', function (string $value):string {
            return $value;
        });
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
        // todo 自定义 Request 和 Response 情况下解决多余生成
        $request = new Request();
        $response = new Response();

        // 路由执行
        list($handler, $params) = Route::search($request->path(), $request->method());
        if (is_null($handler)) {
            $response->status(404);
            $response->send();
            return;
        }

        if (is_array($handler)) {
            // [controller, method]
            $ref_class = new ReflectionClass($handler[0]);
            $ref_method = $ref_class->getMethod($handler[1]);
            $handler = [new $handler[0], $handler[1]];
            $ref_parameters = $ref_method->getParameters();
        } else {
            // Closure
            $ref_function = new ReflectionFunction($handler);
            $ref_parameters = $ref_function->getParameters();
        }
        $real_params = [];
        foreach ($ref_parameters as $parameter) {
            // 自动注入
            $name = $parameter->getName();
            $type = $parameter->getType();
            if ($type instanceof \ReflectionNamedType) {
                // 指定类型的变量
                $type_name = $type->getName();
                if ($instance = $this->make($type_name)) {
                    if ($instance instanceof \Closure) {
                        if (isset($params[$name])) {
                            $real_params[] = call_user_func($instance, $params[$name]);
                        }
                        continue;
                    }
                } else if (class_exists($type_name)) {
                    // todo
                    $instance = new $type_name;
                }
                if ($instance instanceof Request) {
                    $instance->init($params);
                }
                $real_params[] = $instance;
                continue;
            }
            if (isset($params[$name])) {
                $real_params[] = $params[$name];
            }
        }

        $res = call_user_func_array($handler, $real_params);

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