<?php

namespace kicoe\core;

class Model
{
    protected DB $db;

    protected string $table = '';
    protected string $primary_key = 'id';

    /**
     * todo
     * @var bool 自动设置 created_at 与 update_at
     */
    protected bool $timestamps = false;

    public function __construct($table = '')
    {
        $this->table = $table ?: self::defaultTableName();
    }

    public static function defaultTableName()
    {
        return strtolower(substr(static::class, strripos(static::class, '\\')+1));
    }

    public static function all()
    {
        return DB::getInstance()->fetchClassAll(new SQL('select * from '.self::defaultTableName()), self::class);
    }
}