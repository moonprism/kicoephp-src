<?php

namespace kicoe\core;

use kicoe\core\traits\Singleton;

class Config
{
    use Singleton;

    protected array $config = [];

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

    public function init($value)
    {
        $this->config = $value;
    }
}