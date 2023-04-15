<?php


namespace App\Service\Pay\Alipay;


use App\Service\Pay\Alipay\PayMethod\AppPayment;
use App\Service\Pay\Alipay\PayMethod\QrCodePayment;
use App\Service\Pay\Alipay\PayMethod\WebPayment;
use App\Service\Pay\Alipay\PayMethod\UniTransfer;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Redis\RedisFactory;
use Hyperf\Utils\ApplicationContext;
use Psr\Container\ContainerInterface;

/**
 * Class Pay
 * @package App\Service\Pay\Alipay
 * @method AppPayment(array $params)
 * @method QrCodePayment(array $params)
 * @method WebPayment(array $params)
 * @method UniTransfer(array $params)
 */
class Pay
{
    protected  $payload;
    protected  $config;
    protected  $hand;

    public function __construct()
    {
        $redis = ApplicationContext::getContainer()->get(RedisFactory::class)->get('db0');
        $ali_app_id         = '2021003171656607';
        $ali_private_key    = $redis->get('ali_private_key');
        $url                = $redis->get('url');
        $ali_notify_url     = '/pay/alipay';
        $ali_public_key     = $redis->get('ali_public_key');

        $this->config = [
                'appid'                 => $ali_app_id,
                'mode'                  => 'normal',
                'notify_url'            => $url.$ali_notify_url,
                'return_url'            => '',
            'ali_public_key'        => BASE_PATH.'/app/Service/Pay/Alipay/cert/zhi_fu_bao_gong_shi.crt',
            'private_key'           => BASE_PATH.'/app/Service/Pay/Alipay/cert/private_key.txt',
            'app_cert_path'         => BASE_PATH.'/app/Service/Pay/Alipay/cert/ying_yong_gong_shi.crt',
            'app_cert_root_path'    => BASE_PATH.'/app/Service/Pay/Alipay/cert/zhi_fu_bao_gen.crt',
        ];
        $this->hand = make(DataHandle::class);
        $this->payload = [
            'app_id' => $this->config['appid'],
            'method' => '',
            'format' => 'JSON',
            'charset' => 'utf-8',
            'sign_type' => 'RSA2',
            'version' => '1.0',
            'notify_url' => $this->config['notify_url'],
            'return_url'=>$this->config['return_url'],
            'timestamp' => date('Y-m-d H:i:s'),
            'sign' => '',
            'biz_content' => ''
        ];
        if (isset($this->config['app_cert_path']) && isset($this->config['app_cert_root_path']))
        {
            $this->payload['app_cert_sn'] = $this->hand->getCertSN();
            $this->payload['alipay_root_cert_sn'] = $this->hand->getRootCertSN();
        }
    }

    /**
     * @param $name
     * @param $arguments
     * @return mixed
     * @throws \Exception
     */
    public function __call($name, $arguments)
    {
        $class = "App\\Service\\Pay\\Alipay\\PayMethod\\" . $name;
        if (class_exists($class)) {
            $pay = make($class);
            if ($pay instanceof PayInterface) {
                return $pay->pay($this->payload, $arguments[0]);
            }
            throw new \Exception("一个错误的支付网关{$name}",10001);
        }
        throw new \Exception("支付方法{$name}不存在",10001);
    }

    public function find($order_id)
    {
        $data = [
            'out_trade_no' => $order_id
        ];
        $requestData = $this->payload;
        $requestData['method'] = 'alipay.trade.query';
        $requestData['biz_content'] = json_encode($data);
        $requestData['sign'] = $this->hand->sign($requestData);
        return $this->hand->request($requestData);
    }

    public function verify(array $data)
    {
        if(isset($data['sign']))
        {
            if($this->hand->verifySign($data))
            {
                return $data;
            }
        }
        return false;
    }

    public function close($order_id)
    {
        $data = [
            'out_trade_no' => $order_id
        ];
        $requestData = $this->payload;
        $requestData['method'] = 'alipay.trade.close';
        $requestData['biz_content'] = json_encode($data);
        $requestData['sign'] = $this->hand->sign($requestData);
        return $this->hand->request($requestData);
    }

    public function refund($order_id, $fee)
    {
        $data = [
            'out_trade_no' => $order_id,
            'refund_amount' => $fee,
            'out_request_no'=>time()
        ];
        $requestData = $this->payload;
        $requestData['method'] = 'alipay.trade.refund';
        $requestData['biz_content'] = json_encode($data);
        $requestData['sign'] = $this->hand->sign($requestData);
        return $this->hand->request($requestData);
    }

    public function transfer(array $config){
        $requestData = $this->payload;
        $requestData['method'] = 'alipay.fund.trans.toaccount.transfer';
        $requestData['biz_content'] = json_encode(array_merge($config,['product_code' => '']));
        $requestData['sign'] = $this->hand->sign($requestData);
        return $this->hand->request($requestData);
    }
}
