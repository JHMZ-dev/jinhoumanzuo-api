<?php

declare(strict_types=1);

namespace App\Controller\v2;

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
 * @package App\Controller\v2
 * @Controller(prefix="v2/laquhuafei")
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
     * 充值
     * @RequestMapping(path="huafei_do_pinwei",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function huafei_do_pinwei(){
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

        //验证码校验
        $code = $this->request->post('code','');
        if(empty($code))
        {
            return $this->withError('验证码不能为空');
        }
        $this->_checkCode($userInfo['mobile'],'hf_order',$code,time(),$user_id);

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
