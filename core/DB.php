<?php

namespace kicoe\core;

use Closure;
use Exception;
use PDO;

/**
 * Class DB
 * @package kicoe\core
 * mysql 专用
 */
class DB
{
    /**
     * @var PDO|null
     * public PDO|null $pdo = null;
     */
    public ?PDO $pdo = null;

    protected array $mysql_conf = [
        'db' => '',
        'host' => '127.0.0.1',
        'port' => 3306,
        'user' => 'root',
        'passwd' => '',
        'charset' => 'utf8mb4',
    ];

    public function __construct(array $conf)
    {
        $this->mysql_conf = array_merge($this->mysql_conf, $conf);
    }

    protected function pdoCase():PDO
    {
        if ($this->pdo === null) {
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $this->mysql_conf['host'],
                $this->mysql_conf['port'],
                $this->mysql_conf['db'],
                $this->mysql_conf['charset']
            );
            $this->pdo = new PDO($dsn, $this->mysql_conf['user'], $this->mysql_conf['passwd']);
        }
        return $this->pdo;
    }

    /**
     * @param mixed ...$param (string $sql, $bindings);
     * @return object|false
     */
    public function fetch(...$param)
    {
        return $this->execute(...$param)->fetch(PDO::FETCH_OBJ);
    }

    public function fetchAll(...$param):array
    {
        return $this->execute(...$param)->fetchAll(PDO::FETCH_OBJ);
    }

    public function fetchClass(string $class, ...$param)
    {
        $sth = $this->execute(...$param);
        $sth->setFetchMode(PDO::FETCH_CLASS, $class);
        return $sth->fetch();
    }

    public function fetchClassAll(string $class, ...$param):array
    {
        return $this->execute(...$param)->fetchAll(PDO::FETCH_CLASS, $class);
    }

    public function fetchInfo($obj, ...$param)
    {
        $ps = $this->execute(...$param);
        $ps->setFetchMode(PDO::FETCH_INTO, $obj);
        return $ps->fetch();
    }

    protected static array $binding_type_mapping = [
        'integer' => PDO::PARAM_INT,
        'boolean' => PDO::PARAM_BOOL,
        'NULL' => PDO::PARAM_NULL,
    ];

    public function execute(string $sql, array $bindings):\PDOStatement
    {
        $sth = $this->pdoCase()->prepare($sql);
        foreach ($bindings as $key => $binding) {
            if ($pdo_param = self::$binding_type_mapping[gettype($binding)] ?? false) {
                $sth->bindValue($key + 1, $binding, $pdo_param);
            } else {
                $sth->bindValue($key + 1, $binding);
            }
        }
        $sth->execute();
        return $sth;
    }

    public static function select(string $sql, ...$bindings)
    {
        $mysql = new SQL($sql, $bindings);
        return self::getInstance()->fetchAll($mysql, $mysql->bindings());
    }

    public static function insert(string $sql, ...$bindings)
    {
        $instance = self::getInstance();
        $instance->execute($sql, $bindings);
        return $instance->pdoCase()->lastInsertId();
    }

    public static function update(string $sql, ...$bindings):int
    {
        $mysql = new SQL($sql, $bindings);
        return self::getInstance()->execute($mysql, $mysql->bindings())->rowCount();
    }

    public static function delete(string $sql, ...$bindings):int
    {
        $mysql = new SQL($sql, $bindings);
        return self::getInstance()->execute($mysql, $mysql->bindings())->rowCount();
    }

    public static function beginTransaction()
    {
        return self::getInstance()->pdoCase()->beginTransaction();
    }

    public static function rollBack()
    {
        return self::getInstance()->pdoCase()->rollBack();
    }

    public static function commit()
    {
        return self::getInstance()->pdoCase()->commit();
    }

    /**
     * @param Closure $closure
     * @throws Exception
     */
    public static function transaction(Closure $closure)
    {
        self::beginTransaction();
        try {
            $closure();
        } catch (Exception $e) {
            self::rollBack();
            throw $e;
        }
        self::commit();
    }

    public static function table(string $name):Model
    {
        return new Model($name);
    }

    // todo swoole 下的池化
    // 这里主要是不想让服务容器make出来的变量都不带类型提示
    protected static ?self $instance = null;

    public static function setInstance(self $ins)
    {
        self::$instance = $ins;
    }

    public static function getInstance():self
    {
        return self::$instance;
    }
}