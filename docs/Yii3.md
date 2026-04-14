Yii3

# Yii Database
## General Usage

To connect to a database, create an instance of the appropriate driver:

```php
use Yiisoft\Db\Sqlite\Connection;
use Yiisoft\Db\Sqlite\Driver;

/**
 * @var Psr\SimpleCache\CacheInterface $cache 
 */

// Creating a database connection
$db = new Connection(
    new Driver('sqlite:memory:'),
    new SchemaCache($cache),
);
```

You can then use the `$db` object to execute SQL queries, manage transactions, and perform other database operations. Here are some examples:

```php
use Yiisoft\Db\Connection\ConnectionInterface;

/**
 * @var ConnectionInterface $db
 */

// Query builder
$rows = $db 
    ->select(['id', 'email'])  
    ->from('{{%user}}')  
    ->where(['last_name' => 'Smith'])  
    ->limit(10)  
    ->all();

// Insert
$db->createCommand()
    ->insert(
        '{{%user}}',
         [
            'email' => 'mike@example.com',
            'first_name' => 'Mike',
            'last_name' => 'Smith',
         ],
    )
    ->execute();

// Transaction
$db->transaction(
    static function (ConnectionInterface $db) {
        $db->createCommand()
            ->update('{{%user}}', ['status' => 'active'], ['id' => 1])
            ->execute();
        $db->createCommand()
            ->update('{{%profile}}', ['visibility' => 'public'], ['user_id' => 1])
            ->execute();
    }
)
```
## Creating and applying migrations

For the initial state of the application and for further database changes, it is a good idea to use migrations. These are files that create database changes. Applied migrations are tracked in the database, allowing us to know the current state and which migrations remain to be applied.

To use migrations we need another package installed:

```bash
make composer require yiisoft/db-migration
```

Create a directory to store migrations `src/Migration` right in the project root. Add the following configuration to `config/common/params.php`:

```php
'yiisoft/db-migration' => [
    'newMigrationNamespace' => 'App\\Migration',
    'sourceNamespaces' => ['App\\Migration'],
],
```

Now you can use `make yii migrate:create page` to create a new migration. For our example we need a `page` table with some columns:

```php
<?php

declare(strict_types=1);

namespace App\Migration;

use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;

final class M251102141707Page implements RevertibleMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $column = $b->columnBuilder();

        $b->createTable('page', [
            'id' => $column::uuidPrimaryKey(),
            'title' => $column::string()->notNull(),
            'slug' => $column::string()->notNull()->unique(),
            'text' => $column::text()->notNull(),
            'created_at' => $column::dateTime(),
            'updated_at' => $column::dateTime(),
        ]);
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('page');
    }
}
```

The `M251102141707Page` name of the migration class is generated so replace the `Page` suffix with the actual migration name. The `M251102141707` prefix is needed to find and sort migrations in the order they were added.

Note that we use UUID as the primary key. We are going to generate these IDs ourselves instead of relying on database so we'll need an extra compose package for that.

```bash
make composer require ramsey/uuid
```

While the storage space is a bit bigger than using int, the workflow with such IDs is beneficial. Since you generate the ID yourself so you can define a set of related data and save it in a single transaction. The entities that define this set of data in the code are often called an "aggregate".

Apply the migration with `make yii migrate:up`.

## An entity

Now that you have a table it is time to define an entity in the code. Create `src/Web/Page/Page.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Page;

use DateTimeImmutable;
use Yiisoft\Strings\Inflector;

final readonly class Page
{
    private function __construct(
        public string $id,
        public string $title,
        public string $text,
        public string $slug,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }

    public static function create(
        string $id,
        string $title,
        string $text,
        string $slug = null,
        DateTimeImmutable $createdAt = new DateTimeImmutable(),
        DateTimeImmutable $updatedAt = new DateTimeImmutable(),
    ): self
    {
        return new self(
            id: $id,
            title: $title,
            slug: $slug ?? (new Inflector())->toSlug($title),
            text: $text,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }
}
```

## Repository

Now that we have entity, we need a place for methods to save an entity, delete it and select either a single page or multiple pages.

Create `src/Web/Page/PageRepository.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Page;

use DateTimeImmutable;
use Yiisoft\Db\Connection\ConnectionInterface;

final readonly class PageRepository
{
    public function __construct(
        private ConnectionInterface $connection,
    ) {}

    public function save(Page $page): void
    {
        $row = [
            'id' => $page->id,
            'title' => $page->title,
            'slug' => $page->slug,
            'text' => $page->text,
            'created_at' => $page->createdAt,
            'updated_at' => $page->updatedAt,
        ];

        if ($this->exists($page->id)) {
            $this->connection->createCommand()->update('{{%page}}', $row, ['id' => $page->id])->execute();
        } else {
            $this->connection->createCommand()->insert('{{%page}}', $row)->execute();
        }
    }

    public function findOneBySlug(string $slug): ?Page
    {
        $query = $this->connection
            ->select()
            ->from('{{%page}}')
            ->where('slug = :slug', ['slug' => $slug]);

        return $this->createPage($query->one());
    }

    /**
     * @return iterable<Page>
     */
    public function findAll(): iterable
    {
        $rows = $this->connection
            ->select()
            ->from('{{%page}}')
            ->all();

        foreach ($rows as $row) {
            yield $this->createPage($row);
        }
    }

    private function createPage(?array $row): ?Page
    {
        if ($row === null) {
            return null;
        }

        return Page::create(
            id: $row['id'],
            title: $row['title'],
            text: $row['text'],
            slug: $row['slug'],
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
        );
    }

    public function deleteBySlug(string $slug): void
    {
        $this->connection->createCommand()->delete(
            '{{%page}}',
            ['slug' => $slug],
        )->execute();
    }

    public function exists(string $id): bool
    {
        return $this->connection->createQuery()
            ->from('{{%page}}')
            ->where(['id' => $id])
            ->exists();
    }
}
```

In this repository there are both methods to get data and `save()` to do insert or update. DB returns raw data as arrays but our repository automatically creates entities from this raw data so later we operate typed data.
# Dependency injection and container

## Dependency injection

There are two ways of re-using things in OOP: inheritance and composition.

Inheritance is simple:

```php
class Cache
{
    public function getCachedValue($key)
    {
        // ..
    }
}

final readonly class CachedWidget extends Cache
{
    public function render(): string
    {
        $output = $this->getCachedValue('cachedWidget');
        if ($output !== null) {
            return $output;
        }
        // ...        
    }
}
```

The issue here is that these two are becoming unnecessarily coupled or inter-dependent, making them more fragile.

Another way to handle this is composition:

```php
interface CacheInterface
{
    public function getCachedValue($key);
}

final readonly class Cache implements CacheInterface
{
    public function getCachedValue($key)
    {
        // ..
    }
}

final readonly class CachedWidget
{
    public function __construct(
        private CacheInterface $cache
    )
    {
    }
    
    public function render(): string
    {
        $output = $this->cache->getCachedValue('cachedWidget');
        if ($output !== null) {
            return $output;
        }
        // ...        
    }
}
```

We've avoided unnecessary inheritance and used `CacheInterface` in the `CacheWidget` to reduce coupling. You can replace cache implementation without changing `CachedWidget` so it's becoming more stable. The less edits are made to the code, the less chance of breaking it.

The `CacheInterface` here is a dependency: a contract our object needs to function. In other words, our object depends on the contract.

The process of putting an instance of a contract into an object (`CachedWidget`) is called dependency injection. There are many ways to perform it:

- Constructor injection. Best for mandatory dependencies.
- Method injection. Best for optional dependencies.
- Property injection. Better to be avoided in PHP except maybe data transfer objects.

### Why use private properties

In the composition example above, note that the `$cache` property is declared as `private`.

This approach embraces composition by ensuring objects have well-defined interfaces for interaction rather than direct property access, making the code more maintainable and less prone to certain types of mistakes.

This design choice provides several benefits:

- **Encapsulation**: Private properties with getters/setters allow you to control access and make future changes without breaking existing code.
- **Data integrity**: Setters can validate, normalize, or format values before storing them, ensuring properties contain valid data.
- **Immutability**: Private properties enable immutable object patterns where setter `with*()` methods return new instances rather than modifying the current one.
- **Flexibility**: You can create read-only or write-only properties or add additional logic to property access later.

## DI container

Injecting basic dependencies is straightforward. You're choosing a place where you don't care about dependencies, which is usually an action handler, which you aren't going to unit-test ever, create instances of dependencies needed and pass these to dependent classes.

It works well when there are few dependencies overall and when there are no nested dependencies. When there are many and each dependency has dependencies itself, instantiating the whole hierarchy becomes a tedious process, which requires lots of code and may lead to hardly debuggable mistakes.

Additionally, lots of dependencies, such as certain third-party API wrappers, are the same for any class using it. So it makes sense to:

- Define how to instantiate such common dependencies.
- Instantiate them when required and only once per request.

That's what dependency containers are for.

A dependency injection (DI) container is an object that knows how to instantiate and configure objects and all objects they depend on.

