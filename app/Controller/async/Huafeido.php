<?php declare(strict_types=1);

namespace App\Controller\async;

use App\Model\Chuli;
use App\Model\DsCzHuafeiUserOrder;
use App\Model\DsErrorLog;
use App\Model\DsUser;
use App\Model\DsUserTongzheng;
use Hyperf\Guzzle\ClientFactory;
use Hyperf\Redis\RedisFactory;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Context;
use Swoole\Exception;

/**
 * 话费
 * @package App\Controller
 */
class Huafeido
{
    private $fulu_host = '';//沙箱环境
    private $AppKey = '';
    private $AppSecret = '';
    private $version = '';
    private $format = '';
    private $charset = '';
    private $sign_type = '';
    private $app_auth_token = '';

    protected $redis2;
    protected $http;

    public function __construct()
    {
        // 配置参数 正式 福禄社区
        $this->fulu_host = 'http://openapi.fulu.com/api/getway';//正式环境
        $this->AppKey = 'MggQBHn0kC3BBEizoJlh1Et2INmvKYxSUiYpPEWbpAqFQ0iM6nGgB0kbzZT6Rkhg';
        $this->AppSecret = '3dd46243a0c04465a8f59a0f0b6e7c8e';
        $this->version = '2.0';
        $this->format = 'json';
        $this->charset = 'utf-8';
        $this->sign_type = 'md5';
        $this->app_auth_token = "";

        //配置 品味充值
        $this->url_pw = 'http://api.xiaogaokeji.com';
        $this->mchId_pw = '2360000130320298';
        $this->apiKey_pw = 'b39E0AeDc457090EE8A2e7b60aB5Bb6F';

        $this->container = ApplicationContext::getContainer();
        $this->redis2 = $this->container->get(RedisFactory::class)->get('db2');
        $this->http   = ApplicationContext::getContainer()->get(ClientFactory::class)->create();
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

    //话费下单
    public function huafei_add($mobile,$order_id,$user_id,$money){
        $res = DsCzHuafeiUserOrder::query()->where('cz_huafei_user_order_id',$order_id)->where('user_id',$user_id)->first();
        if($res){

            $url = 'fulu.order.mobile.add';
            $r_c = [
                'app_key' => $this->AppKey,
                'method' => $url,
                'timestamp' => date('Y-m-d H:i:s',time()),
                'version' => $this->version,
                'format' => $this->format,
                'charset' => $this->charset,
                'sign_type' => $this->sign_type,
                'app_auth_token' => $this->app_auth_token,
            ];
            $r =[
                'charge_phone' =>   $mobile,
                'charge_value' =>   $money,
                'customer_order_no' =>   $res['customer_order_no'],
            ];
            $r_c['biz_content'] = json_encode($r);
            $sign = $this->getSign($r_c);
            $r_c['sign'] = $sign;

            try {
                $res_info = $this->http->post($this->fulu_host, ['headers' =>['Content-Type' => 'application/json'], 'json' => $r_c])->getBody()->getContents();
                if(empty($res_info))
                {
                    if($res['price_tongzheng']){
                        $fan = DsUserTongzheng::add_tongzheng($user_id,$res['price_tongzheng'],'充值失败退回');
                        if(!$fan){
                            DsErrorLog::add_log('充值失败退回失败！',json_encode($r_c),'充值失败退回失败');
                        }
                    }
                    DsErrorLog::add_log('话费直充返回空',json_encode($r_c),'话费直充返回空');
                }else {
                    $re = json_decode($res_info, true);
                    if(empty($re)){
                        if($res['price_tongzheng']){
                            $fan = DsUserTongzheng::add_tongzheng($user_id,$res['price_tongzheng'],'充值失败退回');
                            if(!$fan){
                                DsErrorLog::add_log('充值失败退回失败！',json_encode($r_c),'充值失败退回失败');
                            }
                        }
                        DsErrorLog::add_log('话费直充解析空',json_encode($r_c),'话费直充解析空');
                    }
                    if($re['code'] == '0'){

//                        $rok = DsCzHuafeiUserOrder::query()->where('cz_huafei_user_order_id',$order_id)->where('user_id',$user_id)->update([
//                           'status' => 1,
//                        ]);
//                        if(!$rok){
//                            DsErrorLog::add_log('充值成功修改状态失败',json_encode($r_c),'充值成功修改状态失败');
//                        }
                    }else{
                        DsErrorLog::add_log('充值失败',json_encode($r_c),$re['message']);
                        if($res['price_tongzheng']){
                            $fan = DsUserTongzheng::add_tongzheng($user_id,$res['price_tongzheng'],'充值失败退回');
                            if(!$fan){
                                DsErrorLog::add_log('充值失败退回失败！',json_encode($r_c),'充值失败退回失败');
                            }
                        }
                    }
                }
            }catch(\Exception $exception) {
                if($res['price_tongzheng']){
                    $fan = DsUserTongzheng::add_tongzheng($user_id,$res['price_tongzheng'],'充值失败退回');
                    if(!$fan){
                        DsErrorLog::add_log('充值失败退回失败',json_encode($r_c),'充值失败退回失败');
                    }
                }
            }
        }else{
            DsErrorLog::add_log('话费直充-订单不存在',$order_id,'话费直充-订单不存在');
        }
        return true;
    }

    //直充下单通用
    public function zhichong_add($mobile,$order_id,$user_id,$money){
        $res = DsCzHuafeiUserOrder::query()->where('cz_huafei_user_order_id',$order_id)->where('user_id',$user_id)->first();
        if($res){

            $url = 'fulu.order.direct.add';
            $r_c = [
                'app_key' => $this->AppKey,
                'method' => $url,
                'timestamp' => date('Y-m-d H:i:s',time()),
                'version' => $this->version,
                'format' => $this->format,
                'charset' => $this->charset,
                'sign_type' => $this->sign_type,
                'app_auth_token' => $this->app_auth_token,
            ];
            $r =[
                'product_id' =>   $res['product_id'],
                'customer_order_no' =>   $res['customer_order_no'],
                'charge_account' =>   $mobile,
                'buy_num' =>   1,
            ];
            $r_c['biz_content'] = json_encode($r);
            $sign = $this->getSign($r_c);
            $r_c['sign'] = $sign;

            try {
                $res_info = $this->http->post($this->fulu_host, ['headers' =>['Content-Type' => 'application/json'], 'json' => $r_c])->getBody()->getContents();
                if(empty($res_info))
                {
                    if($res['price_tongzheng']){
                        $fan = DsUserTongzheng::add_tongzheng($user_id,$res['price_tongzheng'],'充值失败退回');
                        if(!$fan){
                            DsErrorLog::add_log('充值失败退回失败！',json_encode($r_c),'充值失败退回失败');
                        }
                    }
                    DsErrorLog::add_log('直充返回空',json_encode($r_c),'直充返回空');
                }else {
                    $re = json_decode($res_info, true);
                    if(empty($re)){
                        if($res['price_tongzheng']){
                            $fan = DsUserTongzheng::add_tongzheng($user_id,$res['price_tongzheng'],'充值失败退回');
                            if(!$fan){
                                DsErrorLog::add_log('充值失败退回失败！',json_encode($r_c),'充值失败退回失败');
                            }
                        }
                        DsErrorLog::add_log('直充解析空',json_encode($r_c),'直充解析空');
                    }
                    if($re['code'] == '0'){
//                        $rok = DsCzHuafeiUserOrder::query()->where('cz_huafei_user_order_id',$order_id)->where('user_id',$user_id)->update([
//                           'status' => 1,
//                        ]);
//                        if(!$rok){
//                            DsErrorLog::add_log('充值成功修改状态失败',json_encode($r_c),'充值成功修改状态失败');
//                        }
                    }else{
                        DsErrorLog::add_log('充值失败',json_encode($r_c),$re['message']);
                        if($res['price_tongzheng']){
                            $fan = DsUserTongzheng::add_tongzheng($user_id,$res['price_tongzheng'],'充值失败退回');
                            if(!$fan){
                                DsErrorLog::add_log('充值失败退回失败！',json_encode($r_c),'充值失败退回失败');
                            }
                        }
                    }
                }
            }catch(\Exception $exception) {
                if($res['price_tongzheng']){
                    $fan = DsUserTongzheng::add_tongzheng($user_id,$res['price_tongzheng'],'充值失败退回');
                    if(!$fan){
                        DsErrorLog::add_log('充值失败退回失败',json_encode($r_c),'充值失败退回失败');
                    }
                }
            }
        }else{
            DsErrorLog::add_log('直充-订单不存在',$order_id,'直充-订单不存在');
        }
        return true;
    }

    //订单查询接口
    public function order_select($order_id){
        return false;
        $res = DsCzHuafeiUserOrder::query()->where('cz_huafei_user_order_id',$order_id)->where('status',2)->first();
        if($res){
            $url = 'fulu.order.info.get';
            $r_c = [
                'app_key' => $this->AppKey,
                'method' => $url,
                'timestamp' => date('Y-m-d H:i:s',time()),
                'version' => $this->version,
                'format' => $this->format,
                'charset' => $this->charset,
                'sign_type' => $this->sign_type,
                'app_auth_token' => $this->app_auth_token,
            ];
            $r =[
                'customer_order_no' =>   $res['customer_order_no'],
            ];
            $r_c['biz_content'] = json_encode($r);
            $sign = $this->getSign($r_c);
            $r_c['sign'] = $sign;

            try {
                $res_info = $this->http->post($this->fulu_host, ['headers' =>['Content-Type' => 'application/json'], 'json' => $r_c])->getBody()->getContents();
                if(empty($res_info))
                {
                     return false;
                }else {
                    $re = json_decode($res_info, true);
                    if(empty($re)){
                        DsErrorLog::add_log('充值解析空',json_encode($r_c),'充值解析空');
                    }
                    if($re['code'] == '0'){
                        $data_arr = json_decode($re['result'],true);
                        if($data_arr['order_type'] == '1'){
                            DsCzHuafeiUserOrder::query()->where('cz_huafei_user_order_id',$order_id)->update([
                               'status' => 1,
                               'charge_finish_time' => $data_arr['finish_time'],
                            ]);
                        }
                    }
                }
            }catch(\Exception $exception) {
                return false;
            }
        }else{
            return false;
        }
        return true;
    }



    //直充下单通用 品味
    public function zhichong_add_pinwei($order_id){
        $res = DsCzHuafeiUserOrder::query()->where('cz_huafei_user_order_id',$order_id)->first();
        if($res){
            $res = $res->toArray();
            $url = '/api/huafei/recharge';
            $r_c = [
                'mchId' => $this->mchId_pw,
                'out_order_sn' => $res['customer_order_no'],
                'mobile' => $res['charge_phone'],
                'product_id' => $res['product_id'],
                'datetime' => date('YmdHis',time()),
            ];
            $sign = $this->getSign_pw($r_c);
            $r_c['sign'] = $sign;

            try {
                $res_info = $this->http->post($this->url_pw.$url, ['headers' =>['Content-Type' => 'application/json'], 'json' => $r_c])->getBody()->getContents();
                var_dump($res_info);
                if(empty($res_info))
                {
//                    if($res['price_tongzheng']){
//                        $fan = DsUserTongzheng::add_tongzheng($res['user_id'],$res['price_tongzheng'],'充值失败退回');
//                        if(!$fan){
//                            DsErrorLog::add_log('充值失败退回失败！',json_encode($r_c),'充值失败退回失败');
//                        }
//                    }
                    DsErrorLog::add_log('直充返回空',json_encode($r_c),'直充返回空');
                    return false;
                }else {
                    $re = json_decode($res_info, true);

                    if(empty($re)){
//                        if($res['price_tongzheng']){
//                            $fan = DsUserTongzheng::add_tongzheng($res['user_id'],$res['price_tongzheng'],'充值失败退回');
//                            if(!$fan){
//                                DsErrorLog::add_log('充值失败退回失败！',json_encode($r_c),'充值失败退回失败');
//                            }
//                        }
                        DsErrorLog::add_log('充解析空',json_encode($r_c),'充解析空');
                        return false;
                    }
                    if($re['code'] == '200'){
                        $o = DsCzHuafeiUserOrder::query()->where('cz_huafei_user_order_id',$order_id)->update([
                            'order_id'=> $re['datas']['order_sn'],
                            'recharge_description'=> $re['msg'],
                            'price_kou'=> $re['datas']['price'],
                            'balance'=> $re['datas']['balance'],
                        ]);
                        if(!$o){
                            DsErrorLog::add_log('下单成功-修改订单失败',json_encode($r_c),'下单成功-修改订单失败');
                        }
                        return true;
                    }else{
                        var_dump('话费-异步下单失败后');
                        if(empty($re['msg'])){
                            $re['msg'] = '下单失败-联系客服';
                        }
                        $o = DsCzHuafeiUserOrder::query()->where('cz_huafei_user_order_id',$order_id)->update([
                            'status'=> 3,
                            'recharge_description'=> $re['msg'],
                        ]);
                        if(!$o){
                            DsErrorLog::add_log('下单失败-修改订单失败-话费',json_encode($r_c),'下单失败-修改订单失败-话费');
                        }
                        if($res['price_tongzheng']){
                            $fan = DsUserTongzheng::add_tongzheng($res['user_id'],$res['price_tongzheng'],'充值失败退回：'.$res['cz_huafei_user_order_id']);
                            if(!$fan){
                                DsErrorLog::add_log('充值失败退回失败！',json_encode($r_c),'充值失败退回失败');
                            }
                        }
                        return false;
                    }
                }
            }catch(\Exception $exception) {
//                if($res['price_tongzheng']){
//                    $fan = DsUserTongzheng::add_tongzheng($res['user_id'],$res['price_tongzheng'],'充值失败退回');
//                    if(!$fan){
//                        DsErrorLog::add_log('充值异常失败退回失败',json_encode($r_c),'充值异常失败退回失败');
//                    }
//                }
                DsErrorLog::add_log('充值异常失败',json_encode($exception->getMessage()),'充值异常失败：'.$order_id);
                return false;
            }
        }else{
            DsErrorLog::add_log('充-订单不存在',$order_id,'充-订单不存在：'.$order_id);
            return false;
        }

    }

    /**
     * @param $params
     * @return string
     */
    protected function getSign_pw($params){
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
}