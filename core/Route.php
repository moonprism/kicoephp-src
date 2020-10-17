<?php

namespace kicoe\core;

class Route
{
    /**
     * 超快速的路由前缀树实现
     * example:
     * @route get /index/{var1}
     * @route get /index/test
     * 'GET' => [
     *   'path' => '/'
     *   'handler' => [],
     *   'children' => [
     *     'i' => [
     *       'path' => 'index/',
     *       'handler' => [],
     *       'children' => [
     *         '$' => [
     *           'path' => 'var1',
     *           'handler' => ['controller', 'method'],
     *           'children' => [],
     *         ],
     *         't' => [
     *           'path' => 'test',
     *           'handler' => ['controller2', 'method2'],
     *           'children' => [],
     *         ]
     *       ]
     *     ]
     *   ]
     *   ...
     */
    protected static array $tree = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'DELETE' => [],
    ];

    // simple cache
    protected static bool $cache = false;

    // simple service container
    protected static array $bindings = [];

    public static function setCache($tree)
    {
        self::$tree = $tree;
        self::$cache = true;
    }

    public static function getCache()
    {
        return self::$tree;
    }

    public static function scBind($class_name, $instance)
    {
        self::$bindings[$class_name] = $instance;
    }

    public static function scMake($class_name)
    {
        return self::$bindings[$class_name] ?? null;
    }

    /**
     * 从类方法注解中解析路由
     * @param $class_name
     */
    public static function parseAnnotation($class_name)
    {
        if (self::$cache) {
            return;
        }
        try {
            $class = new \ReflectionClass($class_name);
        } catch (\ReflectionException $e) {
            throw new \InvalidArgumentException(sprintf('Class "%s" does not exist.', $class_name));
        }

        foreach ($class->getMethods() as $method) {
            if ($doc = $method->getDocComment()) {
                // @route get /xx/xx
                preg_match_all('/@route (.+?) (.+?)\s/', $doc, $matches, PREG_SET_ORDER);
                foreach ($matches as $mat) {
                    self::add($mat[1], $mat[2], [$class_name, $method->getName()]);
                }
            }
        }
    }

    /**
     * @param string $type GET or POST...
     * @param string $path
     * @param $handler
     */
    protected static function addRoute(string $type, string $path, $handler)
    {
        $type = strtoupper($type);
        $res = self::$tree[$type];
        if ($res === []) {
            $res = self::$tree[$type] = self::generateTreeNode('/', [], []);
        }
        $str = trim($path, '/');
        $stack = [$type];
        // init
        if ($path === '/') {
            self::$tree[$type]['handler'] = $handler;
            return;
        }
        while ($res['children'] !== [] && $str !== '') {
            $start_char = $str[0];
            // 解析栈，路由出问题从这里开始排查
            $stack[] = $start_char !== '{' ? $start_char : '$';
            if ($node = $res['children'][$start_char] ?? false) {
                $path_len = strlen($node['path']);
                if (substr($str, 0, $path_len) === $node['path']) {
                    $res = $node;
                    $str = substr($str, $path_len);
                    continue;
                } else {
                    // LCP
                    $sl = strlen($str);
                    $pl = strlen($node['path']);
                    for ($l = 0; $l < min($sl, $pl) && $str[$l] === $node['path'][$l]; $l++){};
                    $lcp_str = substr($str, 0, $l);
                    // 分裂构造node
                    $str = substr($str, $l);
                    $p_str = substr($node['path'], $l);
                    $node['path'] = $p_str;
                    $attach_node = self::generateTreeNode($lcp_str, [], [$p_str[0] => $node]);
                    if ($sl === $l) {
                        // 将分裂节点作为新node的最终目的
                        $attach_node['handler'] = $handler;
                    } else {
                        // 两开花
                        $parse_node = self::parseTreeNode($str, $handler);
                        $attach_node['children'][$parse_node[0]] = $parse_node[1];
                    }
                    $str = '';
                    self::setTreeNodeByCallStack($attach_node, $stack);
                    break;
                }
            } else {
                if ($node = $res['children']['$'] ?? false) {
                    if ($start_char === '{') {
                        // 变量直接匹配
                        $var_end_pos = strpos($str, '}');
                        if (!$var_end_pos) {
                            throw new \InvalidArgumentException(sprintf('Route to "%s": variables definition not end', $path));
                        }
                        $res = $node;
                        $str = substr($str, $var_end_pos+1);
                        continue;
                    }
                }
                // 生成一般节点直接附加
                $parse_node = self::parseTreeNode($str, $handler);
                $child_node = $parse_node[1];
                self::setTreeNodeByCallStack($child_node, $stack);
                $str = '';
                break;
            }
        }
        if ($str !== '') {
            $child_node = self::parseTreeNode($str, $handler);
            $node['children'][$child_node[0]] = $child_node[1];
            self::setTreeNodeByCallStack($node, $stack);
        }
    }

    /**
     * @param array $node
     * @param array $stack 调用栈 eg:['GET', 'i', '/', '$']
     */
    protected static function setTreeNodeByCallStack(array $node, array $stack)
    {
        $type = $stack[0];
        if (count($stack) === 1) {
            self::$tree[$type]['children'] = $node['children'];
            return;
        }
        $node_point = &self::$tree[$type];
        for ($i = 1; $i < count($stack); $i++) {
            $node_point = &$node_point['children'][$stack[$i]];
        }
        $node_point = $node;
    }

    /**
     * 一般线性节点的生成
     * @param string $path
     * @param $handler
     * @return array [ 'k', node ]
     */
    protected static function parseTreeNode(string $path, $handler)
    {
        preg_match_all('/({.+?})/', $path, $matches, PREG_OFFSET_CAPTURE);
        $path_stack = [];
        $str_index = 0;
        foreach ($matches[1] as $param) {
            $str = substr($path, $str_index, $param[1] - $str_index);
            if ($str !== '') {
                $path_stack[] = [$str[0], $str];
            }
            $var_name = substr($path, $param[1]+1, strlen($param[0])-2);
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_-]*$/', $var_name)) {
                throw new \InvalidArgumentException(sprintf('Route to "%s": illegal variable name "%s"', $path, $var_name));
            }
            $path_stack[] = ['$', $var_name];
            $str_index = $param[1] + strlen($param[0]);
            if (strlen($path) > $str_index && substr($path, $str_index, 1) !== '/') {
                throw new \InvalidArgumentException(sprintf('Route to "%s": variables must split by "/"', $path));
            }
        }

        if (strlen($path) > $str_index) {
            $str = substr($path, $str_index);
            $path_stack[] = [$str[0], $str];
        }

        $child_node = [];
        $child_key = '';
        for ($i = count($path_stack) - 1; $i >= 0; $i--) {
            $node = self::generateTreeNode($path_stack[$i][1], [], []);
            if ($child_key !== '') {
                $node['children'][$child_key] = $child_node;
            } else {
                $node['handler'] = $handler;
            }
            $child_key = $path_stack[$i][0];
            $child_node = $node;
        }
        return [($path[0] !== '{' ? $path[0] : '$'), $child_node];
    }

    /**
     * @param string $path
     * @param array $handler
     * @param array $children
     * @return array node
     */
    protected static function generateTreeNode(string $path, array $handler, array $children)
    {
        return compact('path', 'handler', 'children');
    }

    public static function get(string $path, $call)
    {
        self::add('GET',  $path, $call);
    }

    public static function post(string $path, $call)
    {
        self::add('POST',  $path, $call);
    }

    public static function put(string $path, $call)
    {
        self::add('PUT',  $path, $call);
    }

    public static function delete(string $path, $call)
    {
        self::add('DELETE',  $path, $call);
    }

    /**
     * @param string $type
     * @param string $path
     * @param $call
     */
    public static function add(string $type, string $path, $call)
    {
        if (self::$cache) {
            // if ($call instanceof \Closure) {
            //    throw new \
            // }
            return;
        }
        // 暂不支持闭包的路由缓存
        if (is_array($call)) {
            list($class_name, $method_name) = $call;
        } else if (is_string($call)) {
            list($class_name, $method_name) = explode('@', $call);
        } else if ($call instanceof \Closure) {
            self::addRoute($type, $path, $call);
            return;
        }
        try {
            $ref_class = new \ReflectionClass($class_name);
            $ref_method = $ref_class->getMethod($method_name);
        } catch (\ReflectionException $e) {
            throw new \InvalidArgumentException(sprintf('Class "%s" or Function "%s" does not exist.', $class_name, $method_name));
        }
        self::addRoute($type, $path, [$ref_class->getName(), $ref_method->getName()]);
    }

    /**
     * @param $path
     * @param string $type
     * @return array 返回一个可供执行的 info
     */
    public static function search($path, $type = 'GET')
    {
        $res = self::$tree[$type];
        $str = trim($path, '/');
        $params = [];
        //$stack = [];
        while ($res['children'] !== [] && $str !== '') {
            $start_char = $str[0];
            if ($node = $res['children'][$start_char] ?? false) {
                $path_len = strlen($node['path']);
                if (substr($str, 0, $path_len) === $node['path']) {
                    //$stack[] = $start_char;
                    $res = $node;
                    $str = substr($str, $path_len);
                    continue;
                }
            }
            if ($node = $res['children']['$'] ?? false) {
                // 暂定参数后只能接 /
                $var_name = $node['path'];
                if ($after_node = $node['children']['/'] ?? false) {
                    $pos = strpos($str, '/');
                    if ($pos) {
                        $param = substr($str, 0, $pos);
                        $after_pa = substr($str, $pos);
                        $path_len = strlen($after_node['path']);
                        if (substr($after_pa, 0, $path_len) === $after_node['path']) {
                            // 截取实际传递的到 / 位置的参数
                            // $stack[] = '$';
                            // $stack[] = '/';
                            $params[$var_name] = $param;
                            $res = $after_node;
                            $str = substr($after_pa, $path_len);
                            continue;
                        }
                    }
                }
                // 将剩余部分作为参数传入
                $params[$var_name] = $str;
                // $stack[] = '$';
                $res = $node;
                $str = '';
            }
            break;
        }
        if ($res['handler'] !== [] && $str === '') {
            return [
                'handler' => $res['handler'],
                'params' => $params
            ];
        }
        return [];
    }

    public static function searchAndExecute(...$p)
    {
        $result = self::search(...$p);
        if ($result !== []) {
            if (is_array($result['handler'])) {
                $class_name = $result['handler'][0];
                $method_name = $result['handler'][1];
                $ref_class = new \ReflectionClass($class_name);
                $ref_method = $ref_class->getMethod($method_name);
                $callable_handler = [new $class_name, $method_name];
                $ref_parameters = $ref_method->getParameters();
            } else {
                $ref_function = new \ReflectionFunction($result['handler']);
                $callable_handler = $result['handler'];
                $ref_parameters = $ref_function->getParameters();
            }
            $params = [];
            foreach ($ref_parameters as $parameter) {
                // 自动注入
                if ($type = $parameter->getType()) {
                    if ($instance = self::scMake($type->getName())) {
                        $params[] = $instance;
                    }
                } else {
                    if ($param = $result['params'][$parameter->getName()] ?? false) {
                        $params[] = $param;
                    }
                }
            }
            return call_user_func_array($callable_handler, $params);
        }
        return null;
    }
}