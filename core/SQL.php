<?php

namespace kicoe\core;

/**
 * Class SQL
 * @package kicoe\core
 * a simple sql builder
 */
class SQL
{
    public string $sql = '';

    public array $bindings = [
        'join' => [],
        'where' => [],
        'having' => [],
        'limit' => [],
    ];

    public array $columns = [];

    public string $from = '';

    public string $join = '';

    public string $where = '';

    public string $group_by = '';

    public string $having = '';

    public string $order_by = '';

    public string $limit = '';

    /**
     * 非 Model 使用该类的话, eg:
     * $sql = new SQL('select * from a where id in (?) and c > ?', [$id1, $id2], 10);
     * $sql 可以转换为 string "select * from a where id in(?,?) and c > ?"
     * $sql->bindings() : [$id1, $id2, 10]
     * DB::execute($sql, $sql->bindings())
     * SQL constructor.
     * @param string $sql
     * @param array $bindings
     */
    public function __construct(string $sql = '', array $bindings = [])
    {
        if ($sql !== '') {
            $this->bindings['fake'] = $this->getBindingsAndParseSql($bindings, $sql);
        }
    }

    public function __toString()
    {
        return $this->sql;
    }

    /**
     * 返回实际用来绑定的值列表
     * @param array $bindings
     * @return array
     */
    public function bindings(array $bindings = []):array
    {
        if ($bindings === []) {
            $bindings = $this->bindings;
        }
        return array_merge(...array_values($bindings));
    }

    public function columns(...$columns)
    {
        $this->columns = $columns;
    }

    public function addColumns(...$columns)
    {
        $this->columns = array_merge($this->columns, $columns);
    }

    public function removeColumns(...$columns)
    {
        $this->columns = array_diff($this->columns, $columns);
    }

    protected function parseColumns(...$columns):string
    {
        $this->columns = array_merge($this->columns, $columns);
        if ($this->join !== '') {
            $vars = get_class_vars($this->class);
            foreach ($this->columns as &$column) {
                if (array_key_exists($column, $vars)) {
                    $column = $this->from.'.'.$column;
                }
            }
        }
        return $this->columns === [] ? '*' : implode(',', $this->columns);
    }

    public function where(string $segment, ...$params)
    {
        $this->baseWhere('and', $segment, $params);
    }

    public function andWhere(string $segment, ...$params)
    {
        $this->baseWhere('and', $segment, $params);
    }

    public function orWhere(string $segment, ...$params)
    {
        $this->baseWhere('or', $segment, $params);
    }

    protected function baseWhere(string $boolean, string $segment, $params)
    {
        if (str_replace([' ', '?'], '', $segment) === $segment) {
            $segment .= ' = ?';
        }
        if ($this->where !== '') {
            $this->where .= $boolean. ' ';
        } else {
            $this->where .= 'where ';
        }
        $this->where .= $segment.' ';
        $this->bindings['where'] = array_merge($this->bindings['where'], $params);
    }

    public function from(string $table)
    {
        $this->from = $table;
    }

    public function join(string $table, string $segment, ...$params)
    {
        $this->baseJoin('inner', $table, $segment, $params);
    }

    public function leftJoin(string $table, string $segment, ...$params)
    {
        $this->baseJoin('left', $table, $segment, $params);
    }

    public function rightJoin(string $table, string $segment, ...$params)
    {
        $this->baseJoin('right', $table, $segment, $params);
    }

    public function baseJoin(string $type, string $table, string $segment, $params)
    {
        $this->join .= $type.' join '.$table;
        if ($segment) {
            $this->join .= ' on '.$segment;
            $this->bindings['join'] = array_merge($this->bindings['join'], $params);
        }
    }

    public function groupBy(string $segment)
    {
        $this->group_by = 'group by '.$segment;
    }

    public function having(string $segment, ...$params)
    {
        $this->having = 'having '.$segment;
        $this->bindings['having'] = $params;
    }

    public function orderBy(string $column, string $stands)
    {
        if ($this->order_by === '') {
            $this->order_by = "order by $column $stands";
        } else {
            $this->order_by = ", $column $stands";
        }
    }

    public function limit(...$params)
    {
        $this->limit = 'limit ?'.(isset($params[1]) ? ', ?' : '');
        $this->bindings['limit'] = $params;
    }

    protected function generateSqlBySegmentStack(...$segments)
    {
        return implode(' ', array_filter($segments));
    }

    /**
     * 写给查询的, 用的地方多
     * @return string
     */
    protected function parseSql()
    {
        return $this->generateSqlBySegmentStack(
            'select',
            $this->parseColumns(),
            'from',
            $this->from,
            $this->join,
            $this->where,
            $this->group_by,
            $this->having,
            $this->order_by,
            $this->limit
        );
    }

