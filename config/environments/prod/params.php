<?php

declare(strict_types=1);

return [
    'db' => [
        'dsn' => getenv('DB_DSN'),
        'username' => getenv('DB_USER'),
        'password' => getenv('DB_PASSWORD'),
        'charset'  => 'utf8mb4',
    ],

    'redis' => [
        'dsn' => getenv('REDIS_DSN'),
    ],
];
