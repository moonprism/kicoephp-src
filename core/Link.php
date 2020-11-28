<?php

namespace kicoe\core;

use ReflectionClass;
use ReflectionFunction;

class Link
{
    protected static array $bindings = [];

    public function __construct($conf = [])
    {
        self::makeWithArgs(Config::class, $conf);

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
        /** @var Config $config */
        $config = self::make(Config::class);

        $http = self::makeWithArgs(
            Server::class,
           $config->get('swoole.host') ?: '0.0.0.0',
            $config->get('swoole.port') ?: 80
        );
        $http->set($config->get('swoole.set'));

        $http->on('request', function (\Swoole\Http\Request $request, \Swoole\Http\Response $response) use ($config) {
            if ($config->get('debug')) {
                self::allowCors($request, $response);
            }
            try {
                $this->onRequest($request, $response);
            } catch (\Exception $e) {
                $response->status(500);
                if ($config->get('debug')) {
                    // todo 详细调用栈
                    $response->end($e->getMessage());
                    return;
                }
                $response->end();
            }
        });

        $http->start();
    }

    public function onRequest(\Swoole\Http\Request $request, \Swoole\Http\Response $response)
    {
        list ($handler, $params) = Route::search($request->server['request_uri'], $request->server['request_method']);
        if (is_null($handler)) {
            $response->status(404);
            $response->end();
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
                    // 构造需要注入的类
                    $ref_inject_class = new ReflectionClass($type_name);
                    $ref_inject_parameters = $ref_inject_class->getConstructor()->getParameters();
                    // todo
                    switch ($ref_inject_parameters[0]->getType()) {
                        case \Swoole\Http\Request::class:
                            $instance = new $type_name($request);
                            break;
                        case \Swoole\Http\Response::class:
                            $instance = new $type_name($response);
                            break;
                    }
                }
                if ($instance instanceof Request) {
                    $instance->init();
                }
                $real_params[] = $instance;
                continue;
            }
            if (isset($params[$name])) {
                $real_params[] = $params[$name];
            }
        }

        $res = call_user_func_array($handler, $real_params);

        if (!$res instanceof Response) {
            $r = new Response($response);
            if (is_array($res) || is_object($res)) {
                $r->json($res);
            } else {
                $r->text($res);
            }
            $res = $r;
        }
        $res->send();
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

    public static function allowCors(\Swoole\Http\Request $request, \Swoole\Http\Response $response)
    {
        $response->header('Access-Control-Allow-Origin', $request->header['origin'] ?? '');
        $response->header('Access-Control-Allow-Methods', 'OPTIONS');
        $response->header('Access-Control-Allow-Headers', 'x-requested-with,session_id,Content-Type,token,Origin');
        $response->header('Access-Control-Max-Age', '86400');
        $response->header('Access-Control-Allow-Credentials', 'true');
    }
}
