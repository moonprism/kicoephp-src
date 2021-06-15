<?php

namespace kicoe\core;

use ReflectionClass;
use ReflectionException;

class Route
{
    /**
     * 超快速的路由前缀树实现
     * example:
     * @route get /index/{var1}
     * @route get /index/test
     * 'GET' => {
     *   'path' => '/'
     *   'handler' => [],
     *   'children' => [
     *     'i' => {
     *       'path' => 'index/',
     *       'handler' => [],
     *       'children' => [
     *         '$' => {
     *           'path' => 'var1',
     *           'handler' => ['controller', 'method'],
     *           'children' => [],
     *         },
     *         't' => {
     *           'path' => 'test',
     *           'handler' => ['controller', 'method2'],
     *           'children' => [],
     *         }
     *       ]
     *     }
     *   ]
     * }
     */
    public static array $tree = [];

    /**
     * 从类方法注解中解析路由
     * @param string $class_name
     */
    public static function parseAnnotation(string $class_name)
    {
        try {
            $class = new ReflectionClass($class_name);
        } catch (ReflectionException $e) {
            throw new \InvalidArgumentException(sprintf('Class "%s" does not exist.', $class_name));
        }

        foreach ($class->getMethods() as $method) {
            if ($doc = $method->getDocComment()) {
                // @route get /path/{var_name}/...
                preg_match_all('/@route (.+?) (.+?)\s/', $doc, $matches, PREG_SET_ORDER);
                foreach ($matches as $mat) {
                    self::add($mat[1], $mat[2], [$class_name, $method->getName()]);
                }
            }
        }
    }

    /**
     * @param string $type [GET, POST, PUT, DELETE]
     * @param string $path
     * @param $handler
     */
    protected static function addRoute(string $type, string $path, $handler)
    {
        $type = strtoupper($type);
        if (!isset(self::$tree[$type])) {
            self::$tree[$type] = self::newTreeNode('/');
        }
        $node = self::$tree[$type];

        if ($path === '/') {
            $node->handler = $handler;
            return;
        }
        $path = trim($path, '/');

        // 路由格式转换 /{var_name} => /$var_name
        $path = preg_replace('/{(.+?)}/', '\$${1}', $path);
        $stack = [$type];

        while ($node->children !== [] && $path !== '') {
            $stack[] = $start_char = $path[0];
            if (isset($node->children[$start_char])) {
                $node = $node->children[$start_char];
                if ($start_char === '$') {
                    // 变量直接匹配
                    $var_end_pos = strpos($path, '/');
                    if ($var_end_pos === false) $var_end_pos = strlen($path);
                    $node->path .= '|'.substr($path, 1, $var_end_pos-1);
                    $path = substr($path, $var_end_pos ? $var_end_pos : 0);
                    continue;
                }
                $path_len = strlen($node->path);
                if (substr($path, 0, $path_len) === $node->path) {
                    $path = substr($path, $path_len);
                    if ($path === '') {
                        $node->handler = $handler;
                    }
                    continue;
                } else {
                    // LCP
                    $sl = strlen($path);
                    $pl = strlen($node->path);
                    for ($l = 0; $l < min($sl, $pl) && $path[$l] === $node->path[$l]; $l++){};
                    $lcp_str = substr($path, 0, $l);
                    // 分裂构造node
                    $path = substr($path, $l);
                    $p_str = substr($node->path, $l);
                    $node->path = $p_str;
                    $attach_node = self::newTreeNode($lcp_str, [], [$p_str[0] => $node]);
                    if ($sl === $l) {
                        // 将分裂节点作为新node的最终目的
                        $attach_node->handler = $handler;
                    } else {
                        // 两开花
                        $parse_node = self::parseTreeNode($path, $handler);
                        $attach_node->children[$parse_node[0]] = $parse_node[1];
                    }
                    self::setTreeNodeByCallStack($attach_node, $stack);
                    $path = '';
                    break;
                }
            } else {
                // 生成一般节点直接附加
                $parse_node = self::parseTreeNode($path, $handler);
                $child_node = $parse_node[1];
                self::setTreeNodeByCallStack($child_node, $stack);
                $path = '';
                break;
            }
        }
        if ($path !== '') {
            $child_node = self::parseTreeNode($path, $handler);
            $node->children[$child_node[0]] = $child_node[1];
        }
    }

