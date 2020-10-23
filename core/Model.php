<?php

namespace kicoe\core;

/**
 * Class Model
 * @package kicoe\core
 * @method array select(...$columns)
 * @method self where(string $segment, ...$params)
 * @method self orWhere(string $segment, ...$params)
 * @method self orderBy(...$params)
 * @method self limit(...$params)
 * @method self join(...$params)
 * @method self leftJoin(...$params)
 * @method self rightJoin(...$params)
 * @method self having(...$params)
 * @method self columns(...$params)
 * @method self addColumns(...$params)
 * @method self removeColumns(...$params)
 * @method self from(string $table)
 * @method array get()
 * @method self first()
 * @method self groupBy(string $segment)
 * @method int save()
 * @method int count()
 * @method int delete()
 * @method int update(array $data)
 * @method static int insert(...$data)
 * @method static self fetchById($id)
 */
class Model
{
    protected ?SQL $sql = null;

    protected string $_table = '';
    protected string $primary_key = 'id';

    protected array $_old_data = [];

    public function __construct(string $table = '')
    {
        $this->_table = $table ?: self::defaultTableName();
    }

    public function getPrimaryKey()
    {
        return $this->primary_key;
    }

    public static function defaultTableName()
    {
        return strtolower(substr(static::class, strripos(static::class, '\\')+1));
    }

    public static function __callStatic(string $name, $args)
    {
        return call_user_func_array([new static(static::defaultTableName()), $name], $args);
    }

    public function __call(string $name, $args)
    {
        $res = call_user_func_array([$this->sql(), $name], $args);
        if ($res !== null) {
            return $res;
        }
        return $this;
    }

    public function sql():SQL
    {
        if ($this->sql === null) {
            $this->sql = new SQL();
            $this->sql->from($this->_table);
            $this->sql->setClass(static::class);
            $this->sql->setObj($this);
        }
        return $this->sql;
    }
}