    /**
     * 返回正确使用的 bindings
     * @param array $bindings
     * @param string $sql
     * @return array
     */
    public function getBindingsAndParseSql(array $bindings = [], string $sql = ''):array
    {
        if ($bindings === []) {
            $bindings = $this->bindings();
        }
        $this->sql = $sql ?: $this->parseSql();
        $sql_list = explode('?', $this->sql);
        for ($i = $j = 0; $i < count($bindings); $i++) {
            // 定位 ?
            if (is_array($bindings[$i])) {
                $j++;
                $count = count($bindings[$i]);
                array_splice($bindings, $i, 1, $bindings[$i]);
                if ($count > 1) {
                    // 替换 ?,?,?
                    array_splice($sql_list, $j, 0, array_fill(0, $count-1, ','));
                    $j += $count - 1;
                }
                $i += $count;
            }
        }
        $this->sql = implode('?', $sql_list);
        return $bindings;
    }

    /**
     * 虽然下面是执行代码...
     * 为了优雅，支持 Model 的全静态调用又不想加类只好这样了
     */

    // 执行的 Model 信息
    protected string $class = '';

    protected ?Model $obj = null;

    public function setClass(string $class)
    {
        $this->class = $class;
        $class_vars = get_class_vars($this->class);
        $this->columns = array_keys($class_vars);
    }

    public function setObj(Model $obj)
    {
        $this->obj = $obj;
    }

    public function select(...$columns)
    {
        $this->columns(...$columns);
        return $this->get();
    }

    public function get()
    {
        $bindings = $this->getBindingsAndParseSql();
        return DB::getInstance()->fetchClassAll($this->class, $this->sql, $bindings);
    }

    public function count():int
    {
        $sql = $this->generateSqlBySegmentStack(
            'select count(*) from',
            $this->from,
            $this->join,
            $this->where,
            $this->group_by,
            $this->having,
        );
        // 不可重用
        $bindings = $this->bindings(array_diff_key($this->bindings, ['limit'=>'']));
        return DB::getInstance()->execute($sql, $this->getBindingsAndParseSql($bindings))->fetchColumn();
    }

    public function all()
    {
        return $this->get();
    }

    protected array $_origin_vars = [];

    public function fetchById($id)
    {
        if (is_array($id)) {
            foreach ($id as $k => $v) {
                $this->where("{$k} = ?", $v);
            }
        } else {
            $this->where($this->obj->getPrimaryKey()." = ?", $id);
        }
        return $this->first();
    }

    public function first()
    {
        $this->limit(1);
        $bindings = $this->getBindingsAndParseSql();
        $res = DB::getInstance()->fetchInfo($this->obj, $this->sql, $bindings);
        $this->_origin_vars = get_object_vars($this->obj);
        return $res;
    }

    public function save()
    {
        $obj_vars = get_object_vars($this->obj);
        $primary_key = $this->obj->getPrimaryKey();
        if ($primary = $obj_vars[$primary_key] ?? false) {
            $diff_vars = array_diff($obj_vars, $this->_origin_vars);
            $this->where = '';
            $this->bindings['where'] = [];
            $this->_origin_vars = array_merge($this->_origin_vars, $obj_vars);
            $this->where($this->obj->getPrimaryKey().' = ?', $primary);
            return $this->update($diff_vars);
        } else {
            $last_id = $this->insert($obj_vars);
            $this->obj->$primary_key = $last_id;
            return $last_id;
        }
    }

    public function update(array $data)
    {
        if ($data === []) {
            return 0;
        }
        if ($data instanceof Model) {
            $data = get_object_vars($data);
        }
        $segments = [];
        $bindings = [];
        foreach ($data as $k => $v) {
            $segments[] = "$k = ?";
            $bindings[] = $v;
        }
        $this->sql = $this->generateSqlBySegmentStack(
            'update',
            $this->from,
            'set',
            implode(',', $segments),
            $this->where,
            $this->order_by,
            $this->limit
        );
        return DB::update($this->sql, ...array_merge($bindings, $this->bindings['where'], $this->bindings['limit']));
    }

    public function insert(...$data)
    {
        if ($data === []) {
            return 0;
        }
        $segments = [];
        $bindings = [];
        if (is_object($data[0])) {
            // 转换成数组
            $data = array_map(function ($model) {
                return get_object_vars($model);
            }, $data);
        }
        foreach ($data as $v) {
            $bindings = array_merge($bindings, array_values($v));
            $segments[] = '('.implode(',', array_fill(0, count($v), '?')).')';
        }
        $this->sql = $this->generateSqlBySegmentStack(
            'insert into',
            $this->from,
            '('.implode(',', array_keys($data[0])).')',
            'values',
            implode(',', $segments)
        );
        return DB::insert($this->sql, ...$bindings);
    }

    public function delete()
    {
        $obj_vars = get_object_vars($this->obj);
        if ($primary = $obj_vars[$this->obj->getPrimaryKey()] ?? false) {
            $this->where = 'where '.$this->obj->getPrimaryKey().' = ?';
            $this->bindings['where'][] = $primary;
            $this->sql = $this->generateSqlBySegmentStack(
                'delete from',
                $this->from,
                $this->where,
            );
            $bindings = $this->bindings['where'];
        } else {
            if ($this->where === '') {
                return 0;
            }
            $this->sql = $this->generateSqlBySegmentStack(
                'delete from',
                $this->from,
                $this->where,
                $this->order_by,
                $this->limit
            );
            $bindings = array_merge($this->bindings['where'], $this->bindings['limit']);
        }
        return DB::delete($this->sql, ...$bindings);
    }
}