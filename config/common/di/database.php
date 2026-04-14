<?php

declare(strict_types=1);

use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Mysql\Connection;
use Yiisoft\Db\Mysql\Driver;

/**
 * @psalm-var array{
 *     db: array{
 *         dsn: string,
 *         username: string,
 *         password: string,
 *         charset: string
 *     }
 * } $params
 */

/** @var array $params */

return [
    ConnectionInterface::class => [
        'class' => Connection::class,
        '__construct()' => [
            'driver' => new Driver(
                $params['db']['dsn'],
                $params['db']['username'],
                $params['db']['password'],
                [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                ]
            ),
            'charset' => 'utf8',
        ],
    ],
];
