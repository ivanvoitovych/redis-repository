<?php

namespace RedisDb\Models;

abstract class BaseEntityDbMap
{
    public const PROPERTY_UpdatedOn = 'UpdatedOn';
    public const PROPERTY_CreatedOn = 'CreatedOn';

    public const ORDER_BY_CreatedOn = 'CreatedOn';

    /**
     * 
     * @var array<string,array{Unique:bool,Properties:string[]}>
     */
    static array $Indexes = [];
    static array $Positions = [];
}
