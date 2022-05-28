<?php

namespace Tests;

require __DIR__ . '/../vendor/autoload.php';

use RedisDb\BaseRepository;
use RedisDb\Connectors\RedisConnector;
use RedisDb\Mapper\StdObjectMapper;
use RedisDb\Models\BaseEntityDbMap;
use Tests\Models\BlogPost;
use Tests\Models\BlogPostDbMap;

$mapper = new StdObjectMapper();
$connector = new RedisConnector();

$repository = new BaseRepository($connector, $mapper, BlogPost::class, BlogPostDbMap::class);

// cleanUp
function CleanUp(BaseRepository $repository)
{
    $allBlogs = $repository->GetList(1, BaseRepository::MAX_REDIS_INT);
    foreach ($allBlogs['list'] as $entity) {
        $repository->Delete($entity);
    }
    echo "Deleted: {$allBlogs['count']}" . PHP_EOL;
}

CleanUp($repository);

$totalToTest = 100;
for ($i = 0; $i < $totalToTest; $i++) {
    $blog = new BlogPost();
    $blog->Author = 'Miki the black cat';
    $blog->Body = 'body sample';
    $blog->DisplayOrder = $i;
    $blog->Group = $i % 20;
    $blog->Published = $i % 2 === 1;
    $blog->SeoTitle = "blog-number-$i"; // unique
    $blog->Title = "Blog $i";
    $repository->Create($blog);
}

foreach ([BaseEntityDbMap::ORDER_BY_CreatedOn => null, ...BlogPostDbMap::$Positions] as $orderBy => $_) {
    $orderedASC = $repository->GetList(1, 2, $orderBy, 1);
    $orderedDESC = $repository->GetList(1, 2, $orderBy, 0);

    echo "Ordered $orderBy ASC:" . PHP_EOL;
    // print_r($orderedASC);    
    var_dump($orderedASC['count'] === 100);
    var_dump(count($orderedASC['list']) === 2);
    foreach ($orderedASC['list'] as $blog) {
        /** @var BlogPost $blog */
        var_dump(in_array($blog->DisplayOrder, [0, 1]));
    }

    echo "Ordered $orderBy DESC:" . PHP_EOL;
    // print_r($orderedDESC);
    var_dump($orderedDESC['count'] === 100);
    var_dump(count($orderedDESC['list']) === 2);
    foreach ($orderedDESC['list'] as $blog) {
        /** @var BlogPost $blog */
        var_dump(in_array($blog->DisplayOrder, [98, 99]));
    }
}

foreach ([true, false] as $published) {
    $blogs = $repository->GetByKeys(
        [
            BlogPostDbMap::PROPERTY_Published => $published
        ],
        1,
        5
    );
    echo "Published $published:" . PHP_EOL;
    var_dump($blogs['count'] === 50);
    var_dump(count($blogs['list']) === 5);
    foreach ($blogs['list'] as $blog) {
        /** @var BlogPost $blog */
        var_dump($blog->Published === $published);
    }
}

foreach ([0, 1, 2, 3] as $group) {
    $published = $group % 2 === 1;
    $grouped = $repository->GetByKeys(
        [
            BlogPostDbMap::PROPERTY_Group => $group,
            BlogPostDbMap::PROPERTY_Published => $group % 2 === 1
        ],
        1,
        5,
        1,
        BlogPostDbMap::ORDER_BY_ORDER
    );
    echo "Published Group $group:" . PHP_EOL;
    var_dump($grouped['count'] === 5);
    var_dump(count($grouped['list']) === 5);
    foreach ($grouped['list'] as $blog) {
        /** @var BlogPost $blog */
        var_dump($blog->Published === $published);
        var_dump($blog->Group === $group);
    }
}

CleanUp($repository);
