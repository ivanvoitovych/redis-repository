<?php

namespace Tests\Models;

use RedisDb\Models\BaseEntityDbMap;

class BlogPostDbMap extends BaseEntityDbMap
{
    public const ORDER_BY_ORDER = 'DisplayOrder';

    public const PROPERTY_Published = 'Published';
    public const PROPERTY_Group = 'Group';
    public const PROPERTY_SeoTitle = 'SeoTitle';

    static array $Indexes = [
        'SeoTitle_UX' => [
            'Unique' => true,
            'Properties' => [self::PROPERTY_SeoTitle]
        ],
        'Published_IX' => [
            'Unique' => false,
            'Properties' => [self::PROPERTY_Published]
        ],
        'Published-Group_IX' => [
            'Unique' => false,
            'Properties' => [
                self::PROPERTY_Group,
                self::PROPERTY_Published
            ]
        ]
    ];

    static array $Positions = [
        self::ORDER_BY_ORDER => [
            'Properties' => [self::ORDER_BY_ORDER]
        ]
    ];
}
