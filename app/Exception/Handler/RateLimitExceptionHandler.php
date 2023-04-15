<?php
namespace App\Exception\Handler;

use Hyperf\Di\Annotation\Inject;
use Hyperf\ExceptionHandler\ExceptionHandler;
use Psr\Http\Message\ResponseInterface;
use Hyperf\RateLimit\Exception\RateLimitException;
use Throwable;

class RateLimitExceptionHandler extends  ExceptionHandler
{
    /**
     * @Inject
     *
     * @var \Hyperf\HttpServer\Contract\ResponseInterface as httpResponse
     */
    protected $httpResponse;

    public function handle(Throwable $throwable, ResponseInterface $response)
    {
        // 判断被捕获到的异常是希望被捕获的异常
        if ($throwable instanceof RateLimitException) {
            // 阻止异常冒泡
            $this->stopPropagation();
            return $this->httpResponse->json(['code' => 503, 'msg' => '请不要点这么快啦，我会受不的了啦']);
        }

        // 交给下一个异常处理器
        return $response;

        // 或者不做处理直接屏蔽异常
    }

    /**
     * 判断该异常处理器是否要对该异常进行处理
     */
    public function isValid(Throwable $throwable): bool
    {
        return true;
    }
}
