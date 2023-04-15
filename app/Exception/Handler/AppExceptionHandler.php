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

namespace App\Exception\Handler;

use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\ExceptionHandler\ExceptionHandler;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class AppExceptionHandler extends ExceptionHandler
{
    /**
     * @var StdoutLoggerInterface
     */
    protected $logger;

    public function __construct(StdoutLoggerInterface $logger)
    {
        $this->logger = $logger;
    }

//    public function handle2(Throwable $throwable, ResponseInterface $response)
//    {
//        $this->logger->error(sprintf('%s[%s] in %s', $throwable->getMessage(), $throwable->getLine(), $throwable->getFile()));
//        $this->logger->error($throwable->getTraceAsString());
//        return $response->withStatus(500)->withBody(new SwooleStream('Internal Server Error.'));
//    }
    public function handle(Throwable $throwable, ResponseInterface $response)
    {
        $data = json_encode([
            'code'  => $throwable->getCode(),
//            'file'  => $throwable->getFile(),
//            'Line'  => $throwable->getLine(),
//            'getPrevious'  => $throwable->getPrevious(),
//            'getTraceAsString'  => $throwable->getTraceAsString(),
            'msg'   => $throwable->getMessage()
        ], JSON_UNESCAPED_UNICODE);
        return $response->withHeader('Content-Type','application/json;charset=utf-8')->withStatus(200)->withBody(new SwooleStream($data));
    }
    public function isValid(Throwable $throwable): bool
    {
        return true;
    }
}
