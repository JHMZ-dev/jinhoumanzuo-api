<?php

declare(strict_types=1);

namespace App\Controller\v1;

use App\Controller\async\Huafeido;
use App\Controller\XiaoController;
use App\Model\Chuli;
use App\Model\DsCinema;
use App\Model\DsCinemaCity;
use App\Model\DsCinemaCityChild;
use App\Model\DsCinemaUserOrder;
use App\Model\DsCinemaVideo;
use App\Model\DsCinemaPaiqi;
use App\Model\DsCzHuafeiUserOrder;
use App\Model\DsCzUserOrder;
use App\Model\DsErrorLog;
use App\Model\DsUserTongzheng;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;



use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Annotation\Middlewares;
use App\Middleware\SignatureMiddleware;
use App\Middleware\UserMiddleware;
use Hyperf\Utils\Context;

use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Annotation\Controller;

use Hyperf\Utils\Parallel;

/**
 *
 * @Middlewares({
 *     @Middleware(SignatureMiddleware::class),
 *     @Middleware(UserMiddleware::class)
 * })
 * @package App\Controller\v1
 * @Controller(prefix="v1/laquhuafei")
 */
class LaquHuafeiController extends XiaoController
{
    private $fulu_host = 'http://openapi.fulu.com/api/getway';//沙箱环境 http://pre.openapi.fulu.com/api/getway
    private $AppKey = 'MggQBHn0kC3BBEizoJlh1Et2INmvKYxSUiYpPEWbpAqFQ0iM6nGgB0kbzZT6Rkhg';
    private $AppSecret = '3dd46243a0c04465a8f59a0f0b6e7c8e';
    private $version = '2.0';
    private $format = 'json';
    private $charset = 'utf-8';
    private $sign_type = 'md5';
    private $app_auth_token = '';