Yii provides the DI container feature through the [yiisoft/di](https://github.com/yiisoft/di) package and [yiisoft/injector](https://github.com/yiisoft/injector) package.
### Configuring container

Because to create a new object you need its dependencies, you should register them as early as possible. You can do it in the application configuration, `config/web.php`. For the following service:

```php
final class MyService implements MyServiceInterface
{
    public function __construct(int $amount)
    {
    }

    public function setDiscount(int $discount): void
    {
    
    }
}
```

configuration could be:

```php
return [
    MyServiceInterface::class => [
        'class' => MyService::class,
        '__construct()' => [42],
        'setDiscount()' => [10],
    ],
];
```

That's equal to the following:

```php
$myService = new MyService(42);
$myService->setDiscount(10);
```

You can provide arguments with names as well:

```php
return [
    MyServiceInterface::class => [
        'class' => MyService::class,
        '__construct()' => ['amount' => 42],
        'setDiscount()' => ['discount' => 10],
    ],
];
```

That's basically it. You define a map of interfaces to classes and define how to configure them. When an interface is requested in constructor or elsewhere, container creates an instance of a class and configures it as per the configuration:

```php
final class MyAction
{
    public function __construct(
        private readonly MyServiceInterface $myService
    ) {
    }
    
    public function __invoke() 
    {
        $this->myService->doSomething();
    }
}
```

There are extra methods of declaring dependency configuration.

For simplest cases where there are no custom values needed and all the constructor dependencies could be obtained from a container, you can use a class name as a value.

```php
interface EngineInterface
{
    
}

final class EngineMarkOne implements EngineInterface
{
    public function __construct(CacheInterface $cache) {
    }   
}
```

In the above example, if we already have cache defined in the container, nothing besides the class name is needed:

```php
return [
    // declare a class for an interface, resolve dependencies automatically
    EngineInterface::class => EngineMarkOne::class,
];
```

If you have a dependency that has public properties, you can configure it as well.

```php
final class NameProvider
{
    public string $name;
}
```

Here's how to do it for the example above:

```php
NameProvider::class => [
    'class' => NameProvider::class, 
    '$name' => 'Alex',
],
```

In this example, you may notice `NameProvider` specified twice. The key is what you may request as dependency and the value is how to create it.

If the configuration is tricky and requires some logic, a closure can be used:

```php
MyServiceInterface::class => static function(ContainerInterface $container) {
    return new MyService($container->get('db'));
},
```

Additionally, to `ContainerInterface`, you can request any registered service directly as a closure parameter. The injector will automatically resolve and inject these:

```php
MyServiceInterface::class => static function(ConnectionInterface $db) {
    return new MyService($db);
},
```

It's possible to use a static method call:

```php
MyServiceInterface::class => [MyFactory::class, 'create'],
```

Or an instance of an object:

```php
MyServiceInterface::class => new MyService(),
```

### Injecting dependencies properly

Directly referencing a container in a class is a bad idea since the code becomes non-generic, coupled to the container interface and, what's worse, dependencies are becoming hidden. Because of that, Yii inverts the control by automatically injecting objects from a container in some constructors and methods based on method argument types.

This is primarily done in constructor and handing method of action handlers:

```php
use \Yiisoft\Cache\CacheInterface;

final readonly class MyController
{
    public function __construct(
        private CacheInterface $cache
    )
    {
        $this->cache = $cache;    
    }

    public function actionDashboard(RevenueReport $report)
    {
        $reportData = $this->cache->getOrSet('revenue_report', function() use ($report) {
            return $report->getData();               
        });

        return $this->render('dashboard', [
           'reportData' => $reportData,
        ]);
    }
}
```

Since it's [yiisoft/injector](https://github.com/yiisoft/injector) that instantiates and calls action handler, it checks the constructor and method argument types, gets dependencies of these types from a container and passes them as arguments. That's usually called auto-wiring. It happens for sub-dependencies as well, that's if you don't give dependency explicitly, the container would check if it has such a dependency first. It's enough to declare a dependency you need, and it would be got from a container automatically.
# Events

Events allow you to make custom code executed at certain execution points without modifying existing code. You can attach a custom code called "handler" to an event so that when the event is triggered, the handler gets executed automatically.

For example, when a user is signed up, you need to send a welcome email. You can do it right in the `SignupService` but then, when you additionally need to resize user's avatar image, you'll have to change `SignupService` code again. In other words, `SignupService` will be coupled to both code sending welcome email and code resizing avatar image.

To avoid it, instead of telling what do after signup explicitly you can, instead, raise `UserSignedUp` event and then finish a signup process. The code sending an email and the code resizing avatar image will attach to the event and, therefore, will be executed. If you'll ever need to do more on signup, you'll be able to attach extra event handlers without modifying `SignupService`.

For raising events and attaching handlers to these events, Yii has a special service called event dispatcher. It's available from [yiisoft/event-dispatcher package](https://github.com/yiisoft/event-dispatcher).

## Event Handlers

An event handler is [PHP callable](https://www.php.net/manual/en/language.types.callable.php) that gets executed when the event it's attached to is triggered.

The signature of an event handler is:

```php
function (EventClass $event) {
    // handle it
}
```

## Attaching event handlers

You can attach a handler to an event like the following:

```php
use Yiisoft\EventDispatcher\Provider\Provider;

final readonly class WelcomeEmailSender
{
    public function __construct(Provider $provider)
    {
        $provider->attach([$this, 'handleUserSignup']);
    }

    public function handleUserSignup(UserSignedUp $event)
    {
        // handle it    
    }
}
```

The `attach()` method is accepting a callback. Based on the type of this callback argument, the event type is determined.

## Event handlers order

You may attach one or more handlers to a single event. When an event is triggered, the attached handlers will be called in the order that they were attached to the event. In case an event implements `Psr\EventDispatcher\StoppableEventInterface`, event handler can stop executing the rest of the handlers that follow it if `isPropagationStopped()` returns `true`.

In general, it's better not to rely on the order of event handlers.

## Raising events

Events are raised like the following:

```php
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class SignupService
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher
    )
    {
    }

    public function signup(SignupForm $form)
    {
        // handle signup

        $event = new UserSignedUp($form);
        $this->eventDispatcher->dispatch($event);
    }
}
```

First, you create an event supplying it with data that may be useful for handlers. Then you dispatch the event.

The event class itself may look like the following:

```php
final readonly class UserSignedUp
{
    public function __construct(
        public SignupForm $form
    )
    {
    }
}
```

## Events hierarchy

Events don't have any name or wildcard matching on purpose. Event class names and class/interface hierarchy and composition could be used to achieve great flexibility:

```php
interface DocumentEvent
{
}

final readonly class BeforeDocumentProcessed implements DocumentEvent
{
}

final readonly class AfterDocumentProcessed implements DocumentEvent
{
}
```

With the interface, you can listen to all document-related events:

```php
$provider->attach(function (DocumentEvent $event) {
    // log events here
});
```

## Detaching event handlers

To detach a handler from an event you can call `detach()` method:

```php
$provider->detach(DocmentEvent::class);
```
# Immutability

Immutability means an object's state cannot change after it has been created. Instead of modifying an instance, you create a new instance with the desired changes. This approach is common for value objects such as Money, IDs, and DTOs. It helps to avoid accidental side effects: methods cannot silently change shared state, which makes code easier to reason about.

## Mutable pitfalls (what we avoid)

```php
// A shared base query built once and reused:
$base = Post::find()->where(['status' => Post::STATUS_PUBLISHED]);

// Somewhere deep in the code we only need one post:
$one = $base->limit(1)->one(); // mutates the underlying builder (sticky limit!)

// Later we reuse the same $base expecting a full list:
$list = $base->orderBy(['created_at' => SORT_DESC])->all();
// Oops: still limited to 1 because the previous limit(1) modified $base.
```

## Creating an immutable object in PHP

There is no direct way to modify an instance, but you can use clone to create a new instance with the desired changes. That is what `with*` methods do.

```php
final class Money
{
    public function __construct(
        private int $amount,
        private string $currency,
    ) {
        $this->validateAmount($amount);
        $this->validateCurrency($currency);
    }
    
    private function validateAmount(string $amount) 
    {
     if ($amount < 0) {
            throw new InvalidArgumentException('Amount must be positive.');
        }
    }
    
    private function validateCurrency(string $currency)
    {
        if (!in_array($currency, ['USD', 'EUR'])) {
            throw new InvalidArgumentException('Invalid currency. Only USD and EUR are supported.');
        }
    } 

    public function withAmount(int $amount): self
    {
        $this->validateAmount($amount);
    
        if ($amount === $this->amount) {
            return $this;
        }
    
        $clone = clone $this;
        $clone->amount = $amount;
        return $clone;
    }
    
    public function withCurrency(string $currency): self
    {
        $this->validateCurrency($currency);
    
        if ($currency === $this->currency) {
            return $this;
        }
    
        $clone = clone $this;
        $clone->currency = $currency;
        return $clone;
    }
    
    public function amount(): int 
    {
        return $this->amount;
    }
    
    public function currency(): string 
    {
        return $this->currency;
    }

    public function add(self $money): self
    {
        if ($money->currency !== $this->currency) {
            throw new InvalidArgumentException('Currency mismatch. Cannot add money of different currency.');
        }
        return $this->withAmount($this->amount + $money->amount);
    }
}

$price = new Money(1000, 'USD');
$discounted = $price->withAmount(800);
// $price is still 1000 USD, $discounted is 800 USD
```

- We mark the class `final` to prevent subclass mutations; alternatively, design for extension carefully.
- Validate in the constructor and `with*` methods so every instance is always valid.
## Using clone (and why it is inexpensive)

PHP's clone performs a shallow copy of the object. For immutable value objects that contain only scalars or other immutable objects, shallow cloning is enough and fast. In modern PHP, cloning small value objects is inexpensive in both time and memory.

If your object holds mutable sub-objects that must also be copied, implement `__clone` to deep-clone them:

```php
final class Order
{
    public function __construct(
        private Money $total
    ) {}
    
    public function total(): Money 
    {
        return $this->total;
    }

    public function __clone(): void
    {
        // Money is immutable in our example, so a deep clone is not required.
        // If it were mutable, you could do: $this->total = clone $this->total;
    }

    public function withTotal(Money $total): self
    {
        $clone = clone $this;
        $clone->total = $total;
        return $clone;
    }
}
```

## Usage style

- Build a value object once and pass it around. If you need a change, use a `with*` method that returns a new instance.
- Prefer scalar/immutable fields inside immutable objects; if a field can mutate, isolate it and deep-clone in `__clone` when needed.

Immutability aligns well with Yii's preference for predictable, side-effect-free code and makes services, caching, and configuration more robust.
# Actions

In a web application, the request URL determines what's executed. Matching is made by a router configured with multiple routes. Each route can be attached to a middleware that, given request, produces a response. Since middleware overall could be chained and can pass actual handling to the next middleware, we call the middleware actually doing the job an action.

There are multiple ways to describe an action. The simplest one is using a closure:

```php
use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use Yiisoft\Router\Route;

Route::get('/')->action(function (ServerRequestInterface $request) use ($responseFactory): ResponseInterface {
    $response = $responseFactory->createResponse();
    $response->getBody()->write('You are at homepage.');
    return $response;
});
```

It's fine for simple handling since any more complicated one would require getting dependencies, so a good idea would be moving the handling to a class method. Callback middleware could be used for the purpose:

```php
use Yiisoft\Router\Route;

Route::get('/')->action(FrontPageAction::class),
```

The class itself would be like:

```php
use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;

final readonly class FrontPageAction
{
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        // build response for a front page    
    }
}
```

For many cases, it makes sense to group handling for many routes into a single class:

```php
use Yiisoft\Router\Route;

Route::get('/post/index')->action([PostController::class, 'actionIndex']),
Route::get('/post/view/{id:\d+}')->action([PostController::class, 'actionView']),
```

The class itself would look like the following:

```php
use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;

final readonly class PostController
{
    public function actionIndex(ServerRequestInterface $request): ResponseInterface
    {
        // render posts list
    }
    
    
    public function actionView(ServerRequestInterface $request): ResponseInterface
    {
        // render a single post      
    }
}
```

We usually call such a class "controller."

## Autowiring

Both constructors of action-classes and action-methods are automatically getting services from the dependency injection container:

```php
use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

final readonly class PostController
{
    public function __construct(
        private PostRepository $postRepository
    )
    {
    }

    public function actionIndex(ServerRequestInterface $request, LoggerInterface $logger): ResponseInterface
    {
        $logger->debug('Rendering posts list');
        // render posts list
    }
    
    
    public function actionView(ServerRequestInterface $request): ResponseInterface
    {
        // render a single post      
    }
}
```

In the above example `PostRepository` is injected automatically via constructor. That means it is available in every action. Logger is injected into `index` action only.
# Middleware

Yii works with HTTP using the abstraction layer built around [PSR-7 HTTP message interfaces](https://www.php-fig.org/psr/psr-7/) and [PSR-15 request handler/middleware interfaces](https://www.php-fig.org/psr/psr-15/).

The application is composed of one or several middleware. Middleware runs between request and response. When the URL is requested, the request object is passed to the middleware dispatcher that starts executing middleware one after another. Each middleware, given the request, can:

- Pass the request to the next middleware performing some work before / after it.
- Form the response and return it.

Depending on middleware used, application behavior may vary significantly.
## Using middleware

Any [PSR-15](https://www.php-fig.org/psr/psr-15/) compatible middleware could be used with Yii, and there are many. Say, you need to add basic authentication to one of the application URLs. URL-dependent middleware is configured using router, so you need to change the router factory.

Authentication middleware is implemented by `middlewares/http-authentication` package so execute `composer require middlewares/http-authentication` in the application root directory.

Now register the middleware in DI container configuration `config/web.php`:

```php
<?php
\Middlewares\BasicAuthentication::class => [
    'class' => \Middlewares\BasicAuthentication::class,
    '__construct()' => [
        'users' => [
            'foo' => 'bar',
        ],
    ],
    'realm()' => ['Access to the staging site via basic auth'],
    'attribute()' => ['username'],
],
```

In the `config/routes.php`, add new route:

```php
<?php

declare(strict_types=1);

use Yiisoft\Router\Route;
use App\Controller\SiteController;
use Middlewares\BasicAuthentication;


return [
    //...
    Route::get('/basic-auth')
        ->action([SiteController::class, 'auth'])
        ->name('site/auth')
        ->prependMiddleware(BasicAuthentication::class)
];
```

When configuring routing, you're binding `/basic-auth` URL to a chain of middleware consisting of basic authentication, and the action itself. A chain is a special middleware that executes all the middleware it's configured with.

The action itself could be the following:

```php
public function auth(ServerRequestInterface $request): ResponseInterface
{
    $response = $this->responseFactory->createResponse();
    $response->getBody()->write('Hi ' . $request->getAttribute('username'));
    return $response;
}
```

Basic authentication middleware wrote to request `username` attribute, so you can access the data if needed.

To apply middleware to application overall regardless of URL, adjust `config/application.php`:

```php
return [
    Yiisoft\Yii\Http\Application::class => [
        '__construct()' => [
            'dispatcher' => DynamicReference::to(static function (Injector $injector) {
                return ($injector->make(MiddlewareDispatcher::class))
                    ->withMiddlewares(
                        [
                            ErrorCatcher::class,
                            BasicAuthentication::class,
                            SessionMiddleware::class,
                            CsrfMiddleware::class,
                            Router::class,
                        ]
                    );
            }),
            'fallbackHandler' => Reference::to(NotFoundHandler::class),
        ],
    ],
];
```

## Creating your own middleware

To create middleware, you need to implement a single `process` method of `Psr\Http\Server\MiddlewareInterface`:

```php
public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface;
```

There are multiple ways to handle a request, and choosing one depends on what the middleware should achieve.

### Forming a response directly

To respond directly, one needs a response factory passed via constructor:

```php
<?php
declare(strict_types=1);

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class RespondingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory
    )
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $this->responseFactory->createResponse();
        $response->getBody()->write('Hello!');
        return $response;
    }
}
```

### Delegating handling to the next middleware

If middleware either isn't intended to form a response or change the request or can't do anything this time, handling could be left to the next middleware in the stack:

```php
public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
{
    return $handler->handle($request);    
}
```

In case you need to pass data to the next middleware, you can use request attributes:

```php
$request = $request->withAttribute('answer', 42);
return $handler->handle($request);
```

To get it in the next middleware:

```php
$answer = $request->getAttribute('answer');
```

### Capturing response to manipulate it

You may want to capture the response to manipulate it. It could be useful for adding CORS headers, gzipping content, etc.

```php
$response = $handler->handle($request);
// extra handing
return $response;
```
# Routing and URL generation

Usually, a Yii application processes certain requests with certain handlers. It selects a handler based on the request URL. The part of the application that does the job is a router, and the process of selecting a handler, instantiating it and running a handler method is _routing_.

The reverse process of routing is _URL generation_, which creates a URL from a given named route and the associated query parameters. When you later request the created URL, the routing process can resolve it back into the original route and query parameters.

Routing and URL generation are separate services, but they use a common set of routes for both URL matching and URL generation.

## Configuring routes

By configuring routes, you can let your application recognize arbitrary URL formats without modifying your existing application code. You can configure routes in `/config/routes.php`. The structure of the file is the following:

```php
<?php

declare(strict_types=1);

use App\Controller\SiteController;
use Yiisoft\Router\Route;

return [
    Route::get('/')
        ->action([SiteController::class, 'index'])
        ->name('site/index')
];
```

The file returns an array of routes. When defining a route, you start with a method corresponding to a certain HTTP request type:

- get
- post
- put
- delete
- patch
- head
- options

If you need many methods, you can use `methods()`:

```php
<?php

declare(strict_types=1);

use App\Controller\SiteController;
use Yiisoft\Http\Method;
use Yiisoft\Router\Route;

return [
    Route::methods([Method::GET, Method::POST], '/user/{id}')
        ->action([SiteController::class, 'user'])
        ->name('site/user')
];
```

All these methods accept a route pattern and a handler. The route pattern defines how the router matches the URL when routing and how it generates URL based on route name and parameters. You will learn about the actual syntax later in this guide. You could specify a handler as:

- [Middleware](https://yiisoft.github.io/docs/guide/structure/middleware.html) class name.
- Handler action (an array of [HandlerClass, handlerMethod]).
- A callable.

In case of a handler action, a class of type `HandlerClass` is instantiated and its `handlerMethod` is called:

```php
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

final readonly class HandlerClass
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // ...
    }
}
```

The callable is called as is:

```php
static function (ServerRequestInterface $request, RequestHandlerInterface $next) use ($responseFactory) {
    $response = $responseFactory->createResponse();
    $response->getBody()->write('You are at homepage.');
    return $response;
}
```

For handler action and callable typed parameters are automatically injected using the dependency injection container passed to the route.

Get current request and handler by type-hinting for `ServerRequestInterface` and `RequestHandlerInterface`. You could add extra handlers to wrap primary one with `middleware()` method:

```php
<?php

declare(strict_types=1);

use Yiisoft\Http\Method;
use Yiisoft\Router\Route;

return [
    Route::methods([Method::GET], '/download/{id}')
        ->action([DownloadController::class, 'download'])
        ->name('download/id')
        ->middleware(LimitDownloadRate::class)
];
```

Check ["the middleware"](https://yiisoft.github.io/docs/guide/structure/middleware.html) guide to learn more about how to implement middleware.

This is especially useful when grouping routes:

```php
<?php

declare(strict_types=1);

use Yiisoft\Router\Group;
use Yiisoft\Router\Route;

return [
    Group::create('/api')
        ->middleware(ApiDataWrapper::class)
        ->routes(
            Route::get('/info/v2')
                ->action(ApiInfo::class)
                ->name('api/info/v2')
                ->middleware(JsonDataResponseMiddleware::class),
            Route::get('/user')
                ->action([ApiUserController::class, 'index'])
                ->name('api/user/index'),
            Route::get('/user/{login}')
                ->action([ApiUserController::class, 'profile'])
                ->middleware(JsonDataResponseMiddleware::class)
                ->name('api/user/profile'),
        )
];
```

Router executes `ApiDataWrapper` before handling any URL starting with `/api`.

You could name a route with a `name()` method. It's a good idea to choose a route name based on the handler's name.

You can set a default value for a route parameter. For example:

```php
<?php

declare(strict_types=1);

use App\Controller\SiteController;
use Yiisoft\Http\Method;
use Yiisoft\Router\Route;

return [
    Route::methods([Method::GET, Method::POST], '/user[/{id}]')
        ->action([SiteController::class, 'user'])
        ->name('site/user')
        ->defaults(['id' => '42'])
];
```

This configuration would result in a match with both `/user` and `/user/123`. In both cases `CurrentRoute` service will contain `id` argument filled. In the first case it will be default `42` and in the second case it will be `123`.

In cause URL should be valid for a single host, you can specify it with `host()`.

## Routing

Yii routing is flexible, and internally it may use different routing implementations. The actual matching algorithm may vary, but the basic idea stays the same.

Router matches routes defined in config from top to bottom. If there is a match, further matching isn't performed and the router executes the route handler to get the response. If there is no match at all, router passes handling to the next middleware in the [application middleware set](https://yiisoft.github.io/docs/guide/structure/middleware.html).

## Generating URLs

To generate URL based on a route, a route should have a name:

```php
<?php

declare(strict_types=1);

use App\Controller\TestController;
use Yiisoft\Router\Route;

return [
    Route::get('/test', [TestController::class, 'index'])
        ->name('test/index'),
    Route::post('/test/submit/{id}', [TestController::class, 'submit'])
        ->name('test/submit')
];
```

The generation looks like the following:

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface;
use Yiisoft\Router\UrlGeneratorInterface;

final readonly class TestController extends AbstractController
{
    protected function name(): string
    {
        return 'test';
    }

    public function index(UrlGeneratorInterface $urlGenerator): ResponseInterface
    {
        $url = $urlGenerator->generate('test/submit', ['id' => '42']);
        // ...
    }
}
```

In the above code, we get a generator instance with the help of [automatic dependency injection](https://yiisoft.github.io/docs/guide/concept/di-container.html) that works with action handlers. In another service, you can get the instance with similar constructor injection. In views URL generator is available as `$url`.

Then we use `generate()` method to get actual URL. It accepts a route name and an array of named query parameters. The code will return "/test/submit/42." If you need absolute URL, use `generateAbsolute()` instead.

## Route patterns

Route patterns used depend on the underlying implementation used. The default implementation is [nikic/FastRoute](https://github.com/nikic/FastRoute).

Basic patterns are static like `/test`. That means they must match exactly in order for a route match.

### Named Parameters

A pattern can include one or more named parameters which are specified in the pattern in the format of `{ParamName:RegExp}`, where `ParamName` specifies the parameter name and `RegExp` is an optional regular expression used to match parameter values. If `RegExp` isn't specified, it means the parameter value should be a string without any slash.

NOTE

You can only use regular expressions inside parameters. The rest of the pattern is considered plain text.

You can't use capturing groups. For example `{lang:(en|de)}` isn't a valid placeholder, because `()` is a capturing group. Instead, you can use either `{lang:en|de}` or `{lang:(?:en|de)}`.

On a route match router fills the associated request attributes with values matching the corresponding parts of the URL. When you use the rule to create a URL, it will take the values of the provided parameters and insert them at the places where the parameters are declared.

Let's use some examples to illustrate how named parameters work. Assume you've declared the following three patterns:

1. `'posts/{year:\d{4}}/{category}`
2. `'posts'`
3. `'post/{id:\d+}'`

- `/posts` match the second pattern;
- `/posts/2014/php` match a first pattern. Parameters are the `year` whose value is 2014 and the `category` whose value is `php`;
- `/post/100` match a third pattern. The `id` parameter value is 100;
- `/posts/php` doesn't match.

When generating URLs, you should use the following parameters:

```php
echo $url->generate('first', ['year' => '2020', 'category' => 'Virology']);
echo $url->generate('second');
echo $url->generate('third', ['id' => '42']);
```

### Optional parts

You should wrap optional pattern parts with `[` and `]`. For example, `/posts[/{id}]` pattern would match both `http://example.com/posts` and `http://example.com/posts/42`. Router would fill `id` argument of `CurrentRoute` service in the second case only. In this case, you could specify the default value:

```php
use \Yiisoft\Router\Route;

Route::get('/posts[/{id}]')->defaults(['id' => '1']);
```

Optional parts are only supported in a trailing position, not in the middle of a route.
# Request

HTTP request has a method, URI, a set of headers and a body:

```
POST /contact HTTP/1.1
Host: example.org
Accept-Language: en-us
Accept-Encoding: gzip, deflate

{
    "subject": "Hello",
    "body": "Hello there, we need to build Yii application together!"
}
```

The method is `POST`, URI is `/contact`. Extra headers are specifying host, preferred language and encoding. The body could be anything. In this case, it's a JSON payload.

Yii uses [PSR-7 `ServerRequest`](https://www.php-fig.org/psr/psr-7/) as request representation. The object is available in controller actions and other types of middleware:

```php
public function view(ServerRequestInterface $request): ResponseInterface
{
    // ...
}
```

## Method

The method could be obtained from a request object:

```php
$method = $request->getMethod();
```

Usually it's one of the:

- GET
- POST
- PUT
- DELETE
- HEAD
- PATCH
- OPTIONS

In case you want to make sure the request method is of a certain type, there is a special class with method names:

```php
use Yiisoft\Http\Method;

if ($request->getMethod() === Method::POST) {
    // method is POST
}
```

## URI

A URI has:

- Scheme (`http`, `https`)
- Host (`yiiframework.com`)
- Port (`80`, `443`)
- Path (`/posts/1`)
- Query string (`page=1&sort=+id`)
- Fragment (`#anchor`)

You can obtain `UriInterface` from request like the following:

```php
$uri = $request->getUri();
```

Then you can get various details from its methods:

- `getScheme()`
- `getAuthority()`
- `getUserInfo()`
- `getHost()`
- `getPort()`
- `getPath()`
- `getQuery()`
- `getFragment()`

## Headers

There are various methods to inspect request headers. To get all headers as an array:

```php
$headers = $request->getHeaders();
foreach ($headers as $name => $values) {
   // ...
}
```

To get a single header:

```php
$values = $request->getHeader('Accept-Encoding');
```

Also, you could get value as a comma-separated string instead of an array. That's especially handy if a header has a single value:

```php
if ($request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') {
    // This is an AJAX request made with jQuery.
    // Note that header presence and name may vary depending on the library used. 
}
```

To check if a header is present in the request:

```php
if ($request->hasHeader('Accept-Encoding')) {
    // ...
}
```

## Body

There are two methods to get body contents. The first is getting the body as it is without parsing:

```php
$body = $request->getBody();
```

The `$body` would be an instance of `Psr\Http\Message\StreamInterface`.

Also, you could get a parsed body:

```php
$bodyParameters = $request->getParsedBody();
```

Parsing depends on PSR-7 implementation and may require middleware for custom body formats.

```php
<?php
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

final readonly class JsonBodyParserMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandler $next): Response
    {
        $contentType = $request->getHeaderLine('Content-Type');

        if (strpos($contentType, 'application/json') !== false) {
            $body = $request->getBody();
            $parsedBody = $this->parse($body);
            $request = $request->withParsedBody($parsedBody);
            
        }

        return $next->handle($request);
    }
}
```

## File uploads

Uploaded files that user submitted from a form with `enctype` attribute equals to `multipart/form-data` are handled via special request method:

```php
$files = $request->getUploadedFiles();
foreach ($files as $file) {
    if ($file->getError() === UPLOAD_ERR_OK) {
        $file->moveTo('path/to/uploads/new_filename.ext');
    }
}
```

## Attributes

Application middleware may set custom request attributes using `withAttribute()` method. You can get these attributes with `getAttribute()`.
# Response

HTTP response has status code and message, a set of headers and a body:

```
HTTP/1.1 200 OK
Date: Mon, 27 Jul 2009 12:28:53 GMT
Server: Apache/2.2.14 (Win32)
Last-Modified: Wed, 22 Jul 2009 19:15:56 GMT
Content-Length: 6 
Content-Type: text/html
Connection: Closed

Hello!
```

Yii uses [PSR-7 `Response`](https://www.php-fig.org/psr/psr-7/) in the web application to represent response.

The object should be constructed and returned as a result of the execution of controller actions or other middleware. Usually, the middleware has a response factory injected into its constructor.

```php
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class PostAction
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory
    )
    {
    }

    public function view(ServerRequestInterface $request): ResponseInterface
    {
        $response = $this->responseFactory->createResponse();
        $response->getBody()->write('Hello!');
        return $response;
    }
}
```

## Status code

You can set a status code like the following:

```php
use Yiisoft\Http\Status;

$response = $response->withStatus(Status::NOT_FOUND);
```

Majority of status codes are available from `Status` class for convenience and readability.

## Headers

You can set headers like this:

```php
$response = $response->withHeader('Content-type', 'application/json');
```

If there is a need to append a header value to the existing header:

```php
$response = $response->withAddedHeader('Set-Cookie', 'qwerty=219ffwef9w0f; Domain=somecompany.co.uk; Path=/; Expires=Wed, 30 Aug 2019 00:00:00 GMT');
```

And, if needed, headers could be removed:

```php
$response = $response->withoutHeader('Set-Cookie');
```

## Body

Response body is an object implementing `Psr\Http\Message\StreamInterface`.

You can write to it via the interface itself:

```php
$body = $response->getBody();
$body->write('Hello');
```

## Examples

### Redirecting

```php
use Yiisoft\Http\Status;

return $response
  ->withStatus(Status::PERMANENT_REDIRECT)
  ->withHeader('Location', 'https://www.example.com');
```

Note that there are different statuses used for redirection:

|Code|Usage|What is it for|
|---|---|---|
|301|`Status::MOVED_PERMANENTLY`|Permanently changed a URL structure. Search engines update their indexes, and browsers cache it.|
|308|`Status::PERMANENT_REDIRECT`|Like 301, but guarantees the HTTP method won't change.|
|302|`Status::FOUND`|Temporary changes like maintenance pages. Original URL should still be used for future requests. Search engines typically don't update their indexes.|
|307|`Status::TEMPORARY_REDIRECT`|Like 302, but guarantees the HTTP method won't change.|
|303|`Status::SEE_OTHER`|After form submissions to prevent duplicate submissions if the user refreshes. Explicitly tells to use `GET` for the redirect, even if the original request was `POST`.|

### Responding with JSON

```php
use Yiisoft\Http\Status;
use Yiisoft\Json\Json;

$data = [
    'account' => 'samdark',
    'value' => 42
];

$response->getBody()->write(Json::encode($data));
return $response
    ->withStatus(Status::OK)
    ->withHeader('Content-Type', 'application/json')
```
# Sessions

Sessions persist data between requests without passing them to the client and back. Yii has [a session package](https://github.com/yiisoft/session) to work with session data.

To add it to your application, use composer:

```bash
composer require yiisoft/session
```

## Configuring middleware

To keep a session between requests, you need to add `SessionMiddleware` to your route group or application middlewares. You should prefer a route group when you have both API with token-based authentication and regular web routes in the same application. Having it this way avoids starting the session for API endpoints.

To add a session for a certain group of routes, edit `config/routes.php` like the following:

```php
<?php

declare(strict_types=1);

use Yiisoft\Router\Group;
use Yiisoft\Session\SessionMiddleware;

return [
    Group::create('/blog')
        ->middleware(SessionMiddleware::class)
        ->routes(
            // ...
        )
];
```

To add a session to the whole application, edit `config/application.php` like the following:

```php
return [
    Yiisoft\Yii\Web\Application::class => [
        '__construct()' => [
            'dispatcher' => DynamicReference::to(static function (Injector $injector) {
                return ($injector->make(MiddlewareDispatcher::class))
                    ->withMiddlewares(
                        [
                            Router::class,
                            CsrfMiddleware::class,
                            SessionMiddleware::class, // <-- add this
                            ErrorCatcher::class,
                        ]
                    );
            }),
        ],
    ],
];
```

## Opening and closing session

```php
public function actionProfile(\Yiisoft\Session\SessionInterface $session)
{
    // start a session if it's not yet started
    $session->open();

    // work with session

    // write session values and then close it
    $session->close();
}
```

NOTE

Closing session as early as possible is a good practice since many session implementations are blocking other requests while the session is open.

There are two more ways to close a session:

```php
public function actionProfile(\Yiisoft\Session\SessionInterface $session)
{
    // discard changes and close the session
    $session->discard();

    // destroy the session completely
    $session->destroy();    
}
```

## Working with session data

Usually you will use the following methods to work with session data:

```php
public function actionProfile(\Yiisoft\Session\SessionInterface $session)
{
    // get a value
    $lastAccessTime = $session->get('lastAccessTime');

    // get all values
    $sessionData = $session->all();
        
    // set a value
    $session->set('lastAccessTime', time());

    // check if the value exists
    if ($session->has('lastAccessTime')) {
        // ...    
    }
    
    // remove value
    $session->remove('lastAccessTime');

    // get value and then remove it
    $sessionData = $session->pull('lastAccessTime');

    // clear session data from runtime
    $session->clear();
}
```

## Flash messages

In case you need some data to remain in session until read, such as in case of displaying a message on the next page, "flash" messages are what you need. A flash message is a special type of data that's available only in the current request and the next request. After that, it will be deleted automatically.

`FlashInteface` usage is the following:

```php
/** @var Yiisoft\Session\Flash\FlashInterface $flash */

// request 1
$flash->set('warning', 'Oh no, not again.');

// request 2
$warning = $flash->get('warning');
if ($warning !== null) {
    // do something with it
}
```

## Custom session storage

When using `Yiisoft\Session\Session`, you can use your own storage implementation:

```php
$handler = new MySessionHandler();
$session = new \Yiisoft\Session\Session([], $handler);
```

Custom storage must implement `\SessionHandlerInterface`.
# Cookies

Cookies are for persisting data between requests by sending it to the client browser using HTTP headers. The client sends data back to the server in request headers. Thus, cookies are handy to store small amounts of data, such as tokens or flags.

## Reading cookies

You could obtain Cookie values from server request that's available as route handler (such as controller action) argument:

```php
private function actionProfile(\Psr\Http\Message\ServerRequestInterface $request)
{
    $cookieValues = $request->getCookieParams();
    $cookieValue = $cookieValues['cookieName'] ?? null;
    // ...
}
```

In addition to getting cookie values directly from the server request, you can also use the [yiisoft/request-provider](https://github.com/yiisoft/request-provider) package, which provides a more structured way to handle cookies through the `\Yiisoft\RequestProvider\RequestCookieProvider`. This approach can simplify your code and improve readability.

Here’s an example of how to work with cookies using the `\Yiisoft\RequestProvider\RequestCookieProvider`:

```php
final readonly class MyService
{
    public function __construct(
        private \Yiisoft\RequestProvider\RequestCookieProvider $cookies
    ) {}

    public function go(): void
    {
        // Check if a specific cookie exists
        if ($this->cookies->has('foo')) {
            // Retrieve the value of the cookie
            $fooValue = $this->cookies->get('foo');
            // Do something with the cookie value
        }

        // Retrieve another cookie value
        $barValue = $this->cookies->get('bar');
        // Do something with the bar cookie value
    }
}
```
## Sending cookies

Since sending cookies is, in fact, sending a header but since forming the header isn't trivial, there is
`\Yiisoft\Cookies\Cookie` class to help with it:

```php
$cookie = (new \Yiisoft\Cookies\Cookie('cookieName', 'value'))
    ->withPath('/')
    ->withDomain('yiiframework.com')
    ->withHttpOnly(true)
    ->withSecure(true)
    ->withSameSite(\Yiisoft\Cookies\Cookie::SAME_SITE_STRICT)
    ->withMaxAge(new \DateInterval('P7D'));

return $cookie->addToResponse($response);
```

After forming a cookie call `addToResponse()` passing an instance of `\Psr\Http\Message\ResponseInterface` to add corresponding HTTP headers to it.

## Signing and encrypting cookies

To prevent the substitution of the cookie value, the package provides two implementations:

`Yiisoft\Cookies\CookieSigner` - signs each cookie with a unique prefix hash based on the value of the cookie and a secret key. `Yiisoft\Cookies\CookieEncryptor` - encrypts each cookie with a secret key.

Encryption is more secure than signing but has lower performance.

```php
$cookie = new \Yiisoft\Cookies\Cookie('identity', 'identityValue');

// The secret key used to sign and validate cookies.
$key = '0my1xVkjCJnD_q1yr6lUxcAdpDlTMwiU';

$signer = new \Yiisoft\Cookies\CookieSigner($key);
$encryptor = new \Yiisoft\Cookies\CookieEncryptor($key);

$signedCookie = $signer->sign($cookie);
$encryptedCookie = $encryptor->encrypt($cookie);
```

To validate and get back the pure value, use the `validate()` and `decrypt()` method.

```php
$cookie = $signer->validate($signedCookie);
$cookie = $encryptor->decrypt($encryptedCookie);
```

If the cookie value is tampered with or hasn't been signed/encrypted before, a `\RuntimeException` will be thrown. Therefore, if you aren't sure that the cookie value was signed/encrypted earlier, first use the `isSigned()` and `isEncrypted()` methods, respectively.

```php
if ($signer->isSigned($cookie)) {
    $cookie = $signer->validate($cookie);
}

if ($encryptor->isEncrypted($cookie)) {
    $cookie = $encryptor->decrypt($cookie);
}
```

It makes sense to sign or encrypt the value of a cookie if you store important data that a user shouldn't change.

### Automating encryption and signing

To automate the encryption/signing and decryption/validation of cookie values, use an instance of `Yiisoft\Cookies\CookieMiddleware`, which is [PSR-15](https://www.php-fig.org/psr/psr-15/) middleware.

This middleware provides the following features:

- Validates and decrypts the cookie parameter values from the request.
- Excludes the cookie parameter from the request if it was tampered with and logs information about it.
- Encrypts/signs cookie values and replaces their clean values in the `Set-Cookie` headers in the response.

In order for the middleware to know which values of which cookies need to be encrypted/signed, an array of settings must be passed to its constructor. The array keys are cookie name patterns and values are constant values of `CookieMiddleware::ENCRYPT` or `CookieMiddleware::SIGN`.

```php
use Yiisoft\Cookies\CookieMiddleware;

$cookiesSettings = [
    // Exact match with the name `identity`.
    'identity' => CookieMiddleware::ENCRYPT,
    // Matches any number from 1 to 9 after the underscore.
    'name_[1-9]' => CookieMiddleware::SIGN,
    // Matches any string after the prefix, including an
    // empty string, except for the delimiters "/" and "\".
    'prefix*' => CookieMiddleware::SIGN,
];
```

For more information on using the wildcard pattern, see the [yiisoft/strings](https://github.com/yiisoft/strings#wildcardpattern-usage) package.

Creating and using middleware:

```php
/**
 * @var \Psr\Http\Message\ServerRequestInterface $request
 * @var \Psr\Http\Server\RequestHandlerInterface $handler
 * @var \Psr\Log\LoggerInterface $logger
 */

// The secret key used to sign and validate cookies.
$key = '0my1xVkjCJnD_q1yr6lUxcAdpDlTMwiU';
$signer = new \Yiisoft\Cookies\CookieSigner($key);
$encryptor = new \Yiisoft\Cookies\CookieEncryptor($key);

$cookiesSettings = [
    'identity' => \Yiisoft\Cookies\CookieMiddleware::ENCRYPT,
    'session' => \Yiisoft\Cookies\CookieMiddleware::SIGN,
];

$middleware = new \Yiisoft\Cookies\CookieMiddleware(
    $logger
    $encryptor,
    $signer,
    $cookiesSettings,
);

// The cookie parameter values from the request are decrypted/validated.
// The cookie values are encrypted/signed and appended to the response.
$response = $middleware->process($request, $handler);
```

If the `$cookiesSettings` array is empty, no cookies will be encrypted and signed.

## Cookies security

You should configure each cookie to be secure. Important security settings are:

- `httpOnly`. Setting it to `true` would prevent JavaScript to access cookie value.
- `secure`. Setting it to `true` would prevent sending cookie via `HTTP`. It will be sent via `HTTPS` only.
- `sameSite`, if set to either `SAME_SITE_LAX` or `SAME_SITE_STRICT` would prevent sending a cookie in cross-site browsing context. `SAME_SITE_LAX` would prevent cookie sending during CSRF-prone request methods (e.g., POST, PUT, PATCH, etc.). `SAME_SITE_STRICT` would prevent cookies sending for all methods.
- Sign or encrypt the value of the cookie to prevent spoofing of values if the data in the value shouldn't be tampered with.
# Handling errors

Yii has a [yiisoft/error-handler](https://github.com/yiisoft/error-handler) package that makes error handling a much more pleasant experience than before. In particular, the Yii error handler provides the following:

- [PSR-15](https://www.php-fig.org/psr/psr-15/) middleware for catching unhandled errors.
- PSR-15 middleware for mapping certain exceptions to custom responses.
- Production and debug modes.
- Debug mode displays details, stacktrace, has dark and light themes and handy buttons to search for error without typing.
- Takes PHP settings into account.
- Handles out of memory errors, fatal errors, warnings, notices, and exceptions.
- Can use any [PSR-3](https://www.php-fig.org/psr/psr-3/) compatible logger for error logging.
- Detects a response format based on a mime type of the request.
- Supports responding with HTML, plain text, JSON, XML, and headers out of the box.
- You can implement your own error rendering for extra types.

This guide describes how to use the error handler in the [Yii framework](https://www.yiiframework.com/), for information about using it separate from Yii, see the [package description](https://github.com/yiisoft/error-handler).

## Using error handler

The error handler consists of two parts. One part is `Yiisoft\ErrorHandler\Middleware\ErrorCatcher` middleware that, when registered, catches exceptions that may appear during middleware stack execution and passes them to the handler. Another part is the error handler itself, `Yiisoft\ErrorHandler\ErrorHandler`, that's catching exceptions occurring outside the middleware stack and fatal errors. The handler also converts warnings and notices to exceptions and does more handy things.

Error handler is registered in the application itself. Usually it happens in `ApplicationRunner`. By default, the handler configuration comes from the container. You may configure it in the application configuration, `config/web.php` like the following:

```php
use Psr\Log\LoggerInterface;
use Yiisoft\ErrorHandler\ErrorHandler;
use Yiisoft\ErrorHandler\Renderer\HtmlRenderer;
use Yiisoft\ErrorHandler\ThrowableRendererInterface;

return [
    // ...
    ErrorHandler::class => static function (LoggerInterface $logger, ThrowableRendererInterface $renderer) {
        $errorHandler = new ErrorHandler($logger, $renderer);
        // Set the size of the reserved memory to 512 KB. Defaults to 256KB.
        $errorHandler->memoryReserveSize(524_288);
        return $errorHandler;
    },
    
    ThrowableRendererInterface::class => static fn () => new HtmlRenderer([
        // Defaults to the package file "templates/production.php".
        'template' => '/full/path/to/production/template/file',
        // Defaults to package file "templates/development.php".
        'verboseTemplate' => '/full/path/to/development/template/file',
        // Maximum number of source code lines to be displayed. Defaults to 19.
        'maxSourceLines' => 20,
        // Maximum number of trace source code lines to be displayed. Defaults to 13.
        'maxTraceLines' => 5,
        // Trace the header line with placeholders (file, line, icon) to be substituted. Defaults to `null`.
        'traceHeaderLine' => '<a href="ide://open?file={file}&line={line}">{icon}</a>',
    ]),
    // ...
];
```

As aforementioned, the error handler turns all non-fatal PHP errors into catchable exceptions (`Yiisoft\ErrorHandler\Exception\ErrorException`). This means you can use the following code to deal with PHP errors:

```php
try {
    10 / 0;
} catch (\Yiisoft\ErrorHandler\Exception\ErrorException $e) {
    // Write a log or something else.
}
// execution continues...
```

The package has another middleware, `Yiisoft\ErrorHandler\Middleware\ExceptionResponder`. This middleware maps certain exceptions to custom responses. Configure it in the application configuration as follows:

```php
use Psr\Http\Message\ResponseFactoryInterface;
use Yiisoft\ErrorHandler\Middleware\ExceptionResponder;
use Yiisoft\Injector\Injector;

return [
    // ...
    ExceptionResponder::class => static function (ResponseFactoryInterface $responseFactory, Injector $injector) {
        $exceptionMap = [
            // Status code with which the factory creates the response.
            MyNotFoundException::class => 404,
            // PHP callable that must return a `Psr\Http\Message\ResponseInterface`.
            MyHttpException::class => static fn (MyHttpException $exception) => new MyResponse($exception),
            // ...
        ];
        
        return new ExceptionResponder($exceptionMap, $responseFactory, $injector);
    },
];
```

Note that when configuring application middleware stack, you must place `Yiisoft\ErrorHandler\Middleware\ExceptionResponder` before `Yiisoft\ErrorHandler\Middleware\ErrorCatcher`.

## Rendering error data

One of the renderers could render error data into a certain format. The following renderers are available out of the box:

- `Yiisoft\ErrorHandler\Renderer\HeaderRenderer` - Renders error into HTTP headers. It's used for `HEAD` request.
- `Yiisoft\ErrorHandler\Renderer\HtmlRenderer` - Renders error into HTML.
- `Yiisoft\ErrorHandler\Renderer\JsonRenderer` - Renders error into JSON.
- `Yiisoft\ErrorHandler\Renderer\PlainTextRenderer` - Renders error into plain text.
- `Yiisoft\ErrorHandler\Renderer\XmlRenderer` - Renders error into XML.

The renderer produces detailed error data depending on whether debug mode is enabled or disabled.

An Example of header rendering with a debugging mode turned off:

```
...
X-Error-Message: An internal server error occurred.
...
```

An Example of header rendering with a debugging mode turned on:

```
...
X-Error-Type: Error
X-Error-Message: Call to undefined function App\Controller\myFunc()
X-Error-Code: 0
X-Error-File: /var/www/yii/app/src/Controller/SiteController.php
X-Error-Line: 21
...
```

Example of JSON rendering output with a debugging mode turned off:

```json
{"message":"An internal server error occurred."}
```

An Example of JSON rendering output with debugging mode turned on:

```json
{
    "type": "Error",
    "message": "Call to undefined function App\\Controller\\myFunc()",
    "code": 0,
    "file": "/var/www/yii/app/src/Controller/SiteController.php",
    "line": 21,
    "trace": [
        {
            "function": "index",
            "class": "App\\Controller\\SiteController",
            "type": "->"
        },
        {
            "file": "/var/www/yii/app/vendor/yiisoft/injector/src/Injector.php",
            "line": 63,
            "function": "invokeArgs",
            "class": "ReflectionFunction",
            "type": "->"
        },
        ...
    ]
}
```

The error catcher chooses how to render an exception based on `accept` HTTP header. If it's `text/html` or any unknown content type, it will use the error or exception HTML template to display errors. For other mime types, the error handler will choose different renderers that you register within the error catcher. By default, it supports JSON, XML, and plain text.

### Implementing your own renderer

You may customize the error response format by providing your own instance of `Yiisoft\ErrorHandler\ThrowableRendererInterface` when registering error catcher middleware.

```php
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\ErrorHandler\ErrorData;
use Yiisoft\ErrorHandler\ThrowableRendererInterface;

final readonly class MyRenderer implements ThrowableRendererInterface
{
    public function render(Throwable $t, ServerRequestInterface $request = null): ErrorData
    {
        return new ErrorData($t->getMessage());
    }

    public function renderVerbose(Throwable $t, ServerRequestInterface $request = null): ErrorData
    {
        return new ErrorData(
            $t->getMessage(),
            ['X-Custom-Header' => 'value-header'], // Headers to be added to the response.
        );
    }
};
```

You may configure it in the application configuration `config/web.php`:

```php
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Yiisoft\ErrorHandler\ErrorHandler;
use Yiisoft\ErrorHandler\Middleware\ErrorCatcher;

return [
    // ...
    ErrorCatcher::class => static function (ContainerInterface $container): ErrorCatcher {
        $errorCatcher = new ErrorCatcher(
            $container->get(ResponseFactoryInterface::class),
            $container->get(ErrorHandler::class),
            $container,
        );
        // Returns a new instance without renderers by the specified content types.
        $errorCatcher = $errorCatcher->withoutRenderers('application/xml', 'text/xml');
        // Returns a new instance with the specified content type and renderer class.
        return $errorCatcher->withRenderer('my/format', new MyRenderer());
    },
    // ...
];
```

## Friendly exceptions

Yii error renderer supports [friendly exceptions](https://github.com/yiisoft/friendly-exception) that make error handling an even more pleasant experience for your team. The idea is to offer a readable name and possible solutions to the problem:

```php
use Yiisoft\FriendlyException\FriendlyExceptionInterface;

final readonly class RequestTimeoutException extends \RuntimeException implements FriendlyExceptionInterface
{
    public function getName(): string
    {
        return 'Request timed out';
    }
    
    public function getSolution(): ?string
    {
        return <<<'SOLUTION'
Likely it is a result of resource request is not responding in a timely fashion. Try increasing timeout.
SOLUTION;
    }
}
```

When the application throws such an exception, the error renderer would display the name and the solution if the debug mode is on.
# Logging

Yii relies on [PSR-3 interfaces](https://www.php-fig.org/psr/psr-3/) for logging, so you could configure any PSR-3 compatible logging library to do the actual job.

Yii provides its own logger that's highly customizable and extensible. Using it, you can log various types of messages, filter them, and gather them at different targets, such as files or emails.

Using the Yii logging framework involves the following steps:

- Record [log messages](https://yiisoft.github.io/docs/guide/runtime/logging.html#log-messages) at various places in your code;
- Configure [log targets](https://yiisoft.github.io/docs/guide/runtime/logging.html#log-targets) in the application configuration to filter and export log messages;
- Examine the filtered logged messages exported by different targets (e.g. the Yii debugger).

In this section, the focus in on the first two steps.

## Log Messages

To record log messages, you need an instance of PSR-3 logger. A class that writes log messages should receive it as a dependency:

```php
class MyService
{
    private $logger;
    
    public function __construct(\Psr\Log\LoggerInterface $logger)
    {
        $this->logger = $logger;    
    }
}
```

Recording a log message is as simple as calling one of the following logging methods that correspond to log levels:

- `emergency` - System is unusable.
- `alert` - Action must be taken immediately. Example: Entire website down, database unavailable, etc. This should trigger the SMS alerts and wake you up.
- `critical` - Critical conditions. Example: Application component unavailable, unexpected exception.
- `error` - Runtime errors that don't require immediate action but should typically be logged and monitored.
- `warning` - Exceptional occurrences that aren't errors. Example: Use of deprecated APIs, poor use of an API, undesirable things that aren't necessarily wrong.
- `notice` - Normal but significant events.
- `info` - Interesting events. Example: User logs in, SQL logs.
- `debug` - Detailed debug information.

Each method has two arguments. The first is a message. The Second is a context array that typically has structured data that doesn't fit a message well but still does offer important information. In case you provide an exception as context, you should pass the "exception" key. Another special key is "category." Categories are handy to better organize and filter log messages.

```php
use \Psr\Log\LoggerInterface;

final readonly class MyService
{
    public function __construct(
        private LoggerInterface $logger
    )
    {    
    }

    public function serve(): void
    {
        $this->logger->info('MyService is serving', ['context' => __METHOD__]);    
    }
}
```

When deciding on a category for a message, you may choose a hierarchical naming scheme, which will make it easier for [log targets](https://yiisoft.github.io/docs/guide/runtime/logging.html#log-targets) to filter messages based on their categories. A simple yet effective naming scheme is to use the PHP magic constant `__METHOD__` for the category names. This is also the approach used in the core Yii framework code.

The `__METHOD__` constant evaluates as the name of the method (prefixed with the fully qualified class name) where the constant appears. For example, it's equal to the string `'App\\Service\\MyService::serve'` if the above line of code is called within this method.

IMPORTANT

The logging methods described above are actually shortcuts to the [[\Psr\Log\LoggerInterface::log()]].

Note that PSR-3 package provides `\Psr\Log\NullLogger` class that provides the same set of methods but doesn't log anything. That means that you don't have to check if logger is configured with `if ($logger !== null)` and, instead, can assume that logger is always present.

## Log targets

A log target is an instance of a class that extends the [[\Yiisoft\Log\Target]]. It filters the log messages by their severity levels and categories and then exports them to some medium. For example, a [[\Yiisoft\Log\Target\File\FileTarget|file target]]exports the filtered log messages to a file, while a [[Yiisoft\Log\Target\Email\EmailTarget|email target]] exports the log messages to specified email addresses.

You can register many log targets in an application by configuring them through the `\Yiisoft\Log\Logger` constructor:

```php
use \Psr\Log\LogLevel;

$fileTarget = new \Yiisoft\Log\Target\File\FileTarget('/path/to/app.log');
$fileTarget->setLevels([LogLevel::ERROR, LogLevel::WARNING]);

$emailTarget = new \Yiisoft\Log\Target\Email\EmailTarget($mailer, ['to' => 'log@example.com']);
$emailTarget->setLevels([LogLevel::EMERGENCY, LogLevel::ALERT, LogLevel::CRITICAL]);
$emailTarget->setCategories(['Yiisoft\Cache\*']); 

$logger = new \Yiisoft\Log\Logger([
    $fileTarget,
    $emailTarget
]);
```

In the above code, two log targets are registered:

- the first target selects error and warning messages and writes them to `/path/to/app.log` file;
- the second target selects emergency, alert, and critical messages under the categories whose names start with `Yiisoft\Cache\`, and sends them in an email to both `admin@example.com` and `developer@example.com`.

Yii comes with the following built-in log targets. Please refer to the API documentation about these classes to learn how to configure and use them.

- [[\Yiisoft\Log\PsrTarget]]: passes log messages to another PSR-3 compatible logger.
- [[\Yiisoft\Log\StreamTarget]]: writes log messages into a specified output stream.
- [[\Yiisoft\Log\Target\Db\DbTarget]]: saves log messages in database.
- [[\Yiisoft\Log\Target\Email\EmailTarget]]: sends log messages to pre-specified email addresses.
- [[\Yiisoft\Log\Target\File\FileTarget]]: saves log messages in files.
- [[\Yiisoft\Log\Target\Syslog\SyslogTarget]]: saves log messages to syslog by calling the PHP function `syslog()`.

In the following, we will describe the features common to all log targets.

### Message Filtering

For each log target, you can configure its levels and categories to specify which severity levels and categories of the messages the target should process.

The target `setLevels()` method takes an array consisting of one or several of `\Psr\Log\LogLevel` constants.

By default, the target will process messages of _any_ severity level.

The target `setCategories()` method takes an array consisting of message category names or patterns. A target will only process messages whose category can be found or match one of the patterns in this array. A category pattern is a category name prefix with an asterisk `*` at its end. A category name matches a category pattern if it starts with the same prefix of the pattern. For example, `Yiisoft\Cache\Cache::set` and `Yiisoft\Cache\Cache::get` both match the pattern `Yiisoft\Cache\*`.

By default, the target will process messages of _any_ category.

Besides allowing the categories by the `setCategories()` method, you may also deny certain categories by the `setExcept()` method. If the category of a message is found or matches one of the patterns in this property, the target will NOT process it.

The following target configuration specifies that the target should only process error and warning messages under the categories whose names match either `Yiisoft\Cache\*` or `App\Exceptions\HttpException:*`, but not `App\Exceptions\HttpException:404`.

```php
$fileTarget = new \Yiisoft\Log\Target\File\FileTarget('/path/to/app.log');
$fileTarget->setLevels([LogLevel::ERROR, LogLevel::WARNING]);
$fileTarget->setCategories(['Yiisoft\Cache\*', 'App\Exceptions\HttpException:*']);
$fileTarget->setExcept(['App\Exceptions\HttpException:404']);
```

### Message Formatting

Log targets export the filtered log messages in a certain format. For example, if you install a log target of the class [[\Yiisoft\Log\Target\File\FileTarget]], you may find a log message similar to the following in the log file:

```
2020-12-05 09:27:52.223800 [info][application] Some message

Message context:

time: 1607160472.2238
memory: 4398536
category: 'application'
```

By default, log messages have the following format:

```
Timestamp Prefix[Level][Category] Message Context
```

You may customize this format by calling [[\Yiisoft\Log\Target::setFormat()|setFormat()]] method, which takes a PHP callable returning a custom-formatted message.

```php
$fileTarget = new \Yiisoft\Log\Target\File\FileTarget('/path/to/app.log');

$fileTarget->setFormat(static function (\Yiisoft\Log\Message $message) {
    $category = strtoupper($message->context('category'));
    return "({$category}) [{$message->level()}] {$message->message()}";
});

$logger = new \Yiisoft\Log\Logger([$fileTarget]);
$logger->info('Text message', ['category' => 'app']);

// Result:
// (APP) [info] Text message
```

In addition, if you're comfortable with the default message format but need to change the timestamp format or add custom data to the message, you can call the [[\Yiisoft\Log\Target::setTimestampFormat()|setTimestampFormat()]] and [[\Yiisoft\Log\Target::setPrefix()|setPrefix()]] methods. For example, the following code changes the timestamp format and configures a log target to prefix each log message with the current user ID (IP address and Session ID are removed for privacy reasons).

```php
$fileTarget = new \Yiisoft\Log\Target\File\FileTarget('/path/to/app.log');
$userId = '123e4567-e89b-12d3-a456-426655440000';

// Default: 'Y-m-d H: i: s.u'
$fileTarget->setTimestampFormat('D d F Y');
// Default: ''
$fileTarget->setPrefix(static fn () => "[{$userId}]");

$logger = new \Yiisoft\Log\Logger([$fileTarget]);
$logger->info('Text', ['category' => 'user']);

// Result:
// Fri 04 December 2020 [123e4567-e89b-12d3-a456-426655440000][info][user] Text
// Message context: ...
// Common context: ...
```

The PHP callable that's passed to the [[\Yiisoft\Log\Target::setFormat()|setFormat()]] and [[\Yiisoft\Log\Target::setPrefix()|setPrefix()]] methods has the following signature:

```php
function (\Yiisoft\Log\Message $message, array $commonContext): string;
```

Besides message prefixes, log targets also append some common context information to each of the log messages. You may adjust this behavior by calling target [[\Yiisoft\Log\Target::setCommonContext()|setCommonContext()]] method, passing an array of data in the `key => value` format that you want to include. For example, the following log target configuration specifies that only the value of the `$_SERVER` variable will be appended to the log messages.

```php
$fileTarget = new \Yiisoft\Log\Target\File\FileTarget('/path/to/app.log');
$fileTarget->setCommonContext(['server' => $_SERVER]);
```

### Message Trace Level

During development, it's often desirable to see where each log message is coming from. You can achieve this by calling the [[\Yiisoft\Log\Logger::setTraceLevel()|setTraceLevel()]] method like the following:

```php
$logger = new \Yiisoft\Log\Logger($targets);
$logger->setTraceLevel(3);
```

This application configuration sets the trace level to be 3, so each log message will be appended with at most three levels of the call stack at which the log message is recorded. You can also set a list of paths to exclude from the trace by calling the [[\Yiisoft\Log\Logger::setExcludedTracePaths()|setExcludedTracePaths()]] method.

```php
$logger = new \Yiisoft\Log\Logger($targets);
$logger->setExcludedTracePaths(['/path/to/file', '/path/to/folder']);
```

IMPORTANT

Getting call stack information isn't trivial. Therefore, you should only use this feature during development or when debugging an application.

### Message flushing and exporting

As aforementioned, log messages are maintained in an array by [[\Yiisoft\Log\Logger|logger object]]. To limit the memory consumption by this array, the logger will flush the recorded messages to the [log targets](https://yiisoft.github.io/docs/guide/runtime/logging.html#log-targets) each time the array accumulates a certain number of log messages. You can customize this number by calling the [[\Yiisoft\Log\Logger::setFlushInterval()]] method:

```php
$logger = new \Yiisoft\Log\Logger($targets);
$logger->setFlushInterval(100); // default is 1000
```

IMPORTANT

Message flushing also occurs when the application ends, which ensures log targets can receive complete log messages.

When the [[\Yiisoft\Log\Logger|logger object]] flushes log messages to [log targets](https://yiisoft.github.io/docs/guide/runtime/logging.html#log-targets), they don't get exported immediately. Instead, the message exporting only occurs when a log target accumulates a certain number of the filtered messages. You can customize this number by calling the [[\Yiisoft\Log\Target::setExportInterval()|setExportInterval()]] method of individual [log targets](https://yiisoft.github.io/docs/guide/runtime/logging.html#log-targets), like the following:

```php
$fileTarget = new \Yiisoft\Log\Target\File\FileTarget('/path/to/app.log');
$fileTarget->setExportInterval(100); // default is 1000
```

Because of the flushing and exporting level setting, by default when you call any logging method, you will NOT see the log message immediately in the log targets. This could be a problem for some long-running console applications. To make each log message appear immediately in the log targets, you should set both flush interval and export interval to be 1, as shown below:

```php
$fileTarget = new \Yiisoft\Log\Target\File\FileTarget('/path/to/app.log');
$fileTarget->setExportInterval(1);

$logger = new \Yiisoft\Log\Logger([$fileTarget]);
$logger->setFlushInterval(1);
```

NOTE

Frequent message flushing and exporting will degrade the performance of your application.

### Toggling log targets

You can enable or disable a log target by calling its [[\Yiisoft\Log\Target::enable()|enable()] ] and [[\Yiisoft\Log\Target::disable()|disable()]] methods. You may do so via the log target configuration or by the following PHP statement in your code:

```php
$fileTarget = new \Yiisoft\Log\Target\File\FileTarget('/path/to/app.log');
$logger = new \Yiisoft\Log\Logger([$fileTarget, /*Other targets*/]);

foreach ($logger->getTargets() as $target) {
    if ($target instanceof \Yiisoft\Log\Target\File\FileTarget) {
        $target->disable();
    }
}
```

To check whether the log target is enabled, call the `isEnabled()` method. You also may pass callable to [[\Yiisoft\Log\Target::setEnabled()|setEnabled()]] to define a dynamic condition for whether the log target should be enabled or not.

### Creating new targets

Creating a new log target class is straightforward. You mainly need to implement the [[\Yii\Log\Target::export()]] abstract method that sends all accumulated log messages to a designated medium.

The following protected methods will also be available for child targets:

- `getMessages` - Get a list of log messages ([[\Yii\Log\Message]] instances).
- `getFormattedMessages` - Get a list of log messages formatted as strings.
- `formatMessages` - Get all log messages formatted as a string.
- `getCommonContext` - Get an array with common context data in the `key => value` format.

For more details, you may refer to any of the log target classes included in the package.

TIP

Instead of creating your own loggers, you may try any PSR-3 compatible logger such as [Monolog](https://github.com/Seldaek/monolog) by using [[\Yii\Log\PsrTarget]].

```php
/**
 * @var \Psr\Log\LoggerInterface $psrLogger
 */

$psrTarget = new \Yiisoft\Log\PsrTarget($psrLogger);
$logger = new \Yiisoft\Log\Logger([$psrTarget]);

$logger->info('Text message');
```
# Authentication

Authentication is the process of verifying the identity of a user. It usually uses an identifier (for example, a username or an email address) and a secret token (such as a password or an access token) to judge if the user is the one whom he claims as. Authentication is the basis of the login feature.

Yii provides an authentication framework which wires up various components to support login. To use this framework, you mainly need to do the following work:

- Configure the `Yiisoft\User\CurrentUser` service;
- Create a class implementing the `\Yiisoft\Auth\IdentityInterface` interface;
- Create a class implementing the `\Yiisoft\Auth\IdentityRepositoryInterface` interface;

To use the `Yiisoft\User\CurrentUser` service, install [yiisoft/user](https://github.com/yiisoft/user) package:

```bash
composer require yiisoft/user
```

## Configuring `Yiisoft\User\CurrentUser`

The `Yiisoft\User\CurrentUser` application service manages the user authentication status. It depends on `Yiisoft\Auth\IdentityRepositoryInterface` that should return an instance of `\Yiisoft\Auth\IdentityInterface` which has the actual authentication logic.

```php
use Yiisoft\Session\Session;
use Yiisoft\Session\SessionInterface;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\Definitions\Reference;

return [
    // ...

    SessionInterface::class => [
        'class' => Session::class,
        '__construct()' => [
            $params['session']['options'] ?? [],
            $params['session']['handler'] ?? null,
        ],
    ],
    IdentityRepositoryInterface::class => IdentityRepository::class,
    CurrentUser::class => [
        'withSession()' => [Reference::to(SessionInterface::class)]
    ],
];
```

## Implementing`\Yiisoft\Auth\IdentityInterface`

The identity class must implement the `\Yiisoft\Auth\IdentityInterface` which has a single method:

- [[yii\web\IdentityInterface::getId()|getId()]]: it returns the ID of the user represented by this identity instance.

In the following example, an identity class is implemented as a pure PHP object.

```php
<?php

namespace App\User;

use Yiisoft\Auth\IdentityInterface;

final readonly class Identity implements IdentityInterface
{
    public function __construct(
        private string $id
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }
}
```

## Implementing`\Yiisoft\Auth\IdentityRepositoryInterface`

The identity repository class must implement the `\Yiisoft\Auth\IdentityRepositoryInterface` which has the following methods:

- `findIdentity(string $id): ?IdentityInterface`: it looks for an instance of the identity class using the specified ID. This method is used when you need to keep the login status via session.
- `findIdentityByToken(string $token, string $type): ?IdentityInterface`: it looks for an instance of the identity class using the specified access token. This method is used when you need to authenticate a user by a single secret token (for example, in a stateless REST API).

A dummy implementation may look like the following:

```php
namespace App\User;

use App\User\Identity;
use \Yiisoft\Auth\IdentityInterface;
use \Yiisoft\Auth\IdentityRepositoryInterface;

final readonly class IdentityRepository implements IdentityRepositoryInterface
{
    private const USERS = [
      [
        'id' => 1,
        'token' => '12345'   
      ],
      [
        'id' => 42,
        'token' => '54321'
      ],  
    ];

    public function findIdentity(string $id) : ?IdentityInterface
    {
        foreach (self::USERS as $user) {
            if ((string)$user['id'] === $id) {
                return new Identity($id);            
            }
        }
        
        return null;
    }

    public function findIdentityByToken(string $token, string $type) : ?IdentityInterface
    {
        foreach (self::USERS as $user) {
             if ($user['token'] === $token) {
                 return new Identity((string)$user['id']);            
             }
         }
         
         return null;
    }
}
```

## Using `Yiisoft\User\CurrentUser`

You can use `Yiisoft\User\CurrentUser` service to get current user identity. As any service, it could be auto-wired in either action handler constructor or method:

```php
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\User\CurrentUser;

final readonly class SiteController
{
    public function __construct(private CurrentUser $user) {}

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->user->isGuest()) {
            // user is guest
        } else {
            $identity = $this->user->getIdentity();
            // do something based on identity
        }
    }
}
```

`isGuest()` determines if user is logged in or not. `getIdentity()` returns an instance of identity.

To log in a user, you may use the following code:

```php
$identity = $identityRepository->findByEmail($email);

/* @var $user \Yiisoft\User\CurrentUser */
$user->login($identity);
```

The `login()` method sets the identity to the User service. It stores identity into session so user authentication status is maintained.

To log out a user, call

```php
/* @var $user \Yiisoft\User\CurrentUser */
$user->logout();
```

## Protecting Routes

The `Yiisoft\Auth\Middleware\Authentication` middleware can be used to restrict access to a given route to authenticated users only.

First, configure the `Yiisoft\Auth\AuthenticationMethodInterface`:

```php
use Yiisoft\Auth\AuthenticationMethodInterface;
use Yiisoft\User\Method\WebAuth;

return [
    // ...
    AuthenticationMethodInterface::class => WebAuth::class,
];
```

Then, apply the `Yiisoft\Auth\Middleware\Authentication` middleware to a route:

```php
use Yiisoft\Auth\Middleware\Authentication;
 
Route::post('/create')
        ->middleware(Authentication::class)
        ->action([SiteController::class, 'create'])
        ->name('site/create')
```

Or to a group of routes:

```php
use Yiisoft\Auth\Middleware\Authentication;

Group::create()
        ->middleware(Authentication::class)
        ->routes(
            Route::post('/create')
                ->action([SiteController::class, 'create'])
                ->name('site/create'),
            Route::put('/update/{id}')
                ->action([SiteController::class, 'update'])
                ->name('site/update')
        )
```

## Authentication Events

The user service raises a few events during the login and logout processes.

- `\Yiisoft\User\Event\BeforeLogin`: raised at the beginning of `login()`. If the event handler calls `invalidate()` on an event object, the login process will be cancelled.
- `\Yiisoft\User\Event\AfterLogin`: raised after a successful login.
- `\Yiisoft\User\Event\BeforeLogout`: raised at the beginning of `logout()`. If the event handler calls `invalidate()` on an event object, the logout process will be cancelled.
- `\Yiisoft\User\Event\AfterLogout`: raised after a successful logout.

You may respond to these events to implement features such as login audit, online user statistics. For example, in the handler for `\Yiisoft\User\Event\AfterLogin`, you may record the login time and IP address in the `user` database table.
# Authorization

Authorization is the process of verifying that a user has enough permission to do something.

## Checking for permission

You can check if a user has certain permissions by using `\Yiisoft\User\CurrentUser` service:

```php
namespace App\Blog\Post;

use Yiisoft\User\CurrentUser;
use Yiisoft\Router\HydratorAttribute\RouteArgument;
use Psr\Http\Message\ResponseInterface;

final readonly class PostController
{
    public function __construct(
        private PostRepositoryInterface $postRepository,
        private CurrentUser $user
    )
    {
    }

    public function update(#[RouteArgument('id')] int $id): ResponseInterface
    {
        $post = $this->postRepository->findByPK($id);
        if ($post === null) {
            // respond with 404
        }

        if (!$this->canCurrentUserUpdatePost($post)) {
            // respond with 403
        }

        // continue with updating the post
    }

    private function canCurrentUserUpdatePost(Post $post): bool
    {
        return $post->getAuthorId() === $this->user->getId() &&
            $this->user->can('updatePost');
    }
}
```

Behind the scenes, `Yiisoft\User\CurrentUser::can()` method calls `Yiisoft\Access\AccessCheckerInterface::userHasPermission()` so you should provide an implementation in dependency container in order for it to work.

## Role-based access control (RBAC)

Role-Based Access Control (RBAC) provides a simple yet powerful centralized access control. Please refer to the [Wikipedia](https://en.wikipedia.org/wiki/Role-based_access_control) for details about comparing RBAC with other more traditional access control schemes.

Yii implements a General Hierarchical RBAC, following the [NIST RBAC model](https://csrc.nist.gov/CSRC/media/Publications/conference-paper/2000/07/26/the-nist-model-for-role-based-access-control-towards-a-unified-/documents/sandhu-ferraiolo-kuhn-00.pdf).

Using RBAC involves two parts of work. The first part is to build up the RBAC authorization data, and the second part is to use the authorization data to perform access check in places where it's necessary. Since RBAC implements `\Yiisoft\Access\AccessCheckerInterface`, using it's similar to using any other implementation of an access checker.

To ease description next, there are some basic RBAC concepts first.

### Basic concepts

A role represents a collection of _permissions_ (for example, creating posts, updating posts). You may assign a role to one or many users. To check if a user has a specified permission, you may check if the user has a role with that permission.

Associated with each role or permission, there may be a _rule_. A rule represents a piece of code that an access checker will execute to decide if the corresponding role or permission applies to the current user. For example, the "update post" permission may have a rule that checks if the current user is the post creator. During access checking, if the user is NOT the post creator, there's no "update post" permission.

Both roles and permissions are in a hierarchy. In particular, a role may consist of other roles or permissions. And a permission may consist of other permissions. Yii implements a _partial order_ hierarchy which includes the more special _tree_ hierarchy. While a role can contain a permission, it isn't `true` vice versa.

### Configuring RBAC

Yii RBAC requires storage to be provided.

One of the following storages could be installed:

- [PHP storage](https://github.com/yiisoft/rbac-php) - PHP file storage;
- [DB storage](https://github.com/yiisoft/rbac-db) - database storage based on [Yii DB](https://github.com/yiisoft/db);
- [Cycle DB storage](https://github.com/yiisoft/rbac-cycle-db) - database storage based on [Cycle DBAL](https://github.com/cycle/database).

You can also provide your own storage using the [yiisoft/rbac](https://github.com/yiisoft/rbac) package.

#### Configuring RBAC with the [PHP storage](https://github.com/yiisoft/rbac-php)

Install [yiisoft/rbac-php](https://github.com/yiisoft/rbac-php) package:

```bash
composer require yiisoft/rbac-php
```

Before we set off to define authorization data and perform access checking, you need to configure the `Yiisoft\Access\AccessCheckerInterface` in dependency container:

```php
use Yiisoft\Rbac\ItemsStorageInterface;
use Yiisoft\Rbac\AssignmentsStorageInterface;
use Yiisoft\Rbac\ManagerInterface;
use Yiisoft\Rbac\Php\ItemsStorage;
use Yiisoft\Rbac\Php\AssignmentsStorage;
use Yiisoft\Access\AccessCheckerInterface;
use Yiisoft\User\CurrentUser;

return [
    // ...
    ItemsStorageInterface::class => [
        'class' => ItemsStorage::class,
        '__construct()' => [
            'filePath' => $params['rbacItemsStorageFilePath']
        ]
    ],
    AssignmentsStorageInterface::class => [
        'class' => AssignmentsStorage::class,
        '__construct()' => [
            'filePath' => $params['rbacAssignmentsStorageFilePath']
        ]
    ],
    AccessCheckerInterface::class => ManagerInterface::class,

    CurrentUser::class => [
        'withAccessChecker()' => [Reference::to(AccessCheckerInterface::class)]
    ],
];
```

`Yiisoft\Rbac\Manager` uses PHP script files to store authorization data. Make sure the directory and all the files in it are writable by the Web server process if you want to change permission hierarchy online.

#### Configuring RBAC with the [DB storage](https://github.com/yiisoft/rbac-db)

Install [yiisoft/rbac-db](https://github.com/yiisoft/rbac-db) package:

```bash
composer require yiisoft/rbac-db
```

Install one of the following drivers:

- [SQLite](https://github.com/yiisoft/db-sqlite) (minimal required version is 3.8.3)
- [MySQL](https://github.com/yiisoft/db-mysql)
- [PostgreSQL](https://github.com/yiisoft/db-pgsql)
- [Microsoft SQL Server](https://github.com/yiisoft/db-mssql)
- [Oracle](https://github.com/yiisoft/db-oracle)

[Configure connection](https://yiisoft.github.io/docs/guide/start/databases.html#configuring-connection).

Before we set off to define authorization data and perform access checking, you need to configure the `Yiisoft\Access\AccessCheckerInterface` in dependency container:

```php
use Yiisoft\Rbac\ItemsStorageInterface;
use Yiisoft\Rbac\AssignmentsStorageInterface;
use Yiisoft\Rbac\ManagerInterface;
use Yiisoft\Rbac\Db\ItemsStorage;
use Yiisoft\Rbac\Db\AssignmentsStorage;
use Yiisoft\Access\AccessCheckerInterface;

return [
    // ...
    ItemsStorageInterface::class => ItemsStorage::class,
    AssignmentsStorageInterface::class => AssignmentsStorage::class,
    AccessCheckerInterface::class => ManagerInterface::class,
];
```

Add the RBAC [DB storage](https://github.com/yiisoft/rbac-db) migration paths to params.php:

```php
return [
    // ...
    'yiisoft/db-migration' => [
        'sourcePaths' => [
             __DIR__ . '/../../vendor/yiisoft/rbac-db/migrations/items',
             __DIR__ . '/../../vendor/yiisoft/rbac-db/migrations/assignments',
        ],
    ],
];
```

Apply migrations:

```bash
./yii migrate:up
```

### Building authorization data

Building authorization data is all about the following tasks:

- defining roles and permissions;
- establishing relations between roles and permissions;
- defining rules;
- associating rules with roles and permissions;
- assigning roles to users.

Depending on authorization flexibility requirements, you can do the tasks in different ways. If only developers change your permission hierarchy, you can use either migrations or a console command. Migration advantage is that you could execute it along with other migrations. The Console command advantage is that you have a good overview of the hierarchy in the code without a need to read many migrations.

In case you want to build permission hierarchy dynamically, you need a UI or a console command. The API used to build the hierarchy itself won't be different.

### Using console command

If your permission hierarchy doesn't change at all, and you have a fixed number of users, you can create a [console command](https://yiisoft.github.io/docs/guide/tutorial/console-applications.html) that will initialize authorization data once via APIs offered by `\Yiisoft\Rbac\ManagerInterface`:

```php
<?php
namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Yiisoft\Rbac\ManagerInterface;
use Yiisoft\Rbac\Permission;
use Yiisoft\Rbac\Role;
use Yiisoft\Yii\Console\ExitCode;

#[AsCommand(
    name: 'rbac:init',
    description: 'Builds RBAC hierarchy',
)]
final class RbacCommand extends Command
{
    private const CREATE_POST_PERMISSION = 'createPost';
    private const UPDATE_POST_PERMISSION = 'updatePost';
    private const ROLE_AUTHOR = 'author';
    private const ROLE_ADMIN = 'admin';

    public function __construct(private ManagerInterface $manager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->removeAll();

        $this->manager->addPermission((new Permission(RbacCommand::CREATE_POST_PERMISSION))->withDescription('Create a post'));
        $this->manager->addPermission((new Permission(RbacCommand::UPDATE_POST_PERMISSION))->withDescription('Update post'));

        // add the "author" role and give this role the "createPost" permission
        $this->manager->addRole(new Role(RbacCommand::ROLE_AUTHOR));
        $this->manager->addChild(RbacCommand::ROLE_AUTHOR, RbacCommand::CREATE_POST_PERMISSION);

        // add the "admin" role and give this role the "updatePost" permission
        // as well as the permissions of the "author" role
        $this->manager->addRole(new Role(RbacCommand::ROLE_ADMIN));
        $this->manager->addChild(RbacCommand::ROLE_ADMIN, RbacCommand::UPDATE_POST_PERMISSION);
        $this->manager->addChild(RbacCommand::ROLE_ADMIN, RbacCommand::ROLE_AUTHOR);

        // Assign roles to users. 1 and 2 are IDs returned by IdentityInterface::getId()
        // usually implemented in your User model.
        $this->manager->assign(RbacCommand::ROLE_AUTHOR, 2);
        $this->manager->assign(RbacCommand::ROLE_ADMIN, 1);

        return ExitCode::OK;
    }

    private function removeAll(): void
    {
        $this->manager->revokeAll(2);
        $this->manager->revokeAll(1);

        $this->manager->removeRole(RbacCommand::ROLE_ADMIN);
        $this->manager->removeRole(RbacCommand::ROLE_AUTHOR);

        $this->manager->removePermission(RbacCommand::CREATE_POST_PERMISSION);
        $this->manager->removePermission(RbacCommand::UPDATE_POST_PERMISSION);
    }
}
```

Add the command to `config/console/commands.php`:

```php
return [ 
    // ...
    'rbac:init' => App\Command\RbacCommand::class
];
```

You can execute the command above from the console the following way:

```bash
./yii rbac:init
```

> If you don't want to hardcode what users have certain roles, don't put `->assign()` calls into the command. Instead, create either UI or console command to manage assignments.

#### Using migrations

**TODO**: finish it when migrations are implemented.

You can use [migrations](https://yiisoft.github.io/docs/guide/databases/db-migrations.html) to initialize and change hierarchy via APIs offered by `\Yiisoft\Rbac\ManagerInterface`.

Create new migration using `./yii migrate:create init_rbac` then implement creating a hierarchy:

```php
<?php
namespace App\Migration;

use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Rbac\ManagerInterface;
use Yiisoft\Rbac\Permission;
use Yiisoft\Rbac\Role;

final class M260112125812InitRbac implements RevertibleMigrationInterface
{
    private const CREATE_POST_PERMISSION = 'createPost';
    private const UPDATE_POST_PERMISSION = 'updatePost';
    private const ROLE_AUTHOR = 'author';
    private const ROLE_ADMIN = 'admin';

    public function __construct(private ManagerInterface $manager)
    {
    }

    public function up(MigrationBuilder $b): void
    {
        $this->manager->addPermission((new Permission(M260112125812InitRbac::CREATE_POST_PERMISSION))->withDescription('Create a post'));
        $this->manager->addPermission((new Permission(M260112125812InitRbac::UPDATE_POST_PERMISSION))->withDescription('Update post'));

        // add the "author" role and give this role the "createPost" permission
        $this->manager->addRole(new Role(M260112125812InitRbac::ROLE_AUTHOR));
        $this->manager->addChild(M260112125812InitRbac::ROLE_AUTHOR, M260112125812InitRbac::CREATE_POST_PERMISSION);

        // add the "admin" role and give this role the "updatePost" permission
        // as well as the permissions of the "author" role
        $this->manager->addRole(new Role(M260112125812InitRbac::ROLE_ADMIN));
        $this->manager->addChild(M260112125812InitRbac::ROLE_ADMIN, M260112125812InitRbac::UPDATE_POST_PERMISSION);
        $this->manager->addChild(M260112125812InitRbac::ROLE_ADMIN, M260112125812InitRbac::ROLE_AUTHOR);

        // Assign roles to users. 1 and 2 are IDs returned by IdentityInterface::getId()
        // usually implemented in your User model.
        $this->manager->assign(M260112125812InitRbac::ROLE_AUTHOR, 2);
        $this->manager->assign(M260112125812InitRbac::ROLE_ADMIN, 1);
    }

    public function down(MigrationBuilder $b): void
    {
        $this->manager->revokeAll(2);
        $this->manager->revokeAll(1);

        $this->manager->removeRole(M260112125812InitRbac::ROLE_ADMIN);
        $this->manager->removeRole(M260112125812InitRbac::ROLE_AUTHOR);

        $this->manager->removePermission(M260112125812InitRbac::CREATE_POST_PERMISSION);
        $this->manager->removePermission(M260112125812InitRbac::UPDATE_POST_PERMISSION);
    }
}
```

> If you don't want to hardcode which users have certain roles, don't put `->assign()` calls in migrations. Instead, create either UI or console command to manage assignments.

You could apply migration by using `./yii migrate:up`.

## Assigning roles to users

TODO: update when signup implemented in demo / template.

The author can create a post, admin can update the post and do everything the author can.

If your application allows user signup, you need to assign roles to these new users at once. For example, in order for all signed-up users to become authors in your advanced project template, you need to change `frontend\models\SignupForm::signup()` as follows:

```php
public function signup()
{
    if ($this->validate()) {
        $user = new User();
        $user->username = $this->username;
        $user->email = $this->email;
        $user->setPassword($this->password);
        $user->generateAuthKey();
        $user->save(false);

        // the following three lines were added:
        $authorRole = $this->manager->getRole('author');
        if ($authorRole !== null) {
            $this->manager->assign($authorRole->getName(), $user->getId());
        }

        return $user;
    }

    return null;
}
```

For applications that require complex access control with dynamically updated authorization data (such as an admin panel), you many need to develop special user interfaces using APIs offered by `Yiisoft\Rbac\Manager`.

### Using rules

As aforementioned, rules add extra constraint to roles and permissions. A rule is a class extending from `\Yiisoft\Rbac\Rule`. It must implement the `execute()` method. In the hierarchy you've created before, the author can't edit his own post. Let's fix it. First, you need a rule to verify that the user is the post author:

```php
namespace App\User\Rbac;

use Yiisoft\Rbac\Item;
use Yiisoft\Rbac\RuleContext;
use Yiisoft\Rbac\RuleInterface;

/**
 * Checks if the authorID matches user passed via params.
 */
final readonly class AuthorRule implements RuleInterface
{
    public function execute(?string $userId, Item $item, RuleContext $context): bool
    {
        $post = $context->getParameterValue('post');
        return $post !== null && $post->getAuthorId() == $userId;
    }
}
```

The rule checks if user created the `post`. Create a special permission `updateOwnPost` in the command you've used before:

```php
use Yiisoft\Rbac\Permission;
use Yiisoft\Rbac\ManagerInterface;

// add the "updateOwnPost" permission and associate the rule with it.
$updateOwnPost = (new Permission('updateOwnPost'))
    ->withDescription('Update own post')
    ->withRuleName(AuthorRule::class);
$this->manager->addPermission($updateOwnPost);

// "updateOwnPost" will be used from "updatePost"
$this->manager->addChild($updateOwnPost->getName(), $updatePost->getName());

// allow "author" to update their own posts
$this->manager->addChild($authorRole->getName(), $updateOwnPost->getName());

// Remove this line since we don't want the AuthorRule to be applied to the 'admin' role
$this->manager->addChild('admin', 'author');
```
### Access check

The check is done similarly to how it was done in the first section of this guide:

```php
namespace App\Blog\Post;

use Yiisoft\User\CurrentUser;
use Yiisoft\Router\HydratorAttribute\RouteArgument;
use Psr\Http\Message\ResponseInterface;

final readonly class PostController
{
    public function __construct(
        private PostRepositoryInterface $postRepository,
        private CurrentUser $user
    )
    {
    }

    public function update(#[RouteArgument('id')] int $id): ResponseInterface
    {
        $post = $this->postRepository->findByPK($id);
        if ($post === null) {
            // respond with 404
        }

        if (!$this->canCurrentUserUpdatePost($post)) {
            // respond with 403
        }

        // continue with updating the post
    }

    private function canCurrentUserUpdatePost(Post $post): bool
    {
        return $this->user->can('updatePost', ['post' => $post]);
    }
}
```

The difference is that now checking for a user's own post is part of the RBAC.

To check if a user can update a post, you need to pass an extra parameter that's required by `AuthorRule` described before:

```php
if ($user->can('updatePost', ['post' => $post])) {
    // update post
}
```

You're starting with the `updatePost` and going through `updateOwnPost`. To pass the access check, `AuthorRule` should return `true` from its `execute()` method. The method receives its `$params` from the `can()` method call, so the value is `['post' => $post]`. If everything is fine, you will get to `author` assigned to John.
## Implementing your own access checker

If RBAC doesn't suit your needs, you can implement your own access checker without changing the application code:

```php
namespace App\User;

use \Yiisoft\Access\AccessCheckerInterface;

final readonly class AccessChecker implements AccessCheckerInterface
{
    private const PERMISSIONS = [
        [
            1 => ['editPost'],
            42 => ['editPost', 'deletePost'],
        ],
    ];

    public function userHasPermission($userId, string $permissionName, array $parameters = []) : bool
    {
        if (!array_key_exists($userId, self::PERMISSIONS)) {
            return false;
        }

        return in_array($permissionName, self::PERMISSIONS[$userId], true); 
    }
}
```
# Working with passwords

Most developers know that passwords can't be stored in plain text, but many developers believe it's still safe to hash passwords using `md5`, `sha1` or `sha256` etc. There was a time when using the aforementioned hashing algorithms was enough, but modern hardware makes it possible to reverse such hashes and even stronger ones using brute force attacks.

To offer increased security for user passwords, even in the worst case scenario (when one breaches your application), you need to use a hashing algorithm that's resilient against brute force attacks. The best current choice is `argon2`. Yii `yiisoft/security` package make securely generate and verify hashes easier and ensure the best possible hashing solution used.

To use it, you need to require the package first:

```bash
composer require yiisoft/security
```

When a user provides a password for the first time (e.g., upon registration), the password needs to be hashed and stored:

```php
$hash = (new PasswordHasher())->hash($password);

// save hash to a database or another storage
saveHash($hash);
```

When a user attempts to log in, the submitted password must be verified against the previously hashed and stored password:

```php
// get hash from a database or another storage
$hash = getHash();

if ((new PasswordHasher())->validate($password, $hash)) {
    // all good, logging in user
} else {
    // wrong password
}
```
# Cryptography

In this section, we'll review the following security aspects:

- Generating random data
- Encryption and Decryption
- Confirming Data Integrity

To use these features, you need to install `yiisoft/security` package:

```bash
composer install yiisoft/security
```

## Generating pseudorandom data

Pseudorandom data are useful in many situations. For example, when resetting a password via email, you need to generate a token, save it to the database, and send it via email to the end user, which in turn will allow them to prove ownership of that account. It's important that this token be unique and hard to guess, else there is a possibility that an attacker can predict the token's value and reset the user's password.

`\Yiisoft\Security\Random` makes generating pseudorandom data simple:

```php
$key = \Yiisoft\Security\Random::string(42);
```

The code above would give you a random string consisting of 42 characters.

If you need bytes or integers, use PHP functions directly:

- `random_bytes()` for bytes. Note that the output may not be ASCII.
- `random_int()` for integers.

## Encryption and decryption

Yii provides convenient helper functions to encrypt/decrypt data using a secret key. The data is passed through the encryption function so that only the person who has the secret key will be able to decrypt it. For example, you need to store some information in your database, but you need to make sure only the user who has the secret key can view it (even if one compromises the application database):

```php
$encryptedData = (new \Yiisoft\Security\Crypt())->encryptByPassword($data, $password);

// save data to a database or another storage
saveData($encryptedData);
```

Decrypting it:

```php
// collect encrypted data from a database or another storage
$encryptedData = getEncryptedData();

$data = (new \Yiisoft\Security\Crypt())->decryptByPassword($encryptedData, $password);
```

You could use a key instead of a password:

```php
$encryptedData = (new \Yiisoft\Security\Crypt())->encryptByKey($data, $key);

// save data to a database or another storage
saveData($encryptedData);
```

Decrypting it:

```php
// collect encrypted data from a database or another storage
$encryptedData = getEncryptedData();

$data = (new \Yiisoft\Security\Crypt())->decryptByKey($encryptedData, $key);
```

## Confirming data integrity

There are situations in which you need to verify that your data hasn't been tampered with by a third party or even corrupted in some way. Yii provides a way to confirm data integrity by MAC signing.

The `$key` should be present at both sending and receiving sides. On the sending side:

```php
$signedMessage = (new \Yiisoft\Security\Mac())->sign($message, $key);

sendMessage($signedMessage);
```

On the receiving side:

```php
$signedMessage = receiveMessage($signedMessage);

try {
    $message = (new \Yiisoft\Security\Mac())->getMessage($signedMessage, $key);
} catch (\Yiisoft\Security\DataIsTamperedException $e) {
    // data is tampered
}
```

## Masking token length

Masking a token helps to mitigate a BREACH attack by randomizing how the token outputted on each request. A random mask is applied to the token, making the string always unique.

To mask a token:

```php
$maskedToken = \Yiisoft\Security\TokenMask::apply($token);
```

To get the original value from the masked one:

```php
$token = \Yiisoft\Security\TokenMask::remove($maskedToken);
```
# Trusted request

Getting user information, like a host and IP address, will work out of the box in a normal setup where a single webserver is used to serve the website. If your Yii application, however, runs behind a reverse proxy, you need to add configuration to retrieve this information as the direct client is now the proxy, and the user IP address is passed to the Yii application by a header set by the proxy.

You shouldn't blindly trust headers provided by proxies unless you explicitly trust the proxy. Yii supports configuring trusted proxies via the `Yiisoft\Yii\Web\Middleware\TrustedHostsNetworkResolver`. You should add it to [middleware stack](https://yiisoft.github.io/docs/guide/structure/middleware.html).

The following is a request config for an application that runs behind an array of reverse proxies, which are located in the `10.0.2.0/24` IP network:

```php
/** @var \Yiisoft\Yii\Web\Middleware\TrustedHostsNetworkResolver $trustedHostsNetworkResolver */
$trustedHostsNetworkResolver = $trustedHostsNetworkResolver->withAddedTrustedHosts(['1.0.2.0/24']);
```

The proxy sends the IP in the `X-Forwarded-For` header by default, and the protocol (`http` or `https`) is in `X-Forwarded-Proto`.

In case your proxies are using different headers, you can use the request configuration to adjust these, e.g.:

```php
/** @var \Yiisoft\Yii\Web\Middleware\TrustedHostsNetworkResolver $trustedHostsNetworkResolver */
$trustedHostsNetworkResolver = $trustedHostsNetworkResolver
    ->withAddedTrustedHosts(
        ['1.0.2.0/24'],
        ['X-ProxyUser-Ip'],
        ['Front-End-Https'],
        [],
        [],
        ['X-Proxy-User-Ip']
    );
```

With the above configuration, `X-ProxyUser-Ip` and `Front-End-Https` headers are used to get user IP and protocol.
# Security best practices

Below, we'll review common security principles and describe how to avoid threats when developing applications using Yii. Most of these principles aren't unique to Yii alone but apply to website or software development in general, so you will also find links for further reading on the general ideas behind these.

## Basic principles

There are two main principles when it comes to security no matter which application is being developed:

1. Filter input.
2. Escape output.

### Filter input

Filter input means that you should never consider input safe, and you should always check if the value you've got is actually among allowed ones. For example, if you know that you sort by three fields `title`, `created_at` and `status` and the field came from user input, it's better to check the value you've got right where you're receiving it. In terms of basic PHP, that would look like the following:

```php
$sortBy = $_GET['sort'];
if (!in_array($sortBy, ['title', 'created_at', 'status'])) {
	throw new \InvalidArgumentException('Invalid sort value.');
}
```

In Yii, most probably you'll use form validation to do similar checks.

Further reading on the topic:

- [https://owasp.org/www-community/vulnerabilities/Improper_Data_Validation](https://owasp.org/www-community/vulnerabilities/Improper_Data_Validation)
- [https://cheatsheetseries.owasp.org/cheatsheets/Input_Validation_Cheat_Sheet.html](https://cheatsheetseries.owasp.org/cheatsheets/Input_Validation_Cheat_Sheet.html)

### Escape output

Escape output means that, depending on the context where you're using data, you should prepend it with special characters to negate its special meaning. In context of HTML you should escape `<`, `>` and alike special characters. In the context of JavaScript or SQL, it will be a different set of characters. Since it's error-prone to escape manually, Yii provides various tools to perform escaping in different contexts.

Further reading on the topic:

- [https://owasp.org/www-community/attacks/Command_Injection](https://owasp.org/www-community/attacks/Command_Injection)
- [https://owasp.org/www-community/attacks/Code_Injection](https://owasp.org/www-community/attacks/Code_Injection)
- [https://owasp.org/www-community/attacks/xss/](https://owasp.org/www-community/attacks/xss/)

## Avoiding SQL injections

SQL injection happens when you form a query text by concatenating unescaped strings such as the following:

```php
$username = $_GET['username'];
$sql = "SELECT * FROM user WHERE username = '$username'";
```

Instead of supplying correct username attacker could give your applications something like `'; DROP TABLE user; --`. The Resulting SQL will be the following:

```sql
SELECT * FROM user WHERE username = ''; DROP TABLE user; --'
```

This is a valid query that will search for users with empty username and then will drop `user` table most probably resulting in a broken website and data loss (you've set up regular backups, right?).

Make sure to either use PDO prepared statements directly or ensure that the library you prefer is doing it. In the case of prepared statements, it's impossible to manipulate the query as was demonstrated above.

If you use data to specify column names or table names, the best thing to do is to allow only a predefined set of values:

```php
function actionList($orderBy = null)
{
    if (!in_array($orderBy, ['name', 'status'])) {
        throw new \InvalidArgumentException('Only name and status are allowed to order by.');
    }
    
    // ...
}
```

Further reading on the topic:

- [https://owasp.org/www-community/attacks/SQL_Injection](https://owasp.org/www-community/attacks/SQL_Injection)

## Avoiding XSS

XSS or cross-site scripting happens when output isn't escaped properly when outputting HTML to the browser. For example, if user can enter his name and instead of `Alexander` he enters `<script>alert('Hello!');</script>`, every page that outputs username without escaping it will execute JavaScript `alert('Hello!');` resulting in alert box popping up in a browser. Depending on the website instead of innocent alert, such a script could send messages using your name or even perform bank transactions.

Avoiding XSS is quite easy in Yii. There are two cases:

1. You want to output data as plain text.
2. You want to output data as HTML.

If all you need is plain text, then escaping is as easy as the following:

```php
<?= \Yiisoft\Html\Html::encode($username) ?>
```

If it should be HTML, you could get some help from [HtmlPurifier](http://htmlpurifier.org/). Note that HtmlPurifier processing is quite heavy, so consider adding caching.

Further reading on the topic:

- [https://owasp.org/www-community/attacks/xss/](https://owasp.org/www-community/attacks/xss/)

## Avoiding CSRF

CSRF is an abbreviation for cross-site request forgery. The idea is that many applications assume that requests coming from a user browser are made by the user themselves. This assumption could be false.

For example, the website `an.example.com` has a `/logout` URL that, when accessed using a simple GET request, logs the user out. As long as it's requested by the user themselves everything is OK, but one day bad guys are somehow posting `<img src="http://an.example.com/logout">` on a forum the user often visits. The browser doesn't make any difference between requesting an image or requesting a page so when the user opens a page with such a manipulated `<img>` tag, the browser will send the GET request to that URL and the user will be logged out from `an.example.com`.

That's the basic idea of how a CSRF attack works. One can say that logging out a user isn't a serious thing. However, this was just an example. There are many more things one could do using this approach. For example, triggering payments or changing data. Imagine that some website has a URL `http://an.example.com/purse/transfer?to=anotherUser&amount=2000`. Accessing it using GET request, causes transfer of $2000 from an authorized user account to user `anotherUser`. You know that the browser will always send a GET request to load an image, so you can change the code to accept only POST requests on that URL. Unfortunately, this won't save you, because an attacker can put some JavaScript code instead of `<img>` tag, which allows sending POST requests to that URL as well.

For this reason, Yii applies extra mechanisms to protect against CSRF attacks.

To avoid CSRF, you should always:

1. Follow HTTP specification. GET shouldn't change the application state. See [RFC2616](https://www.w3.org/Protocols/rfc2616/rfc2616-sec9.html) for more details.
2. Keep Yii CSRF protection enabled.

Yii has CSRF protection as `Yiisoft\Yii\Web\Middleware\Csrf` middleware. Make sure it's in your application middleware stack.

Further reading on the topic:

- [https://owasp.org/www-community/attacks/csrf](https://owasp.org/www-community/attacks/csrf)
- [https://owasp.org/www-community/SameSite](https://owasp.org/www-community/SameSite)

## Avoiding file exposure

By default, server webroot is meant to be pointed to `public` directory where `index.php` is. In the case of shared hosting environments, it could be impossible to achieve, so you'll end up with all the code, configs and logs in server webroot.

If so, remember to deny access to everything except `web`. If it's impossible, consider hosting your application elsewhere.

## Avoiding debug info and tools in production

In debug mode, Yii shows quite verbose errors which are certainly helpful for development. The thing is that these verbose errors are handy for attacker as well since these could reveal database structure, configuration values and parts of your code.

Never run production applications with debugger or Gii accessible to everyone. One could use it to get information about database structure, code and to simply rewrite code with what's generated by Gii.

You should avoid the debug toolbar in production unless necessary. It exposes all the application and config details possible. If you absolutely need it, check twice you restrict access to your IP only.

Further reading on the topic:

- [https://owasp.org/www-project-.net/articles/Exception_Handling.md](https://owasp.org/www-project-.net/articles/Exception_Handling.md)
- [https://owasp.org/www-pdf-archive/OWASP_Top_10_2007.pdf](https://owasp.org/www-pdf-archive/OWASP_Top_10_2007.pdf)

## Using secure connection over TLS

Yii provides features that rely on cookies and/or PHP sessions. These can be vulnerable in case your connection is compromised. The risk is reduced if the app uses secure connection via TLS (often referred to as [SSL](https://en.wikipedia.org/wiki/Transport_Layer_Security)).

Nowadays, anyone can get a certificate for free and automatically update it thanks to [Let's Encrypt](https://letsencrypt.org/).

## Secure server configuration

The purpose of this section is to highlight risks that need to be considered when creating a server configuration for serving a Yii-based website. Besides the points covered here, there may be other security-related configuration options to be considered, so don't consider this section to be complete.

### Avoiding `Host`-header attacks

If the webserver is configured to serve the same site independent of the value of the `Host` header, this information mayn't be reliable and [may be faked by the user sending the HTTP request](https://www.acunetix.com/vulnerabilities/web/host-header-attack). In such situations, you should fix your webserver configuration to serve the site only for specified host names.

For more information about the server configuration, please refer to the documentation of your webserver:

- Apache 2: [https://httpd.apache.org/docs/trunk/vhosts/examples.html#defaultallports](https://httpd.apache.org/docs/trunk/vhosts/examples.html#defaultallports)
- Nginx: [https://www.nginx.com/resources/wiki/start/topics/examples/server_blocks/](https://www.nginx.com/resources/wiki/start/topics/examples/server_blocks/)

### Configuring SSL peer validation

There is a typical misconception about how to solve SSL certificate validation issues such as:

```
cURL error 60: SSL certificate problem: unable to get local issuer certificate
```

or

```
stream_socket_enable_crypto(): SSL operation failed with code 1. OpenSSL Error messages: error:1416F086:SSL routines:tls_process_server_certificate:certificate verify failed
```

Many sources wrongly suggest disabling SSL peer verification. That shouldn't be ever done since it enables man-in-the middle type of attacks. Instead, PHP should be configured properly:

1. Download [https://curl.haxx.se/ca/cacert.pem](https://curl.haxx.se/ca/cacert.pem).
2. Add the following to your php.ini:

```
openssl.cafile="/path/to/cacert.pem"
curl.cainfo="/path/to/cacert.pem".
```

Note that you should keep the file up to date.
# Data caching

Data caching is about storing some PHP variables in a cache and retrieving them later from the cache. It's also the foundation for more advanced caching features, such as [page caching](https://yiisoft.github.io/docs/guide/caching/page.html).

To use cache, install [yiisoft/cache](https://github.com/yiisoft/cache) package:

```bash
composer require yiisoft/cache
```

The following code is a typical usage pattern of data caching, where `$cache` refers to a `Cache` instance from the package:

```php
public function getTopProducts(\Yiisoft\Cache\CacheInterface $cache): array
{
    $key = ['top-products', $count = 10];
    
    // Try retrieving $data from cache.
    $data = $cache->getOrSet($key, function (\Psr\SimpleCache\CacheInterface $cache) use ($count) {
        // Can't find $data in a cache, calculate it from scratch.
        return getTopProductsFromDatabase($count);
    }, 3600);
    
    return $data;
}
```

When cache has data associated with the `$key`, it returns the cached value. Otherwise, it executes the passed anonymous function to calculate the value to cache and return.

If the anonymous function requires some data from the outer scope, you can pass it with the `use` statement.

## Cache handlers

The cache service uses [PSR-16](https://www.php-fig.org/psr/psr-16/) compatible cache handlers which represent various cache storages, such as memory, files, and databases.

Yii provides the following handlers:

- `NullCache` — a cache placeholder which does no real caching. The purpose of this handler is to simplify the code that needs to check the availability of cache. For example, during development or if the server doesn't have actual cache support, you may configure a cache service to use this handler. When you enable actual cache support, you can switch to using the corresponding cache handler. In both cases, you may use the same code without extra checks.
- `ArrayCache` — provides caching for the current request only by storing the values in an array.
- [APCu](https://github.com/yiisoft/cache-apcu) - uses a PHP [APC](https://secure.php.net/manual/en/book.apc.php) extension. You can consider this option as the fastest one when dealing with cache for a centralized thick application (e.g., one server, no dedicated load balancers, etc.).
- [Database](https://github.com/yiisoft/cache-db) — uses a database table to store cached data.
- [File](https://github.com/yiisoft/cache-file) — uses standard files to store cached data. This is particularly suitable to cache large chunks of data, such as page content.
- [Memcached](https://github.com/yiisoft/cache-memcached) — uses a PHP [memcached](https://secure.php.net/manual/en/book.memcached.php) extension. You can consider this option as the fastest one when dealing with cache in a distributed application  
    (e.g., with several servers, load balancers, etc.)
- [Wincache](https://github.com/yiisoft/cache-wincache) — uses PHP [WinCache](https://iis.net/downloads/microsoft/wincache-extension) ([see also](https://secure.php.net/manual/en/book.wincache.php)) extension.

[You could find more handlers at packagist.org](https://packagist.org/providers/psr/simple-cache-implementation).

TIP

You may use different cache storage in the same application. A common strategy is:

- To use memory-based cache storage to store small but constantly used data (e.g., statistics)
- To use file-based or database-based cache storage to store big and less often used data (e.g., page content)

Cache handlers are usually set up in a [dependency injection container](https://yiisoft.github.io/docs/guide/concept/di-container.html) so that they can be globally configurable and accessible.

Because all cache handlers support the same set of APIs, you can swap the underlying cache handler with a different one. You can do it by reconfiguring the application without modifying the code that uses the cache.

### Cache keys

A key uniquely identifies each data item stored in the cache. When you store a data item, you have to specify a key for it. Later, when you retrieve the data item, you should give the corresponding key.

You may use a string or an arbitrary value as a cache key. When a key isn't a string, it will be automatically serialized into a string.

A common strategy of defining a cache key is to include all determining factors in terms of an array.

When different applications use the same cache storage, you should specify a unique cache key prefix for each application to avoid conflicts of cache keys. You can do this by using `\Yiisoft\Cache\PrefixedCache` decorator:

```php
$arrayCacheWithPrefix = new \Yiisoft\Cache\PrefixedCache(new \Yiisoft\Cache\ArrayCache(), 'myapp_');
$cache = new \Yiisoft\Cache\Cache($arrayCacheWithPrefix);
```

### Cache expiration

A data item stored in a cache will remain there forever unless it's removed because of some caching policy enforcement. For example, caching space is full and cache storage removes the oldest data. To change this behavior, you can set a TTL parameter when calling a method to store a data item:

```php
$ttl = 3600;
$data = $cache->getOrSet($key, function (\Psr\SimpleCache\CacheInterface $cache) use ($count) {
return getTopProductsFromDatabase($count);
}, $ttl);
```

The `$ttl` parameter indicates for how many seconds the data item can remain valid in the cache. When you retrieve the data item, if it has passed the expiration time, the method will execute the function and set the resulting value into cache.

You may set the default TTL for the cache:

```php
$cache = new \Yiisoft\Cache\Cache($arrayCache, 60 * 60); // 1 hour
```

Additionally, you can invalidate a cache key explicitly:

```php
$cache->remove($key);
```

### Invalidation dependencies

Besides the expiration setting, changes of the so-called **invalidation dependencies** may also invalidate cached data item. For example, `\Yiisoft\Cache\Dependency\FileDependency` represents the dependency of a file's modification time. When this dependency changes, it means something modifying the corresponding file. As a result, any outdated file content found in the cache should invalidate.

Cache dependencies are objects of `\Yiisoft\Cache\Dependency\Dependency` descendant classes. When you store a data item in the cache, you can pass along an associated cache dependency object. For example,

```php
/**
 * @var callable $callable
 * @var \Yiisoft\Cache\CacheInterface $cache
 */

use Yiisoft\Cache\Dependency\TagDependency;

// Set many cache values marking both with a tag.
$cache->getOrSet('item_42_price', $callable, null, new TagDependency('item_42'));
$cache->getOrSet('item_42_total', $callable, 3600, new TagDependency('item_42'));

// Trigger invalidation by tag.
TagDependency::invalidate($cache, 'item_42');
```

Below is a summary of the available cache dependencies:

- `\Yiisoft\Cache\Dependency\ValueDependency`: invalidates the cache when specified value changes.
- `\Yiisoft\Cache\Dependency\CallbackDependency`: invalidates the cache when the result of the specified PHP callback is different.
- `\Yiisoft\Cache\Dependency\FileDependency`: invalidates the cache when the file's last modification time is different.
- `\Yiisoft\Cache\Dependency\TagDependency`: associates a cached data item with one or many tags. You may invalidate the cached data items with the specified tag(s) by calling `TagDependency::invalidate()`.

You may combine many dependencies using `\Yiisoft\Cache\Dependency\AnyDependency` or `\Yiisoft\Cache\Dependency\AllDependencies`.

To implement your own dependency, extend from `\Yiisoft\Cache\Dependency\Dependency`.

### Cache stampede prevention

[A cache stampede](https://en.wikipedia.org/wiki/Cache_stampede) is a type of cascading failure that can occur when massively parallel computing systems with caching mechanisms come under a high load. This behavior is sometimes also called dog-piling.

The `\Yiisoft\Cache\Cache` uses a built-in "Probably early expiration" algorithm that prevents cache stampede. This algorithm randomly fakes a cache miss for one user while others are still served the cached value. You can control its behavior with the fifth optional parameter of `getOrSet()`, which is a float value called `$beta`. By default, beta is `1.0`, which is usually enough. The higher the value, the earlier cache will be re-created.

```php
/**
 * @var mixed $key
 * @var callable $callable
 * @var \DateInterval $ttl
 * @var \Yiisoft\Cache\CacheInterface $cache
 * @var \Yiisoft\Cache\Dependency\Dependency $dependency
 */

$beta = 2.0;
$cache->getOrSet($key, $callable, $ttl, $dependency, $beta);
```
# Migrations

During the course of developing and maintaining a database-driven application, the structure of the database being used evolves just like the source code does. For example, during the development of an application, a new table may be found necessary; after the application is deployed to production, it may be discovered that an index should be created to improve the query performance; and so on. Because a database structure change often requires some source code changes, Yii supports the so-called _database migration_ feature that allows you to keep track of database changes in terms of _database migrations_ which are version-controlled together with the source code.

The following steps show how database migration can be used by a team during development:

1. Tim creates a new migration (e.g. creates a new table, changes a column definition, etc.).
2. Tim commits the new migration into the source control system (e.g. Git, Mercurial).
3. Doug updates his repository from the source control system and receives the new migration.
4. Doug applies the migration to his local development database, thereby synchronizing his database to reflect the changes that Tim has made.

And the following steps show how to deploy a new release with database migrations to production:

1. Scott creates a release tag for the project repository that contains some new database migrations.
2. Scott updates the source code on the production server to the release tag.
3. Scott applies any accumulated database migrations to the production database.

Yii provides a set of migration command line tools that allow you to:

- create new migrations;
- apply migrations;
- revert migrations;
- re-apply migrations;
- show migration history and status.

All these tools are accessible through the command `yii migrate`. In this section we will describe in detail how to accomplish various tasks using these tools.

TIP

Migrations could affect not only database schema but adjust existing data to fit new schema, create RBAC hierarchy or clean up cache.

NOTE

When manipulating data using a migration you may find that using your Active Record or entity classes for this might be useful because some of the logic is already implemented there. Keep in mind however, that in contrast to code written in the migrations, whose nature is to stay constant forever, application logic is subject to change. So when using Active Record or entity classes in migration code, changes to the logic in the source code may accidentally break the existing migrations. For this reason migration code should be kept independent of other application logic such.

## Initial configuration

To use migrations, install [yiisoft/db-migration](https://github.com/yiisoft/db-migration/) package:

```bash
make composer require yiisoft/db-migration
```

Create a directory to store migrations `src/Migration` right in the project root.

Add the following configuration to `config/common/params.php`:

```php
'yiisoft/db-migration' => [
    'newMigrationNamespace' => 'App\\Migration',
    'sourceNamespaces' => ['App\\Migration'],
],
```

If you want to place migrations elsewhere, you can define the path in `newMigrationPath`. If your migrations to be applied are from multiple sources, such as external modules, `sourcePaths` could be used to define these.

You need a database connection configured as well. See [Working with databases](https://yiisoft.github.io/docs/guide/start/databases.html) for an example of configuring it for PostgreSQL.

## Creating a migration

To create a new empty migration, run the following command:

```bash
make shell
./yii migrate:create <name>
```

The required `name` argument gives a brief description about the new migration. For example, if the migration is about creating a new table named _news_, you may use the name `create_news_table` and run the following command:

```bash
make shell
./yii migrate:create create_news_table
```

NOTE

Because the `name` argument will be used as part of the generated migration class name, it should only contain letters, digits, and/or underscore characters.

The above command will create a new PHP class file named `src/Migration/M251225221906CreateNewsTable.php`. The file contains the following code which mainly declares a migration class with the skeleton code:

```php
<?php

declare(strict_types=1);

namespace App\Migration;

use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;

final class M251225221906CreateNewsTable implements RevertibleMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        // TODO: Implement the logic to apply the migration.
    }

    public function down(MigrationBuilder $b): void
    {
        // TODO: Implement the logic to revert the migration.
    }
}
```

In the migration class, you are expected to write code in the `up()` method that makes changes to the database structure. You may also want to write code in the `down()` method to revert the changes made by `up()`. The `up()` method is invoked when you upgrade the database with this migration, while the `down()` method is invoked when you downgrade the database. The following code shows how you may implement the migration class to create a `news` table:

```php
<?php

declare(strict_types=1);

namespace App\Migration;

use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;

final class M251225221906CreateNewsTable implements RevertibleMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $cb = $b->columnBuilder();

        $b->createTable('news', [
            'id' => $cb::uuidPrimaryKey(),
            'title' => $cb::string()->notNull(),
            'content' => $cb::text(),
        ]);
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('news');
    }
}
```

Migration builder `$b` in the above manages database schema while the column builder `$cb` manages column types. Both allow using _abstract types_. When a migration is applied to a particular database, the abstract types will be translated into the corresponding database physical types and corresponding SQL to define them.

Methods available in migration builder belong to the following types:

- Raw queries
    - getDb — to get database connection instance.
    - execute — to execute raw SQL query.
- Data
    - insert / update / delete
    - batchInsert
    - upsert
- Tables and views
    - createTable / renameTable / dropTable
    - truncateTable
    - addCommentOnTable / dropCommentFromTable
    - createView / dropView
- Columns
    - addColumn / renameColumn / alterColumn / dropColumn
    - addCommentOnColumn / dropCommentFromColumn
- Keys and indexes
    - addPrimaryKey / dropPrimaryKey
    - addForeignKey / dropForeignKey
    - createIndex / dropIndex

Additionally, there's a `columnBuilder()` which is used to obtain a column builder as in example above. The builder has static methods that define various column types:

- Keys
    - primaryKey
    - smallPrimaryKey
    - bigPrimaryKey
    - uuidPrimaryKey
- Boolean
    - boolean
- Numbers
    - bit
    - tinyint
    - smallint
    - integer
    - bigint
    - flat
    - double
    - decimal
- Strings
    - char
    - string
    - text
- Date and time
    - timestamp
    - datetime
    - datetimeWithTimezone
    - time
    - timeWithTimezone
    - date
- Special types
    - money
    - binary
    - uuid
    - array
    - structured
    - json
- enum

All the above methods create a base type which could be adjusted with additional methods:

- null / notNull
- defaultValue
- unique
- scale / size / unsigned
- primaryKey / autoIncrement
- check
- comment
- computed
- extra
- reference

### Irreversible migrations

Not all migrations are reversible. For example, if the `up()` method deletes a row of a table, you may not be able to recover this row in the `down()` method. Sometimes, you may be just too lazy to implement the `down()`, because it is not very common to revert database migrations. In this case, you should implement `Yiisoft\Db\Migration\MigrationInterface` that has `up()` only.

### Transactional migrations

While performing complex DB migrations, it is important to ensure each migration to either succeed or fail as a whole so that the database can maintain integrity and consistency. To achieve this goal, it is recommended that you may enclose the DB operations of each migration in a transaction automatically by adding `TransactionalMigrationInterface` to `implements` of your migration.

As a result, if any operation in the `up()` or `down()` method fails, all prior operations will be rolled back automatically.

> Note: Not all DBMS support transactions. And some DB queries cannot be put into a transaction. For some examples, please refer to [implicit commit](https://dev.mysql.com/doc/refman/5.7/en/implicit-commit.html).

## Generating a migration

Instead of writing migrations by hand, the command provides a convenient way generate some of the code.

```bash
make shell
./yii migrate:create -- my_first_table --command=table --fields=name,example --table-comment=my_first_table
```

That would generate the following:

```php
<?php

declare(strict_types=1);

namespace App\Migration;

use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Db\Migration\TransactionalMigrationInterface;

/**
 * Handles the creation of table `my_first_table`.
 */
final class M251227095006CreateMyFirstTableTable implements RevertibleMigrationInterface, TransactionalMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $columnBuilder = $b->columnBuilder();

        $b->createTable('my_first_table', [
            'id' => $columnBuilder::primaryKey(),
            'name',
            'example',
        ]);

        $b->addCommentOnTable('my_first_table', 'my_first_table');
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('my_first_table');
    }
}
```

Commands available are:

- create - empty migration.
- table - creating a table. Use `--fields` specify a list of fields to use. Types could be specified as well such as `id:primaryKey,name:string:defaultValue("Alex"),user_id:integer:foreignKey,category_id2:integer:foreignKey(category id2)`.
- dropTable - dropping a table.
- addColumn - adding a column.
- dropColumn - dropping a column.
- junction - creating a junction table. Use `--and` specify a second table.

## Applying Migrations

To upgrade a database to its latest structure, you should apply all available new migrations using the following command:

```bash
./yii migrate:up
```

This command will list all migrations that have not been applied so far. If you confirm that you want to apply these migrations, it will run the `up()` method in every new migration class, one after another, in the order of their timestamp values. If any of the migrations fails, the command will quit without applying the rest of the migrations.

For each migration that has been successfully applied, the command will insert a row into a database table named `migration` to record the successful application of the migration. This will allow the migration tool to identify which migrations have been applied and which have not.

Sometimes, you may only want to apply one or a few new migrations, instead of all available migrations. You can do so by specifying the number of migrations that you want to apply when running the command. For example, the following command will try to apply the next three available migrations:

```bash
./yii migrate:up --limit=3
```

## Reverting Migrations

To revert (undo) one or multiple migrations that have been applied before, you can run the following command:

```bash
./yii migrate:down            # revert the most recently applied migration
./yii migrate:down --limit=3  # revert the most 3 recently applied migrations
./yii migrate:down --all      # revert all migrations
```

> Note: Not all migrations are reversible. Trying to revert such migrations will cause an error and stop the entire reverting process.

## Redoing Migrations

Redoing migrations means first reverting the specified migrations and then applying again. This can be done as follows:

```bash
./yii migrate:redo            # redo the last applied migration
./yii migrate:redo --limit=3  # redo the last 3 applied migrations
./yii migrate:redo --all      # redo all migrations
```

> Note: If a migration is not reversible, you will not be able to redo it.

## Listing Migrations

To list which migrations have been applied and which are not, you may use the following commands:

```bash
./yii migrate/history            # showing the last 10 applied migrations
./yii migrate:history --limit=5  # showing the last 5 applied migrations
./yii migrate:history --all      # showing all applied migrations

./yii migrate:new                # showing the first 10 new migrations
./yii migrate:new --limit=5      # showing the first 5 new migrations
./yii migrate:new --all          # showing all new migrations
```