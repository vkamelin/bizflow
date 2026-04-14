<?php

declare(strict_types=1);

return [
    'db' => [
        'dsn' => getenv('DB_DSN') ?: 'mysql:host=localhost;port=3306;dbname=app_test;charset=utf8mb4',
        'username' => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: 'secret',
        'charset'  => 'utf8mb4',
    ],

    'redis' => [
        'dsn' => getenv('REDIS_DSN') ?: 'redis://localhost:6379',
    ],
];
