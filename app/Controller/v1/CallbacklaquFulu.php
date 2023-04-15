<?php

declare(strict_types=1);

namespace App\Controller\v1;

use App\Controller\XiaoController;
use App\Model\DsCinema;
use App\Model\DsCinemaCity;
use App\Model\DsCinemaCityChild;
use App\Model\DsCinemaSuozuo;
use App\Model\DsCinemaUserOrder;
use App\Model\DsCinemaVideo;
use App\Model\DsCinemaPaiqi;
use App\Model\DsCity;
use App\Model\DsCzHuafeiUserOrder;
use App\Model\DsErrorLog;
use App\Model\DsUser;
use App\Model\DsUserTongzheng;
use Hyperf\Guzzle\ClientFactory;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;

use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Annotation\Controller;

use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Annotation\Middlewares;
use App\Middleware\SignatureMiddleware;
use App\Middleware\UserMiddleware;
use Hyperf\Redis\RedisFactory;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Context;

/**
 * @package App\Controller\v1
 * @Controller(prefix="v1/callbacklaqufulu")
 */
class CallbacklaquFulu extends XiaoController
{


    private $AppSecret = '3dd46243a0c04465a8f59a0f0b6e7c8e';


    /**
     *
     * 福禄订单回调
     * @RequestMapping(path="callbacklaqu_fulu",methods="post")
     * @return string
     */
    public function callbacklaqu_fulu()
    {
var_dump('福禄回调');
var_dump(date('Y-m-d H:i:s',time()));
        $p = $this->request->post();
var_dump($p);
        if(!$p){
            DsErrorLog::add_log('福禄回调空了','空','福禄回调空了');
            return false;
        }
        if(empty($p['order_id'])){
            DsErrorLog::add_log('福禄回调order_id空了',json_encode($p),'福禄回调order_id空了');
            return false;
        }
        if(empty($p['customer_order_no'])){
            DsErrorLog::add_log('福禄回调customer_order_no空了',json_encode($p),'福禄回调customer_order_no空了');
            return false;
        }
        if(empty($p['sign'])){
            DsErrorLog::add_log('福禄回调sign空了',json_encode($p),'福禄回调sign空了');
            return false;
        }
        $sign = $p['sign'];
        unset($p['sign']);
        $v = $this->getSign($p);

        if($v != $sign){
            var_dump('福禄验签失败');
            return false;
        }
        var_dump('福禄验签ok');

        $order = DsCzHuafeiUserOrder::query()->where('customer_order_no',$p['customer_order_no'])->where('status',2)->first();
        if($order){
            if($p['order_status'] == 'success'){
                $o = DsCzHuafeiUserOrder::query()->where('customer_order_no',$p['customer_order_no'])->update([
                    'status'=> 1,
                    'order_id'=> $p['order_id'],
                    'charge_finish_time'=> $p['charge_finish_time'],
                    'recharge_description'=> $p['recharge_description'],
                ]);
                if(!$o){
                    DsErrorLog::add_log('福禄回调-成功-修改状态1失败',json_encode($p),'福禄回调-成功-修改状态1失败');
                }
            }else{
                $o = DsCzHuafeiUserOrder::query()->where('customer_order_no',$p['customer_order_no'])->update([
                    'status'=> 3,
                    'order_id'=> $p['order_id'],
                    'recharge_description'=> $p['recharge_description'],
                ]);
                if(!$o){
                    DsErrorLog::add_log('福禄回调-成功-修改状态1失败',json_encode($p),'福禄回调-成功-修改状态1失败');
                }
                $this->tui($order);
            }
        }else{
            DsErrorLog::add_log('福禄回调customer_order_no空了/或者已经成功',json_encode($p),'福禄回调customer_order_no空了/或者已经成功');
            return false;
        }

        return 'success';
    }

    /**
     * php签名方法
     */
    private function getSign($Parameters)
    {
        //签名步骤一：把字典json序列化
        $json = json_encode( $Parameters, 320 );
        //签名步骤二：转化为数组
        $jsonArr = $this->mb_str_split( $json );
        //签名步骤三：排序
        sort( $jsonArr );
        //签名步骤四：转化为字符串
        $string = implode( '', $jsonArr );
        //签名步骤五：在string后加入secret
        $string = $string . $this->AppSecret;
        //签名步骤六：MD5加密
        $result_ = strtolower(md5($string));
        return $result_;
    }
    /**
     * 可将字符串中中文拆分成字符数组
     */
    private function mb_str_split($str){
        return preg_split('/(?<!^)(?!$)/u', $str );
    }

    //退
    protected function tui($order){
        if($order['user_id']){
            $re = DsUserTongzheng::add_tongzheng($order['user_id'],$order['price_tongzheng'],'退回充值');
            if(!$re){
                DsErrorLog::add_log('福禄回调-成功-退回修改失败',json_encode($order),'福禄回调-成功-退回修改失败');
            }
        }
    }

}
