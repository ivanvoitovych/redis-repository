<?php

namespace Tests\Models;

use RedisDb\Models\BaseEntity;

class BlogPost extends BaseEntity
{
    public string $Title;
    public string $SeoTitle;
    public string $Body;
    public string $Author;
    public int $DisplayOrder = 0;
    public bool $Published = false;
    public int $Group = 0;
}