    /**
     * @param object $node
     * @param array $stack 调用栈 eg:['GET', 'i', '/', '$']
     */
    protected static function setTreeNodeByCallStack(object $node, array $stack)
    {
        $search = &self::$tree[$stack[0]];
        array_shift($stack);
        foreach ($stack as $k) {
            $search = &$search->children[$k];
        }
        $search = $node;
    }

    /**
     * 一般线性节点的生成
     * @param string $path
     * @param $handler
     * @return array [ 'k', node ]
     */
    protected static function parseTreeNode(string $path, $handler):array
    {
        $node = $root = self::newTreeNode($path);

        while ($path !== '') {
            $pos = strpos($path, '$');
            if ($pos === false) {
                $pos = strlen($path);
            }
            if ($node_path = substr($path, 0, $pos)) {
                // 将开头不包含$的部分加入普通节点
                $child_node = $node->children[$node_path[0]] = self::newTreeNode($node_path);
                $node = $child_node;
            }
            $path = substr($path, $pos);
            if ($path !== '') {
                $var_end_pos = strpos($path, '/');
                if ($var_end_pos === false) $var_end_pos = strlen($path);
                $var_name = substr($path, 1, $var_end_pos-1);
                // 找到$，生成变量节点
                $path = substr($path, $var_end_pos);
                $child_node = $node->children['$'] = self::newTreeNode($var_name);
                $node = $child_node;
            }
        }
        $node->handler = $handler;
        $key = array_keys($root->children)[0];
        $value = array_values($root->children)[0];
        return [$key, $value];
    }

    /**
     * @param string $path
     * @param array $handler [class, method]
     * @param array $children
     * @return object node 不想再加新类了
     */
    protected static function newTreeNode(string $path, array $handler = [], array $children = []):object
    {
        return (object)compact('path', 'handler', 'children');
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
        if (is_array($call)) {
            list($class_name, $method_name) = $call;
        } else if (is_string($call)) {
            list($class_name, $method_name) = explode('@', $call);
        } else if ($call instanceof \Closure) {
            self::addRoute($type, $path, $call);
            return;
        } else {
            return;
        }
        try {
            $ref_class = new ReflectionClass($class_name);
            $ref_method = $ref_class->getMethod($method_name);
        } catch (ReflectionException $e) {
            throw new \InvalidArgumentException(sprintf('Handler [%s, %s] does not exist.', $class_name, $method_name));
        }
        self::addRoute($type, $path, [$ref_class->getName(), $ref_method->getName()]);
    }

    /**
     * @param $path
     * @param string $type
     * @return array 返回一个简单的  [handler, params]
     */
    public static function search(string $path, string $type = 'GET'):array
    {
        $res = self::$tree[strtoupper($type)] ?? null;
        $str = trim($path, '/');
        $params = [];
        if ($res === null) return [null, null];
        while ($res->children !== [] && $str !== '') {
            $start_char = $str[0];
            if ($node = $res->children[$start_char] ?? false) {
                $path_len = strlen($node->path);
                if (substr($str, 0, $path_len) === $node->path) {
                    $res = $node;
                    $str = substr($str, $path_len);
                    continue;
                }
            }
            if ($node = $res->children['$'] ?? false) {
                // 暂定参数后只能接 /
                $var_name = $node->path;
                if ($after_node = $node->children['/'] ?? false) {
                    if ($pos = strpos($str, '/')) {
                        $param = substr($str, 0, $pos);
                        $after_pa = substr($str, $pos);
                        $path_len = strlen($after_node->path);
                        if (substr($after_pa, 0, $path_len) === $after_node->path) {
                            // 相同参数使用'|'分割
                            foreach (explode('|', $var_name) as $name) {
                                $params[$name] = $param;
                            }
                            $res = $after_node;
                            $str = substr($after_pa, $path_len);
                            continue;
                        }
                    }
                }
                foreach (explode('|', $var_name) as $name) {
                    $params[$name] = $str;
                }
                $res = $node;
                $str = '';
            }
            break;
        }
        $handler = $res->handler;
        if ($handler !== [] && $str === '') {
            return [$handler, $params];
        }
        return [null, null];
    }
}
