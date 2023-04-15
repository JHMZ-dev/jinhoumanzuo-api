<?php declare(strict_types=1);

namespace App\Controller;

use App\Model\DsOrder;
use App\Service\Pay\Alipay\Pay;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Di\Annotation\Inject;

/**
 * 支付接口
 * Class UserController

 * @package App\Controller\
 * @Controller(prefix="pay")
 */
class PayController extends XiaoController
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
     * 支付宝回调
     * @RequestMapping(path="alipay",methods="post,get")
     */
    public function alipay(RequestInterface $request)
    {
        $content = $request->all();
        if(!empty($content))
        {
//            $path = BASE_PATH.'/app/Service/Pay/Alipay/';
            //file_put_contents($path.'alylog.txt',json_encode($content).PHP_EOL, FILE_APPEND);
            //记录日志
            try {
                $data = $this->ali_pay->verify($content);
//                file_put_contents($path.'alylog2.txt',json_encode($data).PHP_EOL, FILE_APPEND);
                if($data['trade_status'] == 'TRADE_SUCCESS')
                {
                    $ordersn = $data['out_trade_no'];
                    $money   = $data['total_amount'];
                    DsOrder::paySuccess($ordersn,$money);
                }else{
                    var_dump('支付宝签名不正确');
                }
            }catch (\Exception $e)
            {
                var_dump($e->getMessage());
            }
        }

    }
    /**
     * 支付宝回调2
     * @RequestMapping(path="alipay2",methods="post,get")
     */
    public function alipay2(RequestInterface $request)
    {
        $content = $request->all();
        if(!empty($content))
        {
//            $path = BASE_PATH.'/app/Service/Pay/Alipay/';
            //file_put_contents($path.'alylog.txt',json_encode($content).PHP_EOL, FILE_APPEND);
            //记录日志
            try {
                $data = $this->ali_pay->verify($content);
//                file_put_contents($path.'alylog2.txt',json_encode($data).PHP_EOL, FILE_APPEND);
                if($data['trade_status'] == 'TRADE_SUCCESS')
                {
                    $ordersn = $data['out_trade_no'];
                    $money   = $data['total_amount'];
                    DsOrder::paySuccess($ordersn,$money);
                }else{
                    var_dump('支付宝签名不正确');
                }
            }catch (\Exception $e)
            {
                var_dump($e->getMessage());
            }
        }

    }

    /**
     * 支付宝回调3
     * @RequestMapping(path="alipay3",methods="post,get")
     */
    public function alipay3(RequestInterface $request)
    {
        $content = $request->all();
        if(!empty($content))
        {
//            $path = BASE_PATH.'/app/Service/Pay/Alipay/';
            //file_put_contents($path.'alylog.txt',json_encode($content).PHP_EOL, FILE_APPEND);
            //记录日志
            try {
                $data = $this->ali_pay->verify($content);
//                file_put_contents($path.'alylog2.txt',json_encode($data).PHP_EOL, FILE_APPEND);
                if($data['trade_status'] == 'TRADE_SUCCESS')
                {
                    $ordersn = $data['out_trade_no'];
                    $money   = $data['total_amount'];
                    DsOrder::paySuccess($ordersn,$money);
                }else{
                    var_dump('支付宝签名不正确');
                }
            }catch (\Exception $e)
            {
                var_dump($e->getMessage());
            }
        }

    }

    /**
     * 支付宝回调4
     * @RequestMapping(path="alipay4",methods="post,get")
     */
    public function alipay4(RequestInterface $request)
    {
        $content = $request->all();
        if(!empty($content))
        {
//            $path = BASE_PATH.'/app/Service/Pay/Alipay/';
            //file_put_contents($path.'alylog.txt',json_encode($content).PHP_EOL, FILE_APPEND);
            //记录日志
            try {
                $data = $this->ali_pay->verify($content);
//                file_put_contents($path.'alylog2.txt',json_encode($data).PHP_EOL, FILE_APPEND);
                if($data['trade_status'] == 'TRADE_SUCCESS')
                {
                    $ordersn = $data['out_trade_no'];
                    $money   = $data['total_amount'];
                    DsOrder::paySuccess($ordersn,$money);
                }else{
                    var_dump('支付宝签名不正确');
                }
            }catch (\Exception $e)
            {
                var_dump($e->getMessage());
            }
        }

    }


    /**
     * 微信回调 注意 微信生成订单单位是分 需要*100
     * @RequestMapping(path="wxpay",methods="post,get")
     */
    public function wxpay(RequestInterface $request)
    {
        $content = $request->getBody()->getContents();
        if(!empty($content))
        {
            //记录日志
            try {
                $data = $this->wx_pay->verify($content);
                if($data['return_code'] == 'SUCCESS' && $data['result_code'] == 'SUCCESS')
                {
                    $ordersn = $data['out_trade_no'];
                    $money   = $data['total_fee'] / 100;
                    DsOrder::paySuccess($ordersn,$money);
                }else{
                    var_dump('微信支付回调失败');
                }
            }catch (\Exception $e)
            {
                var_dump($e->getMessage());
            }
        }

    }

    /**
     * 微信回调 注意 微信生成订单单位是分 需要*100
     * @RequestMapping(path="off_wxpay",methods="post,get")
     */
    public function off_wxpay(RequestInterface $request)
    {
        $content = $request->getBody()->getContents();
        if(!empty($content))
        {
            //记录日志
            try {
                $data = $this->wx_pay->verify($content);
                if($data['return_code'] == 'SUCCESS' && $data['result_code'] == 'SUCCESS')
                {
                    $ordersn = $data['out_trade_no'];
                    $money   = $data['total_fee'] / 100;
                    DsOfflineOrder::paySuccess($ordersn,$money);
                }else{
                    var_dump('微信支付回调失败');
                }
            }catch (\Exception $e)
            {
                var_dump($e->getMessage());
            }
        }

    }
}