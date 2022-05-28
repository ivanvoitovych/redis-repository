<?php

namespace RedisDb\Models;

abstract class BaseEntity
{
    public string $Id;

    public int $CreatedOn;
    public ?string $CreatedBy;

    public int $UpdatedOn;
    public ?string $UpdatedBy;
}
