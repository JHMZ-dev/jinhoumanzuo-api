<?php

declare(strict_types=1);

namespace App\Middleware;


use App\Job\ViewLogs;
use App\Model\DsUser;
use App\Model\DsUserToken;
use App\Model\DsUserWebToken;
use Hyperf\AsyncQueue\Driver\DriverFactory;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Hyperf\Redis\RedisFactory;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Context;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class WebMiddleware
 * @package App\Middleware
 */
class WebMiddleware implements MiddlewareInterface
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    protected $request;

    protected $response;

    public function __construct(ContainerInterface $container,HttpResponse $response, RequestInterface $request)
    {
        $this->container = $container;
        $this->response  = $response;
        $this->request   = $request;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {

        //获取所有post数据
        $postArr = $this->request->post();
        $json           = json_encode($postArr);
        $fullUrl        = $this->request->fullUrl();
        //获取浏览器标识
        $UserAgent      = $this->request->header('User-Agent');
        //获取请求IP
        $for            = $this->request->header('X-Forwarded-For');
        //转换ip
        if(!empty($for))
        {
            $ip             = explode(',',$for);
        }else{
            $ip             = ['0' =>'' ];
        }
        if(!empty($ip['0']))
        {
            //存入上下文
            Context::set('ip', $ip['0']);
        }

        //获取header中的token
        $token = $this->request->header('token','');
        if(empty($token))
        {
            if(array_key_exists('token',$postArr))
            {
                //读取post中的token
                $token = $postArr['token'];
            }
        }
        $user_id = 0;
        //查找token信息
        if(!empty($token))
        {
            $uid = DsUserToken::query()->where('token',$token)->value('user_id');
            if(!$uid)
            {
                $uid = DsUserWebToken::query()->where('token',$token)->value('user_id');
            }
            if($uid)
            {
                $info = DsUser::query()->where('user_id',$uid)->first();
                if(!empty($info))
                {
                    $userInfo = $info->toArray();
                    //存入上下文
                    Context::set('userInfo', $userInfo);
                    Context::set('user_id', strval($userInfo['user_id']));
                    Context::set('token', $token);
                    $user_id = $userInfo['user_id'];
                }
            }
        }

        $data = [
            'user_id'               => $user_id,
            'viewlogs_url'          => $fullUrl,
            'viewlogs_datas'        => $json,
            'viewlogs_time'         => time(),
            'viewlogs_ip'           => $ip['0'],
            'viewlogs_useragent'    => $UserAgent,
            'viewlogs_longitude'    => '',
            'viewlogs_latitude'     => ''
        ];
        //写入请求日志
        $job = ApplicationContext::getContainer()->get(DriverFactory::class);
        $job->get('viewlogs')->push(new ViewLogs($data));

        return $handler->handle($request);
    }
}