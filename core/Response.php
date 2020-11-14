<?php

namespace kicoe\core;

use ReflectionClass;

class Response
{
    protected array $_header = [];
    protected string $_body = '';

    public function header(string $key, string $value)
    {
        $this->_header[] = $key . ': ' . $value;
        return $this;
    }

    public function status(int $code)
    {
        $this->_header[] = 'HTTP/1.1 ' . $code;
        return $this;
    }

    public function json($data)
    {
        $this->jsonHeader();
        $this->_body = json_encode($data);
        return $this;
    }

    protected function jsonHeader()
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
            $ref_class = new ReflectionClass($this);
            $props = $ref_class->getProperties(\ReflectionProperty::IS_PUBLIC);
            if (count($props) === 0) {
                return;
            }
            $this->json($this);
        }
        echo $this->_body;
    }

    public function redirect(string $url)
    {
        $this->header('Location', $url);
        return $this;
    }
}