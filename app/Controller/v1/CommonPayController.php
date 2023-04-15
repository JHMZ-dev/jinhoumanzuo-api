<?php declare(strict_types=1);

namespace App\Controller\v1;

use App\Controller\async\OrderSn;
use App\Controller\XiaoController;
use App\Model\DsOrder;
use App\Service\Pay\Alipay\Pay;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Annotation\Middleware;
use App\Middleware\UserMiddleware;
use Hyperf\HttpServer\Annotation\Middlewares;
use App\Middleware\SignatureMiddleware;
use Hyperf\Utils\Context;
use Hyperf\Di\Annotation\Inject;
use Swoole\Exception;

/**
 * 统一支付下单接口
 * Class CommonPayController
 * @package App\Controller\v1
 */
class CommonPayController extends XiaoController
{
    /**
     * @Inject
     * @var Pay
     */
    protected $ali_pay;

    /**
     * @Inject
     * @var \App\Service\Pay\Wechat\Pay
     */
    protected $wx_pay;

    /**
     *
     * @param $user_id
     * @param $pay_type 1支付宝 2微信
     * @param $price    //价格
     * @param $orderSn  //订单号
     * @param $cont     //下单内容
     * @return array|\Psr\Http\Message\ResponseInterface
     * @throws Exception
     */
    public function apply_pay($openid,$price,$orderSn,$cont)
    {
        //判断是否开放支付
        $is_pay = $this->redis0->get('is_pay');
        if($is_pay != 1)
        {
            throw new Exception('非常抱歉，系统升级中,请耐心等待一下~', 10001);
        }
        //生成微信app订单
        $data = [
            'total_fee'      => $price*100,
            'out_trade_no'   => $orderSn,
            'body'           => $cont,
            'openid'         => $openid,
//            'time_expire'    => strval(date('YmdHis')+290),
        ];
        $res = $this->wx_pay->MiniAppPayment($data);
        return ['order_sn' => $orderSn,'wx_res' => $res];
    }

    /**
     * @param $user_id
     * @param $pay_type 1支付宝 2微信
     * @param $price    //价格
     * @param $orderSn  //订单号
     * @param $cont     //下单内容
     * @param string $openid
     * @return array
     * @throws Exception
     */
    public function pay($user_id,$pay_type,$price,$orderSn,$cont,$openid ='')
    {
        //判断是否开放支付
        $is_pay = $this->redis0->get('is_pay');
        if($is_pay != 1)
        {
            if($user_id != 1470000 && $user_id != 1670001)
            {
                throw new Exception('非常抱歉，系统升级中,请耐心等待一下~', 10001);
            }
        }
        switch ($pay_type)
        {
            case '1':
                //生成支付宝app订单
                $data = [
                    'total_amount'      => $price,
                    'out_trade_no'      => $orderSn,
                    'subject'           => $cont,
//                    'timeout_express'   => '3m',
                ];
                $url = $this->ali_pay->AppPayment($data);
                return ['order_sn' => $orderSn,'res' => $url];
                break;
            case '2':
                throw new Exception('微信支付暂时关闭！请使用支付宝！', 10001);
                //生成微信app订单
                $data = [
                    'total_fee'      => $price*100,
                    'out_trade_no'   => $orderSn,
                    'body'           => $cont,
//                    'time_expire'    => strval(date('YmdHis')+290),
                ];
                $res = $this->wx_pay->AppPayment($data);
                $result['appid']     = $res['appid'];
                $result['partnerid'] = $res['partnerid'];
                $result['prepayid']  = $res['prepayid'];
                $result['package']   = 'Sign=WXPay';
                $result['noncestr']  = $res['noncestr'];
                $result['timestamp'] = strval(time());
                $result['sign']      = $res['sign'];
                return ['order_sn' => $orderSn,'wx_res' => $result];
                break;
            case '4':
                //生成微信小程序订单
                $data = [
                    'total_fee'      => $price*100,
                    'out_trade_no'   => $orderSn,
                    'body'           => $cont,
                    'openid'         => $openid,
//                    'time_expire'    => strval(date('YmdHis')+290),
                ];
                $res = $this->wx_pay->MiniAppPayment($data);
                return ['order_sn' => $orderSn,'wx_res' => $res];
                break;
            case '7':
                //生成微信小程序订单
                $data = [
                    'total_fee'      => $price*100,
                    'out_trade_no'   => $orderSn,
                    'body'           => $cont,
                    'openid'         => $openid,
                ];
                $res = $this->wx_pay->MiniAppPayment($data);
                return ['order_sn' => $orderSn,'wx_res' => $res];
                break;
            default:
                throw new Exception('支付类型选择错误', 10001);
                break;
        }
    }
}