<?php

namespace kicoe\core;

use ReflectionClass;

class Request
{
    protected array $_query = [];
    protected array $_input = [];

    protected string $_path = '';
    protected string $_url = '';

    public function __construct()
    {
        // query
        $url_data = parse_url($_SERVER['REQUEST_URI']);
        if ($query_str = $url_data['query'] ?? false) {
            parse_str(urldecode($query_str), $this->_query);
            $this->_input = $this->_query;
        }

        // path
        $this->_path = urldecode($url_data['path']);

        // all input
        if ($this->isJson()) {
            $this->_input = array_merge($this->_input, json_decode(file_get_contents('php://input'), true));
        } else if ($this->method() === 'POST') {
            $this->_input = array_merge($this->_input, $_POST);
        }

        // url
        $this->_url = $_SERVER['HTTP_HOST'].$this->_path.($query_str ? '?'.$query_str : '');
    }

    public function init()
    {
        // 附加所有 input 到公共属性
        $ref_class = new ReflectionClass($this);
        $props = $ref_class->getProperties(\ReflectionProperty::IS_PUBLIC);
        foreach ($props as $prop) {
            $prop_name = $prop->getName();
            if (isset($this->_input[$prop_name])) {
                $this->$prop_name = $this->input($prop_name);
            } else if (!isset($this->$prop_name)) {
                // 没有默认值的参数没传则抛出异常
                throw new \InvalidArgumentException(sprintf('%s property %s not exists', static::class, $prop_name));
            }
        }
    }

    public function isJson():bool
    {
        return substr($_SERVER['CONTENT_TYPE'], -4) === 'json';
    }

    public function method():string
    {
        return $_SERVER['REQUEST_METHOD'];
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
        return $this->_query[$key] ?? $default;
    }

    public function input(string $key, $default = false)
    {
        return $this->_input[$key] ?? $default;
    }
}