    /**生成唯一单号
     * @return string
     */
    protected function get_number(){
        return  date('Ymd').substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8) . rand(100000,9999999);
    }

    /**
     * 充话费-初始化
     * @RequestMapping(path="get_huafei_csh",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get_huafei_csh(){
        $tz_beilv = $this->tz_beilv();
        $huafei100 = 100;
        $huafei200 = 200;
        $data = [
            '0' => [
                'price_tongzheng' => round($huafei100/$tz_beilv,$this->xiaoshudian),
                'price' => $huafei100,
                'name' => '话费100',
                'type' => 1,
                'product_id' => "11366363",
            ],
            '1' => [
                'price_tongzheng' => round($huafei200/$tz_beilv,$this->xiaoshudian),
                'price' => $huafei200,
                'name' => '话费200',
                'type' => 2,
                'product_id' => "14114160",
            ],
        ];

         return $this->withResponse('ok',$data);
    }

    /**
     * 充话费
     * @RequestMapping(path="huafei_do",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function huafei_do(){
        $tz_beilv = $this->tz_beilv();
        $user_id = Context::get('user_id');
        $userInfo = Context::get('userInfo');
return $this->withError('请升级APP版本');
        $type = $this->request->post('type',1);//1话费100 2话费200
        $mobile = $this->request->post('mobile',0);

        if (empty($type) || empty($mobile)){
            return $this->withError('请输入手机号');
        }

        if(!$this->isMobile($mobile)){
            return $this->withError('请输入正确的手机号');
        }

        switch ($type){
            case '1':
                $product_name = '话费100';
                $price = 100;
                break;
            case '2':
                $product_name = '话费200';
                $price = 200;
                break;
            default:
                return $this->withError('请选择正确的类型');
                break;
        }

        $this->check_often($user_id);
        $this->start_often($user_id);

        $kou = round($price/$tz_beilv,$this->xiaoshudian);

        //扣通证
        if($userInfo['tongzheng'] < $kou){
            return $this->withError('通证不足还差：'.round($kou - $userInfo['tongzheng'],$this->xiaoshudian));
        }

        $customer_order_no = $this->get_number();
        $data = [
            'user_id' => $user_id,
            'charge_phone' => $mobile,
            'charge_value' => $price,
            'customer_order_no' => $customer_order_no,
            'ordersn' => $this->get_number(),
            'customer_price' => $price,
            'cz_huafei_user_order_time' => time(),
            'product_name' => $product_name,
            'price_tongzheng' => $kou,
        ];
        $re_order_in = DsCzHuafeiUserOrder::query()->insertGetId($data);
        if(!$re_order_in){
            DsUserTongzheng::add_tongzheng($user_id,$kou,$product_name.'退回');
            return $this->withError('当前操作人数较多，请稍后再试');
        }

        $kou_tz = DsUserTongzheng::del_tongzheng($user_id,$kou,$product_name.'【'.$re_order_in.'】');
        if(!$kou_tz){
            return $this->withError('当前人数较多，请稍后');
        }

        $info = [
            'type' => 36,
            'mobile' => $mobile,
            'order_id' => $re_order_in,
            'user_id' => $user_id,
            'money' => $price,
        ];
        $this->yibu($info);

        return $this->withSuccess('ok');
    }

    /**
     * php签名方法
     */
    protected function getSign($Parameters)
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
    protected function mb_str_split($str){
        return preg_split('/(?<!^)(?!$)/u', $str );
    }

    //获取商品详细
    protected function get_goods_fulu($product_id){
        $redis = $this->redis2->get('fulu_goods_'.$product_id);
        if($redis){
            return json_decode($redis,true);
        }
        $url = 'fulu.goods.info.get';
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
            'product_id' =>   $product_id,
        ];
        $r_c['biz_content'] = json_encode($r);
        $sign = $this->getSign($r_c);
        $r_c['sign'] = $sign;

            $res_info = $this->http->post($this->fulu_host, ['headers' =>['Content-Type' => 'application/json'], 'json' => $r_c])->getBody()->getContents();
            if(empty($res_info))
            {
                return false;
            }else {
                $re = json_decode($res_info, true);
                if(empty($re)){
                    return false;
                }
                if($re['code'] == '0'){
                    $this->redis2->set('fulu_goods_'.$product_id,$re['result'],60*60);
                    return $re['result'];
                }else{
                    return false;
                }
            }

    }

    /**
     * 获取商品列表
     * @RequestMapping(path="get_goods_fulu_list",methods="post")
     * @param $product_id
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get_goods_fulu_list(){
//        $mobile = $this->request->post('mobile',0);
//        $url = 'fulu.goods.list.get';
//        $r_c = [
//            'app_key' => $this->AppKey,
//            'method' => $url,
//            'timestamp' => date('Y-m-d H:i:s',time()),
//            'version' => $this->version,
//            'format' => $this->format,
//            'charset' => $this->charset,
//            'sign_type' => $this->sign_type,
//            'app_auth_token' => $this->app_auth_token,
//        ];
//        $r =[
//            'product_id' =>   $product_id,
//        ];
//        $r_c['biz_content'] = json_encode($r);
//        $sign = $this->getSign($r_c);
//        $r_c['sign'] = $sign;
//
//        $res_info = $this->http->post($this->fulu_host, ['headers' =>['Content-Type' => 'application/json'], 'json' => $r_c])->getBody()->getContents();
//        if(empty($res_info))
//        {
//            return false;
//        }else {
//            $re = json_decode($res_info, true);
//            var_dump('商品详细');
//            var_dump($re);
//            if(empty($re)){
//                return false;
//            }
//            if($re['code'] == '0'){
//                $this->redis2->set('fulu_goods_'.$product_id,json_encode($re['result']),60*60);
//                return $re['result'];
//            }else{
//                return false;
//            }
//        }

    }

    /**
     * 直充
     * @RequestMapping(path="zhichong_do",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function zhichong_do(){
        $tz_beilv = $this->tz_beilv();
        return $this->withError('升级中');
        $user_id = Context::get('user_id');
        $userInfo = Context::get('userInfo');

        $mobile = $this->request->post('mobile',0);
        $product_id = $this->request->post('product_id',1);

        if(empty($mobile)){
            return $this->withError('请输入号码');
        }

        if(empty($product_id)){
            return $this->withError('请选择产品');
        }

        if(!in_array($product_id,[11366363,14114160])){
            return $this->withError('当前选择未开放');
        }

        $re = $this->get_goods_fulu($product_id);
        if(empty($re)){
            return $this->withError('商品更新升级中...请等待');
        }

        //检查当前id和号码是否在充值中
        $re_order_start = DsCzHuafeiUserOrder::query()->where('user_id',$user_id)->where('status',2)->orderByDesc('cz_huafei_user_order_id')->first();
        if(!empty($re_order_start)){
            return $this->withError('你有正在充值订单，请等待处理完成再下单');
        }
        $re_order_start_mobile = DsCzHuafeiUserOrder::query()->where('charge_phone',$mobile)->where('status',2)->orderByDesc('cz_huafei_user_order_id')->first();
        if(!empty($re_order_start_mobile)){
            return $this->withError('该号码有正在充值订单，请等待处理完成再下单');
        }

        $product_name = $re['product_name'];
        $price = $re['face_value'];

        if(empty($product_id)){
            return $this->withError('请选择产品');
        }

        if (empty($mobile)){
            return $this->withError('请输入手机号');
        }

        if(!$this->isMobile($mobile)){
            return $this->withError('请输入正确的手机号');
        }

        $this->check_often($user_id);
        $this->start_often($user_id);

        $kou = round($price/$tz_beilv,$this->xiaoshudian);

        //扣通证
        if($userInfo['tongzheng'] < $kou){
            return $this->withError('通证不足还差：'.round($kou - $userInfo['tongzheng'],$this->xiaoshudian));
        }

        $customer_order_no = $this->get_number();
        $data = [
            'user_id' => $user_id,
            'charge_phone' => $mobile,
            'charge_value' => $price,
            'customer_order_no' => $customer_order_no,
            'ordersn' => $this->get_number(),
            'customer_price' => $price,
            'cz_huafei_user_order_time' => time(),
            'product_name' => $product_name,
            'price_tongzheng' => $kou,
            'product_id' => $product_id,
        ];
        $re_order_in = DsCzHuafeiUserOrder::query()->insertGetId($data);
        if(!$re_order_in){
            DsUserTongzheng::add_tongzheng($user_id,$kou,$product_name.'退回');
            return $this->withError('当前操作人数较多，请稍后再试');
        }

        $kou_tz = DsUserTongzheng::del_tongzheng($user_id,$kou,$product_name.'【'.$re_order_in.'】');
        if(!$kou_tz){
            return $this->withError('当前人数较多，请稍后');
        }

        $info = [
            'type' => 37,
            'mobile' => $mobile,
            'order_id' => $re_order_in,
            'user_id' => $user_id,
            'money' => $price,
        ];
        $this->yibu($info);

        return $this->withSuccess('下单成功，请等待到账预计1天内到账');
    }

    /**
     *
     * 直充订单列表
     * @RequestMapping(path="get_zhichong_order",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get_zhichong_order(){
        $user_id  = Context::get('user_id');

        if(empty($user_id)){
            return $this->withError('登录已过期');
        }

        $page     = $this->request->post('page',1);
        if($page <= 0)
        {
            $page = 1;
        }
        $data['more']           = '0';
        $data['data']         = [];
        $type = $this->request->post('type',0); //1成功，2处理中，3失败

        $res = DsCzHuafeiUserOrder::query()->where('user_id',$user_id)
            ->orderByDesc('cz_huafei_user_order_id')
            ->forPage($page,10)->get()->toArray();

        if(!empty($type)){
            switch ($type){
                case 1:
                    $res = DsCzHuafeiUserOrder::query()->where('user_id',$user_id)
                        ->where('status',1)
                        ->orderByDesc('cz_huafei_user_order_id')
                        ->forPage($page,10)->get()->toArray();
                    break;
                case 2:
                    $res = DsCzHuafeiUserOrder::query()->where('user_id',$user_id)
                        ->where('status',2)
                        ->orderByDesc('cz_huafei_user_order_id')
                        ->forPage($page,10)->get()->toArray();
                    break;
                case 3:
                    $res = DsCzHuafeiUserOrder::query()->where('user_id',$user_id)
                        ->where('status',3)
                        ->orderByDesc('cz_huafei_user_order_id')
                        ->forPage($page,10)->get()->toArray();
                    break;
            }
        }


        if(!empty($res))
        {
            //查找是否还有下页数据
            if(count($res) == 10)
            {
                $data['more'] = '1';
            }
            //整理数据
            $datas = [];
            foreach ($res as $k => $v)
            {
                $datas[$k]['id']  = $v['cz_huafei_user_order_id'];
                $datas[$k]['charge_phone']  = $v['charge_phone'];
                $datas[$k]['charge_value']  = $v['charge_value'];
                $datas[$k]['ordersn']  = $v['ordersn'];
                $datas[$k]['status']  = $v['status'];
                $datas[$k]['product_name']  = $v['product_name'];
                $datas[$k]['customer_price']  = $v['customer_price'];
                $datas[$k]['price_tongzheng']  = $v['price_tongzheng'];
                $datas[$k]['recharge_description']  = $v['recharge_description'];
                $datas[$k]['name']  = $v['name'];
                $datas[$k]['time']  = $this->replaceTime($v['cz_huafei_user_order_time']);
                $datas[$k]['time_ok']  = $this->replaceTime($v['charge_finish_time']);
            }
            $data['data']           = $datas;
        }

        return $this->withResponse('获取成功',$data);
    }


    //品味查询话费列表=======================================================================
    public function get_goods_pw(){
        $tz_beilv = $this->tz_beilv();

        return [
            '0' => [
                'price_tongzheng' => strval(round(100/$tz_beilv,$this->xiaoshudian)),
                'price' => 100,
                'name' => '全国移动话费慢充',
                'name2' => '72小时到账，携号转网、副卡、空号不能充值，无法处理售后，超过10天的售后不处理。订单未成功之前，请勿通过其他方式充值。',
                'product_id' => "1038",
            ],
            '1' => [
                'price_tongzheng' => strval(round(100/$tz_beilv,$this->xiaoshudian)),
                'price' => 100,
                'name' => '全国联通话费慢充',
                'name2' => '72小时到账，携号转网、副卡、空号不能充值，无法处理售后，超过10天的售后不处理。订单未成功之前，请勿通过其他方式充值。',
                'product_id' => "1041",
            ],
            '2' => [
                'price_tongzheng' => strval(round(100/$tz_beilv,$this->xiaoshudian)),
                'price' => 100,
                'name' => '全国电信话费慢充',
                'name2' => '72小时到账，携号转网、副卡、空号不能充值，无法处理售后，超过10天的售后不处理。订单未成功之前，请勿通过其他方式充值。',
                'product_id' => "1044",
            ],
            '3' => [
                'price_tongzheng' => strval(round(200/$tz_beilv,$this->xiaoshudian)),
                'price' => 200,
                'name' => '全国移动话费慢充',
                'name2' => '72小时到账，携号转网、副卡、空号不能充值，无法处理售后，超过10天的售后不处理。订单未成功之前，请勿通过其他方式充值。',
                'product_id' => "1039",
            ],
            '4' => [
                'price_tongzheng' => strval(round(200/$tz_beilv,$this->xiaoshudian)),
                'price' => 200,
                'name' => '全国联通话费慢充',
                'name2' => '72小时到账，携号转网、副卡、空号不能充值，无法处理售后，超过10天的售后不处理。订单未成功之前，请勿通过其他方式充值。',
                'product_id' => "1042",
            ],
            '5' => [
                'price_tongzheng' => strval(round(200/$tz_beilv,$this->xiaoshudian)),
                'price' => 200,
                'name' => '全国电信话费慢充',
                'name2' => '72小时到账，携号转网、副卡、空号不能充值，无法处理售后，超过10天的售后不处理。订单未成功之前，请勿通过其他方式充值。',
                'product_id' => "1045",
            ],
        ];
    }
    /**
     * 商品列表
     * @RequestMapping(path="select_goods_list_huafei",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function select_goods_list_huafei(){

        $data = $this->get_goods_pw();
        if(empty($data)){
            return $this->withError('商品上架中');
        }

        return $this->withResponse('ok',$data);
    }

    /**
     * 充值
     * @RequestMapping(path="huafei_do_pinwei",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function huafei_do_pinwei(){
        //检测版本
        $this->_check_version('1.1.3');

        $tz_beilv = $this->tz_beilv();

        $user_id = Context::get('user_id');
        $userInfo = Context::get('userInfo');

        $mobile = $this->request->post('mobile',0);
        $product_id = $this->request->post('product_id',0);

        $this->check_often($user_id);
        $this->start_often($user_id);

        $redwrap_switch = $this->redis0->get('is_huafei_buy');
        if($redwrap_switch != 1){
            return $this->withError('暂未开放');
        }

        $this->check_futou($user_id);

        if(empty($mobile)){
            return $this->withError('请输入号码');
        }
        if(empty($product_id)){
            return $this->withError('请选择产品');
        }
        if(!$this->isMobile($mobile)){
            return $this->withError('请输入正确的手机号');
        }

        if(!in_array($product_id,[
            1038,
            1041,
            1044,
            1039,
            1042,
            1045,
        ])){
            return $this->withError('当前选择未开放');
        }

        $data = $this->get_goods_pw();
        if(empty($data)){
            return $this->withError('商品上架中');
        }

        switch ($product_id){
            case '1038':
                $re = $data[0];
                break;
            case '1041':
                $re = $data[1];
                break;
            case '1044':
                $re = $data[2];
                break;
            case '1039':
                $re = $data[3];
                break;
            case '1042':
                $re = $data[4];
                break;
            case '1045':
                $re = $data[5];
                break;
            default:
                return $this->withError('当前选择未开放!');
                break;
        }
        if(empty($re)){
            return $this->withError('商品更新升级中...请等待');
        }

        //检查当前id和号码是否在充值中
        $re_order_start = DsCzHuafeiUserOrder::query()->where('user_id',$user_id)->where('status',2)->orderByDesc('cz_huafei_user_order_id')->first();
        if(!empty($re_order_start)){
            return $this->withError('你有正在充值订单，请等待处理完成再下单');
        }
        $re_order_start_mobile = DsCzHuafeiUserOrder::query()->where('charge_phone',$mobile)->where('status',2)->orderByDesc('cz_huafei_user_order_id')->first();
        if(!empty($re_order_start_mobile)){
            return $this->withError('该号码有正在充值订单，请等待处理完成再下单');
        }

        $product_name = $re['name'];
        $price = $re['price'];

        $kou = round($price/$tz_beilv,$this->xiaoshudian);

        //扣通证
        if($userInfo['tongzheng'] < $kou){
            return $this->withError('通证不足还差：'.round($kou - $userInfo['tongzheng'],$this->xiaoshudian));
        }
        $kou_tz = DsUserTongzheng::del_tongzheng($user_id,$kou,'充值'.$product_name);
        if(!$kou_tz){
            return $this->withError('当前人数较多，请稍后');
        }

        $customer_order_no = $this->get_number();
        $data = [
            'user_id' => $user_id,
            'charge_phone' => $mobile,
            'charge_value' => $price,
            'customer_order_no' => $customer_order_no,
            'ordersn' => $this->get_number(),
            'customer_price' => $price,
            'cz_huafei_user_order_time' => time(),
            'product_name' => $product_name,
            'price_tongzheng' => $kou,
            'product_id' => $product_id,
        ];
        $re_order_in = DsCzHuafeiUserOrder::query()->insertGetId($data);
        if(!$re_order_in){
            DsUserTongzheng::add_tongzheng($user_id,$kou,$product_name.'下单退回:'.$re_order_in);
            return $this->withError('当前操作人数较多，请稍后再试');
        }

        $info = [
            'type' => 43,
            'order_id' => $re_order_in,
        ];
        $this->yibu($info);

        return $this->withSuccess('下单成功，请等待到账预计3天内到账');
    }

}
