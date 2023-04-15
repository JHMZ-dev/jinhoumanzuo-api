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

use Hyperf\Server\Server;
use Hyperf\Server\SwooleEvent;

return [
    'mode' => SWOOLE_PROCESS,
    'servers' => [
        [
            'name' => 'http',
            'type' => Server::SERVER_HTTP,
            'host' => '0.0.0.0',
            'port' => 9510,
            'sock_type' => SWOOLE_SOCK_TCP,
            'callbacks' => [
                SwooleEvent::ON_REQUEST => [Hyperf\HttpServer\Server::class, 'onRequest'],
            ]
        ],
        [
            'name' => 'ws',
            'type' => Server::SERVER_WEBSOCKET,
            'host' => '0.0.0.0',
            'port' => 9512,
            'sock_type' => SWOOLE_SOCK_TCP,
            'callbacks' => [
                SwooleEvent::ON_HAND_SHAKE => [Hyperf\WebSocketServer\Server::class, 'onHandShake'],
                SwooleEvent::ON_MESSAGE => [Hyperf\WebSocketServer\Server::class, 'onMessage'],
                SwooleEvent::ON_CLOSE => [Hyperf\WebSocketServer\Server::class, 'onClose'],
            ],
        ],
    ],
    'settings' => [
        'enable_coroutine' => true, // 开启内置协程
        'worker_num' => swoole_cpu_num() *2, // 设置启动的 Worker 进程数
        'pid_file' => BASE_PATH . '/runtime/hyperf.pid', // master 进程的 PID
        'open_tcp_nodelay' => true, // TCP 连接发送数据时会关闭 Nagle 合并算法，立即发往客户端连接
        'max_coroutine' => 100000, // 设置当前工作进程最大协程数量
        'open_http2_protocol' => true, // 启用 HTTP2 协议解析
        //'max_request' => 100000, // 设置 worker 进程的最大任务数
        'socket_buffer_size' => 10 * 1024 * 1024, // 配置客户端连接的缓存区长度
        'package_max_length' => 20 * 1024 * 1024, // 上传大小限制
        'buffer_output_size' => 20 *1024 *1024,
//        'document_root' => BASE_PATH . '/public',
//        'static_handler_locations' => ['/'],
//        'enable_static_handler' => true,
    ],
    'callbacks' => [
        SwooleEvent::ON_BEFORE_START => [Hyperf\Framework\Bootstrap\ServerStartCallback::class, 'beforeStart'],
        SwooleEvent::ON_WORKER_START => [Hyperf\Framework\Bootstrap\WorkerStartCallback::class, 'onWorkerStart'],
        SwooleEvent::ON_PIPE_MESSAGE => [Hyperf\Framework\Bootstrap\PipeMessageCallback::class, 'onPipeMessage'],
    ],
];
