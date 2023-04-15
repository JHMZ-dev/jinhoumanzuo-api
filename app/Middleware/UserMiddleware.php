<?php

declare(strict_types=1);

namespace App\Middleware;

use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Hyperf\Utils\Context;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class UserMiddleware
 * @package App\Middleware
 */
class UserMiddleware implements MiddlewareInterface
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    protected $request;

    protected $response;

    public function __construct(ContainerInterface $container, HttpResponse $response, RequestInterface $request)
    {
        $this->container = $container;
        $this->response = $response;
        $this->request = $request;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        //读取上下文中的token
        $userInfo = Context::get('userInfo');
        if(!$userInfo)
        {
            return $this->response->json([
                'code' => 1001,
                'msg' => '登录信息已过期，请重新登录',
            ]);
        }
        //判断是否被禁止登录
        if($userInfo['login_status'] == 1)
        {
            return $this->response->json([
                'code' => 1004,
                'msg' => '您的账号安全存在风险已被限制登陆,请联系客服核实身份信息后解除!',
            ]);
        }
        //判断是否被禁止登录
        if($userInfo['login_status'] == 2)
        {
            return $this->response->json([
                'code' => 1004,
                'msg' => '该账号已注销',
            ]);
        }
        return $handler->handle($request);
    }
}