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
        echo $this->body;
    }

    public function redirect($url)
    {
        $this->header('Location', $url);
        $this->send();
    }
}