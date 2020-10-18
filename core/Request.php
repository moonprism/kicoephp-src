<?php

namespace kicoe\core;

class Request
{
    protected array $query = [];
    protected array $input = [];

    protected string $path = '';

    protected array $route_param_map = [];

    public function __construct()
    {
        // query
        $url_data = parse_url($_SERVER['REQUEST_URI']);
        if ($query_str = $url_data['query'] ?? '') {
            parse_str(urldecode($query_str), $this->query);
            $this->input = $this->query;
        }

        // path
        $this->path = urldecode($url_data['path']);

        // all input
        if ($this->isJson()) {
            $this->input += json_decode(file_get_contents('php://input'), true);
        } else if ($this->method() === 'POST') {
            $this->input += $_POST;
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

    public function path($var_name = '', $default = false)
    {
        if ($var_name !== '') {
            return $this->route_param_map[$var_name] ?? $default;
        }
        return $this->path;
    }

    public function setRouteParams(array $param_map)
    {
        $this->route_param_map = $param_map;
    }

    public function query(string $key, $default = false)
    {
        return $this->query[$key] ?? $default;
    }

    public function input(string $key, $default = false)
    {
        return $this->input[$key] ?? $default;
    }
}