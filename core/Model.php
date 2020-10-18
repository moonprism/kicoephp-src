<?php

namespace kicoe\core;

class Model
{
    protected $connection;

    protected string $table = '';
    protected string $primary_key = 'id';

    /**
     * todo
     * @var bool 自动设置 created_at 与 update_at
     */
    protected bool $timestamps = false;

    public function __construct()
    {
        if ($this->table === '') {
            // 默认表名和类名一致
            $this->table = strtolower(substr(static::class, strripos(static::class, '\\')+1));
        }
    }

    public function all()
    {

    }
}