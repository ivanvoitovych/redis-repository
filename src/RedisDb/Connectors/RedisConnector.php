<?php

namespace RedisDb\Connectors;

use Redis;
use RedisDb\Interfaces\IRedisConnector;

class RedisConnector implements IRedisConnector
{
    private static $Connections = [];

    public function get(): Redis
    {
        $dbIndex = 5; // TODO: config, connection string
        if (!isset(self::$Connections[$dbIndex])) {
            self::$Connections[$dbIndex] = new \Redis();
            self::$Connections[$dbIndex]->connect('127.0.0.1', 6379);
            self::$Connections[$dbIndex]->select($dbIndex);
        }
        return self::$Connections[$dbIndex];
    }
}
