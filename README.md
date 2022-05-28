# redis-repository
Redis repository implementation.

Usage Example
--------

```php
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
```

```php
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
```

```php
$mapper = new StdObjectMapper();
$connector = new RedisConnector();

$repository = new BaseRepository($connector, $mapper, BlogPost::class, BlogPostDbMap::class);

$blog = new BlogPost();
$blog->Author = 'Miki the black cat';
$blog->Body = 'body sample';
$blog->DisplayOrder = $i;
$blog->Group = $i % 20;
$blog->Published = $i % 2 === 1;
$blog->SeoTitle = "blog-number-$i"; // unique
$blog->Title = "Blog $i";
$repository->Create($blog);

$repository->Update($blog);

$repository->Delete($blog);

$repository->GetList(1, 2, BaseEntityDbMap::ORDER_BY_CreatedOn, 1);
$repository->GetList(1, 2, BlogPostDbMap::ORDER_BY_ORDER, 0);

$repository->GetByKeys(
    [
        BlogPostDbMap::PROPERTY_Group => $group,
        BlogPostDbMap::PROPERTY_Published => $group % 2 === 1
    ],
    1,
    5,
    1,
    BlogPostDbMap::ORDER_BY_ORDER
    );
```


License
--------

MIT License

Copyright (c) 2022-present Ivan Voitovych

Please see [LICENSE](/LICENSE) for license text


Legal
------

By submitting a Pull Request, you disallow any rights or claims to any changes submitted to the Viewi project and assign the copyright of those changes to Ivan Voitovych.

If you cannot or do not want to reassign those rights (your employment contract for your employer may not allow this), you should not submit a PR. Open an issue, and someone else can do the work.

This is a legal way of saying, "If you submit a PR to us, that code becomes ours." 99.9% of the time, that's what you intend anyways; we hope it doesn't scare you away from contributing.