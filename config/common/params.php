<?php

declare(strict_types=1);

use App\Shared\ApplicationParams;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Assets\AssetManager;
use Yiisoft\Definitions\Reference;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Yii\View\Renderer\CsrfViewInjection;

return [
    'application' => require __DIR__ . '/application.php',

    'yiisoft/aliases' => [
        'aliases' => require __DIR__ . '/aliases.php',
    ],

    'yiisoft/view' => [
        'basePath' => null,
        'parameters' => [
            'assetManager' => Reference::to(AssetManager::class),
            'applicationParams' => Reference::to(ApplicationParams::class),
            'aliases' => Reference::to(Aliases::class),
            'urlGenerator' => Reference::to(UrlGeneratorInterface::class),
            'currentRoute' => Reference::to(CurrentRoute::class),
        ],
    ],

    'yiisoft/yii-view-renderer' => [
        'viewPath' => null,
        'layout' => '@src/Web/Shared/Layout/Main/layout.php',
        'injections' => [
            Reference::to(CsrfViewInjection::class),
        ],
    ],

    'yiisoft/db-migration' => [
        'newMigrationNamespace' => 'App\\Database\\Migration',
        'sourceNamespaces' => ['App\\Database\\Migration'],
        'sourcePaths' => [
            __DIR__ . '/../../vendor/yiisoft/rbac-db/migrations/items',
            __DIR__ . '/../../vendor/yiisoft/rbac-db/migrations/assignments',
        ],
    ],

    'yiisoft/db' => [
        'schema-cache' => [
            'enabled' => true,
        ],
    ],

    'db' => [
        'dsn' => getenv('DB_DSN') ?: 'mysql:host=localhost;port=3306;dbname=app',
        'username' => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: 'secret',
        'charset' => 'utf8mb4',
    ],
];
