<?php

namespace RedisDb\Interfaces;

use Redis;

interface IRedisConnector
{
    public function get(): Redis;
}