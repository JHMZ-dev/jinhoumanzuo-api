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
class SignatureMiddleware implements MiddlewareInterface
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
            //判断是否开启ip限制
            $redis0 = ApplicationContext::getContainer()->get(RedisFactory::class)->get('db0');
            $check_ip_xianzhi = $redis0->get('check_ip_xianzhi');
            if($check_ip_xianzhi == 1)
            {
                //判断是否有ip批量访问
                $checkres = $this->check_ip($ip['0'],$UserAgent);
                if(!$checkres)
                {
                    return $this->response->json([
                        'code' => 503,
                        'msg' => 'service unavailable',
                    ]);
                }
            }
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
        $system = $this->request->header('system', '');
        if(!empty($system))
        {
            Context::set('system', $system);
        }
//        //获取header中的语言
//        $language = $this->request->header('language',1);
//        if($language == 1)
//        {
//            Context::set('language', 1);
//        }else{
//            Context::set('language', 2);
//        }
        //获取经度
        $longitude      = $this->request->header('longitude','');
        if(empty($longitude))
        {
            if(array_key_exists('longitude',$postArr))
            {
                $longitude = $postArr['longitude'];
            }
        }
        if(!empty($longitude))
        {
            //存入上下文
            Context::set('longitude', $longitude);
        }
        //获取纬度
        $latitude       = $this->request->header('latitude','');
        if(empty($latitude))
        {
            if(array_key_exists('latitude',$postArr))
            {
                $latitude = $postArr['latitude'];
            }
        }
        if(!empty($latitude))
        {
            //存入上下文
            Context::set('latitude', $latitude);
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
            'viewlogs_longitude'    => $longitude,
            'viewlogs_latitude'     => $latitude
        ];
        //写入请求日志
        $job = ApplicationContext::getContainer()->get(DriverFactory::class);
        $job->get('viewlogs')->push(new ViewLogs($data));
        //判断是否开启签名
        $redis0 = ApplicationContext::getContainer()->get(RedisFactory::class)->get('db0');
        $is_signature = $redis0->get('is_signature');
        //if($is_signature == 1 && !in_array($user_id,[669933]))
        if($is_signature == 1 && !in_array($user_id,[669933]))
        {
            $signature = $this->request->header('signature','');
//var_dump($fullUrl.':'.$signature);//请求的url+签名
            if(empty($signature))
            {
                if(array_key_exists('signature',$postArr))
                {
                    $signature = $postArr['signature'];
                }else{
                    return $this->response->json([
                        'code' => 1000,
                        'msg' => '非法请求',
                    ]);
                }
            }
//var_dump($fullUrl.':'.$this->signature($postArr));//api计算的签名
            if ($signature != $this->signature($postArr))
            {
                return $this->response->json([
                    'code' => 1000,
                    'msg' => '非法请求',
                ]);
            }
        }
        return $handler->handle($request);
    }
    /**
     * 生成签名
     * @param $datas
     * @return string
     */
    protected function signature($datas)
    {
        if(array_key_exists('signature',$datas))
        {
            unset($datas['signature']);
        }
        $redis0 = ApplicationContext::getContainer()->get(RedisFactory::class)->get('db0');
        $key = $redis0->get('set_key');
        $signature = '';
        ksort($datas);
        foreach ($datas as $paramName => $paramValue) {
            $signature .= $paramName . $paramValue;
        }
        $signature .= $key;
        return md5($signature);
    }

    //更新缓存用户信息
    protected function update_user_info($userInfo)
    {
        $redis1 = ApplicationContext::getContainer()->get(RedisFactory::class)->get('db1');
        //获取用户信息剩余时长
        $ttl = $redis1->ttl(strval($userInfo['user_id']));
        if($ttl && $ttl > 0)
        {
            //保存用户信息
            $redis1->set(strval($userInfo['user_id']),json_encode($userInfo));
            //设置过期时间
            $redis1->expire(strval($userInfo['user_id']),$ttl);
            //保存一下聊天头像
            $redis1->set($userInfo['user_id'].'_img',$userInfo['img'],$ttl);
            //保存一下昵称
            $redis1->set($userInfo['user_id'].'_nickname',$userInfo['nickname'],$ttl);
        }else{
            $redis0 = ApplicationContext::getContainer()->get(RedisFactory::class)->get('db0');
            //获取配置信息
            $day = $redis0->get('set_token_out_time');
            $tokenTime = 60 *60 *24 * $day;
            //保存用户信息
            $redis1->set(strval($userInfo['user_id']),json_encode($userInfo));
            //设置过期时间
            $redis1->expire(strval($userInfo['user_id']),$tokenTime);
            //保存一下聊天头像
            $redis1->set($userInfo['user_id'].'_img',$userInfo['img'],$tokenTime);
            //保存一下昵称
            $redis1->set($userInfo['user_id'].'_nickname',$userInfo['nickname'],$tokenTime);
        }
    }

    /**
     * 封ip
     * @param $ip
     * @param $UserAgent
     * @return bool
     */
    protected function check_ip($ip,$UserAgent)
    {
        //增加一次过滤
        $redis1 = ApplicationContext::getContainer()->get(RedisFactory::class)->get('db1');
        //判断该ip是否被封禁
        $feng = $redis1->get('ip_feng_'.$ip);
        if($feng == 2)
        {
            return false;
        }
        //判断该ip1秒内请求了多少次
        $name = 'ip_'.$ip.$UserAgent;
        $ci = $redis1->get($name);
        if($ci >=30)
        {
            //封ip 1小时
            $redis1->set('ip_feng_'.$ip,2,3600);
            return false;
        }
        $redis1->set($name,$ci+1,1);
        return true;
    }
}