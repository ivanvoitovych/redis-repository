<?php

namespace RedisDb\Connectors;

use Redis;
use RedisDb\Interfaces\IRedisConnector;

class RedisConnector implements IRedisConnector
{
    private static $Connections = [];
    private int $dbIndex = 0;

    public function __construct(int $dbIndex = 0)
    {
        $this->dbIndex = $dbIndex;
    }

    public function get(): Redis
    {
        if (!isset(self::$Connections[$this->dbIndex])) {
            self::$Connections[$this->dbIndex] = new \Redis();
            self::$Connections[$this->dbIndex]->connect('127.0.0.1', 6379);
            self::$Connections[$this->dbIndex]->select($this->dbIndex);
        }
        return self::$Connections[$this->dbIndex];
    }
}
