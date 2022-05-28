<?php

namespace RedisDb\Interfaces;

interface IMapper
{
    function map(string $type, object $stdObject, $instance = null);
}