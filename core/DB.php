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

    protected string $sql = '';

    protected array $binds = [];

    protected array $mysql_conf = [
        'host' => '127.0.0.1',
        'port' => 3306,
        'user' => 'root',
        'passwd' => '',
        'charset' => 'utf8mb4',
    ];

    public function __construct($conf)
    {
        $this->mysql_conf += $conf;
    }

    protected function PDOCase(): PDO
    {
        if ($this->pdo === null) {
            $dsn = sprintf('mysql:host=%s:%s;dbname=%s;charset=%s',
                $this->mysql_conf['host'],
                $this->mysql_conf['port'],
                $this->mysql_conf['charset']
            );
            $this->pdo = new PDO($dsn, $this->mysql_conf['user'], $this->mysql_conf['passwd']);
        }
        return $this->pdo;
    }
}