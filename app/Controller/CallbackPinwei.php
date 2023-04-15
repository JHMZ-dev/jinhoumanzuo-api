<?php

declare(strict_types=1);

namespace App\Controller;

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
 * @package App\Controller
 * @Controller(prefix="callbackpinwei")
 */
class CallbackPinwei extends XiaoController
{
    private $mchId_pw = '2360000130320298';
    private $apiKey_pw = 'b39E0AeDc457090EE8A2e7b60aB5Bb6F';

    /**
     *
     * @RequestMapping(path="callbacklaqu_pinwei",methods="post")
     * @return false|string
     */
    public function callbacklaqu_pinwei()
    {
//        var_dump('品味充值回调');
//        var_dump(date('Y-m-d H:i:s',time()));
        $p = $this->request->post();

        if(!$p){
            DsErrorLog::add_log('充值回调空了','空','回调空了');
            return false;
        }
        if(empty($p['mchId'])){
            DsErrorLog::add_log('回调mchId空了',json_encode($p),'回调mchId空了');
            return false;
        }
        if(empty($p['order_sn'])){
            DsErrorLog::add_log('回调order_sn空了',json_encode($p),'回调order_sn空了');
            return false;
        }
        if(empty($p['out_order_sn'])){
            DsErrorLog::add_log('回调out_order_sn空了',json_encode($p),'回调out_order_sn空了');
            return false;
        }
        if(empty($p['order_state'])){
            DsErrorLog::add_log('回调order_state空了',json_encode($p),'回调order_state空了');
            return false;
        }
        if(empty($p['sign'])){
            DsErrorLog::add_log('回调sign空了',json_encode($p),'回调sign空了');
            return false;
        }
        if($p['mchId'] != $this->mchId_pw){
            DsErrorLog::add_log('回调商户号不匹配',json_encode($p),'回调商户号不匹配');
            return false;
        }

        $sign = $p['sign'];
        unset($p['sign']);
        $v = $this->getSign($p);

        if($v != $sign){
            var_dump('验签失败');
            return false;
        }

        $order = DsCzHuafeiUserOrder::query()->where('customer_order_no',$p['out_order_sn'])->where('status',2)->first();
        if($order){
            if($p['order_state'] == 'SUCCESS'){
                $o = DsCzHuafeiUserOrder::query()->where('customer_order_no',$p['out_order_sn'])->update([
                    'status'=> 1,
                    'charge_finish_time'=> $p['datetime'],
                    'recharge_description'=> $p['order_msg'],
                ]);
                if(!$o){
                    DsErrorLog::add_log('回调-成功-修改状态1失败',json_encode($p),'回调-成功-修改状态1失败');
                }
                return 'SUCCESS';
            }else if($p['order_state'] == 'WAIT'){
                DsCzHuafeiUserOrder::query()->where('customer_order_no',$p['out_order_sn'])->update([
                    'status'=> 2,
                    'recharge_description'=> $p['order_msg'],
                ]);
            }else{
                $o = DsCzHuafeiUserOrder::query()->where('customer_order_no',$p['out_order_sn'])->update([
                    'status'=> 3,
                    'recharge_description'=> $p['order_msg'],
                ]);
                if(!$o){
                    DsErrorLog::add_log('回调-成功-修改状态3失败',json_encode($p),'回调-成功-修改状态3失败');
                }else{
                    $this->tui($order);
                }
                return 'SUCCESS';
            }

        }else{
            //DsErrorLog::add_log('回调查询订单空了/或者已经处理',json_encode($p),'回调查询订单空了/或者已经处理');
            return false;
        }
    }

    /**
     * @param $params
     * @return string
     */
    protected function getSign($params){
        //第一步、参数名ASCII码从小到大排序（字典序），区分大小写，参数为空也参与签名。
        ksort($params);
        //第二步、将排序好的参数使用URI参数的方式拼接密钥，得到字符串 waitSignParamsStr。
        $waitSignParamsStr = http_build_query($params);
        //第三步、使用 '&key=apiKey’方式拼接到waitSignParamsStr字符结尾，得到字符串waitSign。
        $waitSign = $waitSignParamsStr.'&key='.$this->apiKey_pw;
        // 此时$waitSign = 'appid=WBSGTRTMR6&key=D5W7AsARzGWAiznSNKnjqeg61cU44w51'
        //第四步、对waitSign字符串MD5摘要，并转换为全大写。
        $sign = strtoupper(md5($waitSign));
        //此时$sign = '2323481C19EDAE9560705A321072EBF0';
        return $sign;
    }

    //退
    protected function tui($order){
        if($order['user_id']){
            $re = DsUserTongzheng::add_tongzheng($order['user_id'],$order['price_tongzheng'],'退回充值'.$order['cz_huafei_user_order_id']);
            if(!$re){
                DsErrorLog::add_log('回调-成功-退回修改失败',json_encode($order),'回调-成功-退回修改失败');
            }
        }
    }

}
