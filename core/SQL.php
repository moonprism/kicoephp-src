<?php

namespace kicoe\core;

class SQL
{
    public string $sql = '';

    public array $bindings = [];

    public function __construct(string $sql = '', array $bindings = [])
    {
        $this->sql = $sql;
        $this->bindings = $bindings;
    }

    public function __toString()
    {
        return $this->sql;
    }
}