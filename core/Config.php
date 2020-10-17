<?php

namespace kicoe\core;

class Config
{
    protected array $config = [];

    public function __construct($conf)
    {
        $this->config = $conf;
    }

    public function get(string $key = '')
    {
        if ($key === '') {
            return $this->config;
        }
        $c = $this->config;
        foreach (explode('.', $key) as $k) {
            if ( !($c = $c[$k] ?? false) ) {
                break;
            }
        }
        return $c;
    }

    public function set(string $key, $value)
    {
        $c = &$this->config;
        foreach (explode('.', $key) as $k) {
            if (!isset($c[$k])) {
                $c[$k] = [];
            }
            $c = &$c[$k];
        }
        $c = $value;
    }
}