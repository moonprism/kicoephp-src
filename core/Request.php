<?php

namespace kicoe\core;

class Request
{
    protected array $query = [];
    protected array $input = [];
    protected string $path = '';

    public function __construct()
    {
        // query
        $url_data = parse_url($_SERVER['REQUEST_URI']);
        if ($query_str = $url_data['query'] ?? '') {
            parse_str($query_str, $this->query);
            $this->input = $this->query;
        }

        // path
        $this->path = $url_data['path'];

        // all input
        if (substr($_SERVER['CONTENT_TYPE'], -4) === 'json') {
            $this->input += json_decode(file_get_contents('php://input'), true);
        } else if ($this->method() === 'POST') {
            $this->input += $_POST;
        }
    }

    public function method():string
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    public function path():string
    {
        return $this->path;
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