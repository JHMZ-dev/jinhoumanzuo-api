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

namespace App\Controller;

use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Utils\Context;
use Psr\Container\ContainerInterface;

abstract class AbstractController
{
    /**
     * @Inject
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @Inject
     * @var RequestInterface
     */
    protected $request;

    /**
     * @Inject
     * @var ResponseInterface
     */
    protected $response;

    /**
     * Ajax returns with data
     * @param string $msg
     * @param array $result
     * @param int $code
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function withResponse(string $msg, array $result,int $code = 200)
    {
        return $this->response->json(compact('code',  'msg','result'));
    }
    /**
     * Ajax returns with data
     * @param string $msg
     * @param string $result
     * @param int $code
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function withResponseString(string $msg, string $result,int $code = 200)
    {
        return $this->response->json(compact('code',  'msg','result'));
    }
    /**
     * Ajax returns success
     * @param string $msg
     * @param int $code
     * @param array $result
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function withSuccess(string $msg,int $code = 200, $result = [])
    {
        return $this->response->json(compact('code', 'msg','result'));
    }

    /**
     * @param string $message
     * @param int $code
     * @param null $result
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function withSuccess_ty(string $message,int $code = 200, $result = null)
    {
        return $this->response->json(compact('code', 'message','result'));
    }

    /**
     * @param string $msg
     * @param int $code
     * @param $result
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function withSuccess_ty_null(string $msg,int $code = 200, $result = null)
    {
        return $this->response->json(compact('msg','result','code'));
    }

    /**
     * @param string $message
     * @param int $code
     * @param null $result
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function withError_ty( string $message,int $code = 10001, $result = null)
    {
        return $this->response->json(compact('code', 'message','result'));
    }
    /**
     * 广告返回信息
     * @param bool $msg
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function isValid(bool $msg)
    {
        return $this->response->json(['isValid'=>$msg]);
    }

    /**
     * Ajax returns error
     * @param string $msg
     * @param int $code
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function withError( string $msg,int $code = 10001)
    {
        return $this->response->json(compact('code', 'msg'));
    }

}
