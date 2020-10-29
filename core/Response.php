<?php

namespace kicoe\core;

class Response
{
    protected array $header = [];
    protected string $body = '';

    public function header(string $key, string $value)
    {
        $this->header[] = $key . ': ' . $value;
        return $this;
    }

    public function status(int $code)
    {
        $this->header[] = 'HTTP/1.1 ' . $code;
        return $this;
    }

    public function json($data)
    {
        $this->header('Content-type', 'text/json');
        $this->body = json_encode($data);
        return $this;
    }

    public function text($str)
    {
        $this->body = (string)$str;
        return $this;
    }

    public function send()
    {
        foreach ($this->header as $h) {
            \header($h);
        }
        if ($this->view_file !== '') {
            $view_file = $this->view_path.$this->view_file.'.php';
            if (!file_exists($view_file)) {
                throw new \Exception(sprintf('view file "%s" not exists', $view_file));
            }
            extract($this->view_vars, EXTR_SKIP);
            include $view_file;
            return;
        }
        echo $this->body;
    }

    public function redirect(string $url)
    {
        $this->header('Location', $url);
        return $this;
    }

    // 把 View 类提到这

    protected string $view_path = '';

    protected string $view_file = '';

    protected array $view_vars = [];

    public function __construct(string $view_path = '')
    {
        $this->view_path = $view_path;
    }

    public function view(string $path, array $vars = [])
    {
        $this->view_file = $path;
        if ($vars !== []) {
            $this->view_vars = $vars;
        }
        return $this;
    }

    public function with(array $vars = [])
    {
        $this->view_vars = $vars;
        return $this;
    }
}