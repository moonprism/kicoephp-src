<?php

namespace kicoe\core;

class Response
{
    protected array $_header = [];
    protected string $_body = '';

    protected \Swoole\Http\Response $_response;

    public function __construct(\Swoole\Http\Response $response)
    {
        $this->_response = $response;
    }

    public function header(string $key, string $value)
    {
        $this->_response->header($key, $value);
        return $this;
    }

    public function status(int $code)
    {
        $this->_response->status($code);
        return $this;
    }

    public function json($data)
    {
        $this->jsonHeader();
        $this->_body = json_encode($data);
        return $this;
    }

    public function jsonHeader()
    {
        $this->header('Content-type', 'text/json');
    }

    public function text($str)
    {
        $this->_body = (string)$str;
        return $this;
    }

    public function send()
    {
        foreach ($this->_header as $h) {
            \header($h);
        }
        if ($this->_body === '') {
            $this->json($this);
            if ($this->_body === '{}') {
                // 没有public变量置空
                $this->_body = '';
            }
        }
        $this->_response->end($this->_body);
    }

    public function redirect(string $url)
    {
        $this->header('Location', $url);
        return $this;
    }
}
