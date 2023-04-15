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
use App\Model\DsErrorLog;
use App\Model\DsUser;
use App\Model\DsUserTongzheng;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;

use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Annotation\Controller;

use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Annotation\Middlewares;
use App\Middleware\SignatureMiddleware;
use App\Middleware\UserMiddleware;
use Hyperf\Utils\Context;

/**
 * @package App\Controller\v1
 * @Controller(prefix="v1/callbacklaqu")
 */
class Callbacklaqu extends XiaoController
{
    protected function makeSign($data, $apikey)
    {
        ksort($data);
        $sign = "";
        foreach ($data as $key => $val) {
            if ($key == "sign" || $key == "vendor" || $key == "ts") {
                continue;
            }
            if (is_array($val) || is_object($val)) {
                $sign .= $key;
            } else {
                if (is_null($val)) {
                    $val = '';
                }
                $sign .= $val;
            }
        }
        if ($sign != "") {
            $sign = md5(strtolower($sign));
        }
        $sign .= $data["ts"] . $data["vendor"] . $apikey;
        return strtolower(md5(strtolower($sign)));
    }

    public function callbacklaqu_dytest(){

        //验证签名
//            $p2['vendor'] = $p['vendor'];//追加回调的vendor
//            $p2['ts'] = $p['ts'];//追加回调的ts
//            $re = $this->makeSign($p2,$apikey);//加密
//            if($p['sign'] == $re){//将回调的sign和加密后的参数 对比是否验签成功
//                var_dump('ok');
//            }

    }

    /**
     * @RequestMapping(path="callbacklaqu_dy",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function callbacklaqu_dy()
    {
        $apikey = 'ccd78abcd7ec84879ee0598fb813a6c7';
        $vendor = '00274';

        $p = $this->request->post();

        $p1 = $p['data'];//将回调的data取出
        if(!$p1){
            return false;
        }
        if(!$p1['orderNumber']){
            return false;
        }
        $or = DsCinemaUserOrder::query()->where('orderNumber',$p1['orderNumber'])->first();
        if(!$or){
            DsErrorLog::add_log('电影回调-订单查询为空',json_encode($p),$p1['orderNumber']);
            return false;
        }
        if($or['is_chuli'] > 0){
            return false;
        }
        if($p['vendor'] != $vendor){
            return false;
        }

        //先设置订单为已处理 防止重复处理
        DsCinemaUserOrder::query()->where('orderNumber',$p1['orderNumber'])->update(['is_chuli' => 1]);

        if($p['code'] == '0'){
            //var_dump('电影回调进了,渠道号验证成功');

            //验证签名 begin
            $re = $this->makeSign($p,$apikey);//加密
            if($p['sign'] == $re){//将回调的sign和加密后的参数 对比是否验签成功
                //var_dump('============ok');
            }else{
                //var_dump('============no');
                return false;
            }
            //end

            if($p1['status'] == '1'){//订单状态:-1报价中,0出票中,1出票成功,2出票失败 ,3订单关闭
                if(is_array($p1['ticketList']) && $p1['ticketList']){
                    $re = DsCinemaUserOrder::query()->where('orderNumber',$p1['orderNumber'])->update([
                        'status' => 1,
                        'erweima' => json_encode($p1['ticketList']),
                    ]);
                    if(!$re){
                        DsErrorLog::add_log('电影回调-订单出票信息修改失败',json_encode($p),$p1['orderNumber']);
                    }
                }else{
                    DsErrorLog::add_log('电影回调-订单出票数字空了',json_encode($p),$p1['orderNumber']);
                }
            }else if($p1['status'] == '-1'){
                DsCinemaUserOrder::query()->where('orderNumber',$p1['orderNumber'])->update([
                    'status' => 1,
                    'beizhu' => json_encode($p1),
                ]);
            }else if($p1['status'] == '0'){
                DsCinemaUserOrder::query()->where('orderNumber',$p1['orderNumber'])->update([
                    'status' => 0,
                    'beizhu' => json_encode($p1),
                ]);
            }else if($p1['status'] == '2'){
                $this->tuipiao($or,$p,$p1,2);
            }else if($p1['status'] == '3'){
                $this->tuipiao($or,$p,$p1,3);
            }
            return $this->response->json(['code' => 0, 'msg' => '处理成功']);
        }else{
            return $this->response->json(['code' => 0, 'msg' => '状态失败']);
        }
    }

    //退票
    protected function tuipiao($or,$p,$p1,$status){
        //DsErrorLog::add_log('电影回调-出票失败-退款',json_encode($p),$or['user_id']);
        if($or['user_id']){
            $message = '退票';
            if(!$p['message']){
                $message = $p['message'];
            }
            $re = DsCinemaUserOrder::query()->where('orderNumber',$p1['orderNumber'])->update([
                'status' => $status,
                'beizhu' => $message,
            ]);
            if(!$re){
                DsErrorLog::add_log('电影回调-订单未出票信息修改失败',json_encode($p),$p1['orderNumber']);
            }
            $price = $or['yuanjia_tz'];
            $r2 = DsUserTongzheng::add_tongzheng($or['user_id'],$price,'购票失败退回：'.$or['cinema_order_id']);
            if(empty($r2)){
                DsErrorLog::add_log('购票失败退钱失败',json_encode($or),'购票失败退钱失败:'.$or['user_id']);
            }
        }
    }

}
