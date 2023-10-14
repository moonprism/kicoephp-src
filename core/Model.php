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
 * @method static static fetchById($id)
 */
#[\AllowDynamicProperties]
class Model
{
    const TABLE = '';
    const PRIMARY_KEY = 'id';

    protected ?SQL $_sql = null;
    protected string $_table = '';
    protected array $_old_data = [];

    // todo php8 语法糖
    public function __construct(string $_table = '')
    {
        $this->_table = $_table ?: static::TABLE ?: self::defaultTableName();
    }

    public function getPrimaryKey()
    {
        return static::PRIMARY_KEY;
    }

    public static function defaultTableName()
    {
        return strtolower(substr(static::class, strripos(static::class, '\\')+1));
    }

    public static function __callStatic(string $name, $args)
    {
        return call_user_func_array([new static(), $name], $args);
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
        if ($this->_sql === null) {
            $this->_sql = new SQL();
            $this->_sql->setObj($this);
            $this->_sql->from($this->_table);
        }
        return $this->_sql;
    }
}
