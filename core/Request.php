<?php

namespace kicoe\core;

use ReflectionClass;

class Request
{
    protected string $_path = '';
    protected string $_url = '';

    protected \Swoole\Http\Request $_request;

    public function __construct(\Swoole\Http\Request $request)
    {
        $this->_request = $request;

        // path
        $this->_path = urldecode($request->server['path_info']);

        // url
        $query_str = $request->server['query_string'];
        $this->_url = $request->server['http_host'].$this->_path.($query_str ? '?'.$query_str : '');
    }

    public function init()
    {
        // 附加所有 input 到公共属性
        $ref_class = new ReflectionClass($this);
        $props = $ref_class->getProperties(\ReflectionProperty::IS_PUBLIC);
        foreach ($props as $prop) {
            $prop_name = $prop->getName();
            if (($var = $this->input($prop_name), null) !== null) {
                $this->$prop_name = $var;
            } else if (!isset($this->$prop_name)) {
                throw new \InvalidArgumentException(sprintf('%s property %s not exists', static::class, $prop_name));
            }
        }
    }

    public function path()
    {
        return $this->_path;
    }

    public function url()
    {
        return $this->_url;
    }

    public function query(string $key, $default = false)
    {
        return $this->_request->get[$key] ?? $default;
    }

    public function input(string $key, $default = false)
    {
        return $this->_request->post[$key] ?? $this->_request->get[$key] ?? $default;
    }

    public function header(string $key, $default = false)
    {
        return $this->_request->header[$key] ?? $default;
    }
}
