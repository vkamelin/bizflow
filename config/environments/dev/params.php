<?php

declare(strict_types=1);

return [
    'traceLink' => 'phpstorm://open?url=file://{file}&line={line}',

    'db' => [
        'dsn' => getenv('DB_DSN') ?: 'mysql:host=localhost;port=3306;dbname=app;charset=utf8mb4',
        'username' => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: 'secret',
    ],

    'redis' => [
        'dsn' => getenv('REDIS_DSN') ?: 'redis://localhost:6379',
    ],
];
