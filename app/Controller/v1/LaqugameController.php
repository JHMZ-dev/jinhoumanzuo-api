<?php

declare(strict_types=1);

namespace App\Controller\v1;

use App\Controller\XiaoController;
use App\Model\DsCinema;
use App\Model\DsCinemaCity;
use App\Model\DsCinemaCityChild;
use App\Model\DsCinemaUserOrder;
use App\Model\DsCinemaVideo;
use App\Model\DsCinemaPaiqi;
use App\Model\DsErrorLog;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;

use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Annotation\Middlewares;
use App\Middleware\SignatureMiddleware;
use App\Middleware\UserMiddleware;
use Hyperf\Utils\Context;

use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Annotation\Controller;


/**
 * 获取游戏url
 * @Middlewares({
 *     @Middleware(SignatureMiddleware::class),
 *     @Middleware(UserMiddleware::class)
 * })
 * @package App\Controller\v1
 * @Controller(prefix="v1/laqugame")
 */
class LaqugameController extends XiaoController
{
    protected $qudao = '20580';//渠道号
    protected $game_url = 'http://www.shandw.com';
    protected $apikey = '08a0eeca3dc94b799d883cda854609ce';

    /**
     * 获取链接
     * @RequestMapping(path="get_game_url",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Swoole\Exception
     */
    public function get_game_url(){
        $user_id = Context::get('user_id');
        $userInfo = Context::get('userInfo');

        $sign = 'channel=' . $this->qudao . '&openid=' . $user_id . '&time=' . time() . '&nick=' . $user_id . '&avatar=https://jinhoumanzuo.oss-cn-hangzhou.aliyuncs.com/logo.png&sex=0&phone=' . $userInfo['mobile'];
        $sign_apikey = $this->apikey;
        $sign2 = md5($sign . $sign_apikey);
        $url = $this->game_url . '/auth/?' . $sign . '&sign=' . $sign2;
        return $this->withSuccess('ok',200,$url);
    }

}
