<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://doc.hyperf.io
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf-cloud/hyperf/blob/master/LICENSE
 */

return [
    'default' => [
        'driver' => Hyperf\AsyncQueue\Driver\RedisDriver::class,
        'channel' => 'queue',
        'timeout' => 2,
        'retry_seconds' => 5,
        'handle_timeout' => 60,
        'processes' => 5,
        'concurrent' => [
            'limit' => 5,
        ],
    ],
    'register' => [
        'driver' => Hyperf\AsyncQueue\Driver\RedisDriver::class,
        'channel' => 'register',
        'timeout' => 2,
        'retry_seconds' => 5,
        'handle_timeout' => 1800,
        'processes' => 5,
        'concurrent' => [
            'limit' => 5,
        ],
    ],
    'ali_cms'=>[
        'driver' => Hyperf\AsyncQueue\Driver\RedisDriver::class,
        'channel' => 'ali_cms',
        'timeout' => 2,
        'retry_seconds' => 5,
        'handle_timeout' => 60,
        'processes' => 5,
        'concurrent' => [
            'limit' => 5,
        ],
    ],
    'viewlogs' => [
        'driver' => Hyperf\AsyncQueue\Driver\RedisDriver::class,
        'channel' => 'viewlogs',
        'timeout' => 2,
        'retry_seconds' => 5,
        'handle_timeout' => 60,
        'processes' => 5,
        'concurrent' => [
            'limit' => 5,
        ],
    ],
    'update' => [
        'driver' => Hyperf\AsyncQueue\Driver\RedisDriver::class,
        'channel' => 'queue',
        'timeout' => 2,
        'retry_seconds' => 5,
        'handle_timeout' => 600,
        'processes' => 5,
        'concurrent' => [
            'limit' => 5,
        ],
    ],
    'async' => [
        'driver' => Hyperf\AsyncQueue\Driver\RedisDriver::class,
        'channel' => 'async',
        'timeout' => 2,
        'retry_seconds' => 5,
        'handle_timeout' => 1800,
        'processes' => 5,
        'concurrent' => [
            'limit' => 5,
        ],
    ],
    'test' => [
        'driver' => Hyperf\AsyncQueue\Driver\RedisDriver::class,
        'channel' => 'queue',
        'timeout' => 2,
        'retry_seconds' => 5,
        'handle_timeout' => 1800,
        'processes' => 1,
    ],
];
