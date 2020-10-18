<?php

namespace kicoe\core;

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

    public function __construct($conf)
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

    public function fetch(SQL $sql):array
    {
        return $this->execute($sql)->fetch(PDO::FETCH_OBJ);
    }

    public function fetchAll(SQL $sql):array
    {
        return $this->execute($sql)->fetchAll(PDO::FETCH_OBJ);
    }

    public function fetchClass(SQL $sql, string $class)
    {
        return $this->execute($sql)->fetch(PDO::FETCH_CLASS, $class);
    }

    public function fetchClassAll(SQL $sql, string $class)
    {
        return $this->execute($sql)->fetchAll(PDO::FETCH_CLASS, $class);
    }

    public function fetchInfo(SQL $sql, $obj)
    {
        $ps = $this->execute($sql);
        $ps->setFetchMode(PDO::FETCH_INTO, $obj);
        $ps->fetch();
    }

    public function execute(SQL $sql):\PDOStatement
    {
        $sth = $this->pdoCase()->prepare($sql);
        foreach ($sql->bindings as $key => $binding) {
            $sth->bindValue($key + 1, $binding);
        }
        $sth->execute();
        return $sth;
    }

    public static function select(string $sql, ...$bindings)
    {
        return self::getInstance()->fetchAll(new SQL($sql, $bindings));
    }

    public static function table(string $name):Model
    {
        return new Model($name);
    }

    // todo
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