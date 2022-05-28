# dot-di
Dependency Injection and Service provider for PHP. Inspired by .NET DI.

Usage
--------

```php
require __DIR__ . '/../vendor/autoload.php';

use DotDi\DependencyInjection\ServiceProvider;
use DotDi\DependencyInjection\ServiceProviderHelper;

$services = new ServiceProvider();
// register a singleton
$services->addSingleton(FooService::class);
// register a transient
$services->addTransient(BarService::class);
// register scoped using interface as a type
$services->addScoped(ITaxService::class, TaxService::class);
$services->addScoped(GenericService::class);

// to auto register everything in the folder (as Scoped, for ex.: controllers)
ServiceProviderHelper::discover($services, ['path/to/your/files']);

```

## Using with generics

```php
class GenericService
{
    public function __construct(private string $type, private ?string $dbMap = null, ?int $dbIndex = null)
    {
    }
}
...
// injecting generic type using Inject attribute

class TestService
{
    /**
     * 
     * @param GenericService<UserEntity, UserEntityDbMap> $usersRepository 
     * @return void 
     */
    public function __construct(
        #[Inject([
            'type' => 'UserEntity',
            'dbMap' => 'UserEntityDbMap'
        ])]
        public GenericService $usersRepository
    ) {
    }
}
```

## Using scope

```php
// creating a scope
$scope = $services->createScope();
// getting a service
$scope->serviceProvider->get(FooService::class);
$scope->serviceProvider->get(ITaxService::class);
// setting up a service for current scope only
$requestScope->serviceProvider->set(HttpContext::class, new HttpContext());
// dispose at the end
$scope->dispose();
```

## Useful for swoole or ReactPHP

dot-di is super efficient and memory friendly - no memory leaks.

Use with IDisposable to auto release your resources:


```php
<?php

namespace Application\Swoole\Connectors;

use DotDi\Interfaces\IDisposable;
use Redis;
use Swoole\Database\RedisPool;

class SwooleRedisConnector implements IDisposable
{
    private Redis $redis;

    public function __construct(private RedisPool $pool)
    {
    }

    /**
     * 
     * @return Redis 
     */
    function get()
    {
        if (!isset($this->redis)) {
            $this->redis = $this->pool->get();
        }
        return $this->redis;
    }

    // this will be called automatically
    function dispose()
    {
        if (isset($this->redis)) {
            $this->pool->put($this->redis);
            unset($this->redis);
        }
    }
}
```

```php
// swoole app example
...
    function handle(HttpContext $httpContext)
    {
        // create scope
        $scope = $this->serviceProvider->createScope();
        try {
            // create request and http context
            $scope->serviceProvider->set(HttpRequest::class, $httpContext->request);
            $scope->serviceProvider->set(HttpResponse::class, $httpContext->response);
            $scope->serviceProvider->set(HttpContext::class, $httpContext);
            $requestDelegate = new RequestDelegate($this, $scope);
            $scope->serviceProvider->set(RequestDelegate::class, $requestDelegate);
            // run middleware(s)
            $requestDelegate();
            // end response
            $httpContext->response->end();
        } finally {
            // dispose the scope
            $scope->dispose();
        }
    }
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