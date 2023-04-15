<?php declare(strict_types=1);

namespace App\Controller\v2;

use App\Controller\async\OrderSn;
//use App\Controller\v1\CommonPayController;
use App\Controller\v1\CommonPayController;
use App\Controller\XiaoController;
use App\Model\DsOrder;
use App\Model\DsUser;
use App\Model\DsUserPuman;
use App\Model\DsUserTongzheng;
use App\Model\DsVipPrice;
use App\Service\Pay\Alipay\Pay;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Annotation\Middlewares;
use App\Middleware\UserMiddleware;
use App\Middleware\SignatureMiddleware;
use Hyperf\Utils\Context;
use Hyperf\Di\Annotation\Inject;
use Hyperf\RateLimit\Annotation\RateLimit;
/**
 * vip接口
 * Class UserController
 * @Middlewares({
 *     @Middleware(SignatureMiddleware::class),
 *     @Middleware(UserMiddleware::class)
 * })
 * @package App\Controller\v2
 * @Controller(prefix="v2/vip")
 */
class VipController extends XiaoController
{
    /**
     * 开通会员
     * @RequestMapping(path="pay",methods="post")
     * @RateLimit(create=1, consume=1, capacity=1, key={VipController::class, "_key"})
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Swoole\Exception
     */
    public function pay()
    {
        $user_id    = Context::get('user_id');
        $userInfo     = Context::get('userInfo');
        //自己判断是否是会员
        if($this->_is_vip($user_id))
        {
            return $this->withError('您已是会员,请前往续费');
        }

        //判断类型 ====begin
        $type = $this->request->post('type',0);//1现金30天 2现金365天 3铺满30天 4铺满365天
        if(empty($type)){
            return $this->withError('请选择要开通的类型');
        }
        //判断类型 ====end

        $pay_type = $this->request->post('pay_type',1); //1支付宝 2微信 3银行卡 4余额通证支付 5铺满支付
        switch ($pay_type)
        {
            case 1:
                //判断类型 ====begin
                if(!in_array($type,[1,2])){
                    return $this->withError('请选择要开通的类型');
                }
                if($type=='1'){
                    //获取价格
                    $price = DsVipPrice::query()->where('vip_price_id',1)->value('price');
                    $day = 30;
                }
                if($type=='2'){
                    $price = DsVipPrice::query()->where('vip_price_id',2)->value('price');
                    $day = 365;
                }
                //判断类型 ====end

                //判断用户是否再刷单
                $add_time = DsOrder::query()->where('user_id',$user_id)
                    ->orderByDesc('order_id')
                    ->value('add_time');
                if($add_time)
                {
                    $shuadan_time = $this->redis0->get('shuadan_time');
                    if((time()-$add_time) < $shuadan_time)
                    {
                        $ci = $shuadan_time - (time()-$add_time);
                        return $this->withError('唤起支付次数过多，请于'.$ci.'秒后再试!');
                    }
                }
                //生成订单号
                $od = make(OrderSn::class);
                $orderSn = $od->createOrderSN();
                //生成支付订单
                $orderDatas = [
                    'order_sn'          => $orderSn,         // 这里要保证订单的唯一性
                    'user_id'           => $user_id,         //用户id
                    'to_id'             => 0,               //为谁支付的id
                    'payment_type'      => $pay_type,        //支付类型  1.alipay   2.wechat
                    'need_money'        => $price,         //需要支付的金额
                    'order_status'      => 0,              //订单状态 0-未支付；1-已支付
                    'add_time'          => time(),           //订单生成时间
                    'order_type'        => 2,              //开通类型 购买商品开通类型 1实名认证 2开通会员 3续费会员 4购买商品
                    'order_relation'        => $day,              //天数
                ];
                //入库
                $res2 = DsOrder::query()->insertGetId($orderDatas);
                if(!$res2)
                {
                    return $this->withError('当前支付订单较多,请稍后再试！');
                }

                $cpay = make(CommonPayController::class);
                $z = $cpay->pay($user_id,$pay_type,$price,$orderSn,'开通会员');
                return $this->withResponse('订单生成成功',$z);
                break;
            case 2:
                return $this->withError('请使用支付宝支付');
                break;
            case 4:
                //判断类型 ====begin
                if(!in_array($type,[3,4])){
                    return $this->withError('请选择要开通的类型');
                }
                if($type=='3'){
                    //获取价格
                    $price = DsVipPrice::query()->where('vip_price_id',1)->value('puman');
                    $day = 30;
                }
                if($type=='4'){
                    $price = DsVipPrice::query()->where('vip_price_id',2)->value('puman');
                    $day = 365;
                }
                //判断类型 ====end

                //验证码校验
                $code = $this->request->post('code','');
                if(empty($code))
                {
                    return $this->withError('验证码不能为空');
                }
                $this->_checkCode($userInfo['mobile'],'vip_pay',$code,time(),$user_id);

                //判断用户是否再刷单
                $add_time = DsOrder::query()->where('user_id',$user_id)
                    ->orderByDesc('order_id')
                    ->value('add_time');
                if($add_time)
                {
                    $shuadan_time = $this->redis0->get('shuadan_time');
                    if((time()-$add_time) < $shuadan_time)
                    {
                        $ci = $shuadan_time - (time()-$add_time);
                        return $this->withError('唤起支付次数过多，请于'.$ci.'秒后再试!');
                    }
                }

                if($userInfo['tongzheng'] < $price){
                    return $this->withError('通证不足');
                }
                $re =  DsUserTongzheng::del_tongzheng($user_id,$price,'开通会员');
                if(!$re){
                    return $this->withError('当前人数开通较多，请稍后再试');
                }
                DsUser::pay_vip($user_id, $day, 1);

                return $this->withSuccess_ty_null('成功');
                break;
            default :
                break;
        }

    }

    /**
     * 续费会员
     * @RequestMapping(path="xufei",methods="post")
     * @RateLimit(create=1, consume=1, capacity=1, key={VipController::class, "_key"})
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Swoole\Exception
     */
    public function xufei()
    {
        $user_id    = Context::get('user_id');
        $userInfo     = Context::get('userInfo');
        //自己判断是否是会员
        if(!$this->_is_vip($user_id))
        {
            return $this->withError('您还不是会员,请前往开通');
        }

        //判断类型 ====begin
        $type = $this->request->post('type',0);//1现金30天 2现金365天 3铺满30天 4铺满365天
        if(empty($type)){
            return $this->withError('请选择要开通的类型');
        }
        //判断类型 ====end

        $pay_type = $this->request->post('pay_type',1); //1支付宝 2微信 3银行卡 4余额
        switch ($pay_type)
        {
            case 1:
                //判断类型 ====begin
                if(!in_array($type,[1,2])){
                    return $this->withError('请选择要开通的类型');
                }
                if($type=='1'){
                    //获取价格
                    $price = DsVipPrice::query()->where('vip_price_id',1)->value('price');
                    $day = 30;
                }
                if($type=='2'){
                    $price = DsVipPrice::query()->where('vip_price_id',2)->value('price');
                    $day = 365;
                }
                //判断类型 ====end

                //判断用户是否再刷单
                $add_time = DsOrder::query()->where('user_id',$user_id)
                    ->orderByDesc('order_id')
                    ->value('add_time');
                if($add_time)
                {
                    $shuadan_time = $this->redis0->get('shuadan_time');
                    if((time()-$add_time) < $shuadan_time)
                    {
                        $ci = $shuadan_time - (time()-$add_time);
                        return $this->withError('唤起支付次数过多，请于'.$ci.'秒后再试!');
                    }
                }
                //生成订单号
                $od = make(OrderSn::class);
                $orderSn = $od->createOrderSN();
                //生成支付订单
                $orderDatas = [
                    'order_sn'          => $orderSn,         // 这里要保证订单的唯一性
                    'user_id'           => $user_id,         //用户id
                    'to_id'             => 0,               //为谁支付的id
                    'payment_type'      => $pay_type,        //支付类型  1.alipay   2.wechat
                    'need_money'        => $price,         //需要支付的金额
                    'order_status'      => 0,              //订单状态 0-未支付；1-已支付
                    'add_time'          => time(),           //订单生成时间
                    'order_type'        => 3,              //开通类型 购买商品开通类型 1实名认证 2开通会员 3续费会员 4购买商品
                    'order_relation'        => $day,              //天数
                ];
                //入库
                $res2 = DsOrder::query()->insertGetId($orderDatas);
                if(!$res2)
                {
                    return $this->withError('当前支付订单较多,请稍后再试！');
                }

                $cpay = make(CommonPayController::class);
                $z = $cpay->pay($user_id,$pay_type,$price,$orderSn,'续费会员');
                return $this->withResponse('订单生成成功',$z);
                break;
            case 2:
                return $this->withError('没有开通该类型');
                break;
            case 4:
                //判断类型 ====begin
                if(!in_array($type,[3,4])){
                    return $this->withError('请选择要开通的类型');
                }
                if($type=='3'){
                    //获取价格
                    $price = DsVipPrice::query()->where('vip_price_id',1)->value('puman');
                    $day = 30;
                }
                if($type=='4'){
                    $price = DsVipPrice::query()->where('vip_price_id',2)->value('puman');
                    $day = 365;
                }
                //判断类型 ====end

                //验证码校验
                $code = $this->request->post('code','');
                if(empty($code))
                {
                    return $this->withError('验证码不能为空');
                }
                $this->_checkCode($userInfo['mobile'],'vip_xufei',$code,time(),$user_id);

                //判断用户是否再刷单
                $add_time = DsOrder::query()->where('user_id',$user_id)
                    ->orderByDesc('order_id')
                    ->value('add_time');
                if($add_time)
                {
                    $shuadan_time = $this->redis0->get('shuadan_time');
                    if((time()-$add_time) < $shuadan_time)
                    {
                        $ci = $shuadan_time - (time()-$add_time);
                        return $this->withError('唤起支付次数过多，请于'.$ci.'秒后再试!');
                    }
                }

                if($userInfo['tongzheng'] < $price){
                    return $this->withError('通证不足');
                }
                $re = DsUserTongzheng::del_tongzheng($user_id,$price,'续费会员');
                if(!$re){
                    return $this->withError('当前人数开通较多，请稍后再试');
                }
                DsUser::pay_vip($user_id, $day, 2);

                return $this->withSuccess_ty_null('成功');
                break;
            default:
                return $this->withError('没有开通该类型');
                break;
        }
    }


    /**
     * 返回用户id
     * @return string
     */
    public static function _key(): string
    {
        $user_id    = Context::get('user_id');
        return strval($user_id);
    }
}