<?php


namespace App\Service\Pay\Wechat;

use App\Service\Pay\Wechat\PayMethod\AppPayment;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Redis\RedisFactory;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Str;
use Psr\Container\ContainerInterface;
use App\Service\Pay\Wechat\PayMethod\MiniAppPayment;
use App\Service\Pay\Wechat\PayMethod\OfficialPayment;
use App\Service\Pay\Wechat\PayMethod\QrCodePayment;

/**
 * Class Pay
 * @package App\Service\Pay\Wechat
 * @method MiniAppPayment(array $config) 小程序支付
 * @method OfficialPayment(array $config) 公众号支付
 * @method QrCodePayment(array $config) 扫码支付
 * @method AppPayment(array $config) app支付
 * @method H5Payment(array $config) H5支付
 */
class Pay
{
    protected $payload;
    protected $config;
    protected $handle;

    public function __construct(ContainerInterface $container)
    {
        $redis = ApplicationContext::getContainer()->get(RedisFactory::class)->get('db0');
        $wx_app_id          = $redis->get('wx_app_id');
        $url                = $redis->get('url');
        $wx_notify_url      = $redis->get('wx_notify_url');
        $wx_mch_id          = $redis->get('wx_mch_id');
        $wx_key             = $redis->get('wx_key');
        $wx_app_secret      = $redis->get('wx_app_secret');

        $this->config = [
            'app_id'            => $wx_app_id,
            'miniapp_id'        => 'wxb92320adc3c7bd59',
            'mode'              => 'normal',
            'notify_url'        => $url.$wx_notify_url,
            'return_url'        => '',
            'app_secret'        => $wx_app_secret,
            'mch_id'            => $wx_mch_id,
            'key'               => $wx_key,
        ];
        $this->payload = [
            'appid'            => $this->config['app_id'],
            'mch_id'           => $this->config['mch_id'],
            'nonce_str'        => Str::random(),
            'sign'             => '',
        ];
        $this->handle = make(DataHandle::class);
    }

    public function __call($name, $arguments)
    {
        $pay = $this->makePay($name);
        return $this->pay($pay, $arguments[0]);

    }

    private function pay($pay, $arguments)
    {
        $config = array_merge($this->payload, $arguments);
        return $pay->pay($config);
    }

    private function makePay($className): PayInterface
    {
        $class = 'App\\Service\\Pay\\Wechat\\PayMethod\\' . $className;
        if (class_exists($class)) {
            $pay = make($class);
            if ($pay instanceof PayInterface) {
                return $pay;
            }
            throw new \Exception($className . '未实现PaymentInterface方法');
        }
        throw new \Exception($className . '方法不存在');
    }

    public function find($order_id)
    {
        $data = [
            'out_trade_no' => $order_id,
        ];
        $requestData = array_merge($this->payload, $data);
        $requestData['sign'] = $this->handle->PaySign($requestData);
        return $this->handle->request($requestData, 'pay/orderquery');
    }

    public function close($order_id)
    {
        $data = [
            'out_trade_no' => $order_id,
        ];
        $requestData = array_merge($this->payload, $data);
        $requestData['sign'] = $this->handle->PaySign($requestData);
        return $this->handle->request($requestData, 'pay/closeorder');
    }

    public function refund($order_id, $refund_id, $fee, $refund_fee = null)
    {
        $refund_fee = empty($refund_fee) ? $fee : $refund_fee;
        $data = [
            'out_trade_no' => $order_id,
            'out_refund_no' => $refund_id,
            'total_fee' => $fee * 100,
            'refund_fee' => $refund_fee * 100,
        ];
        $requestData = array_merge($this->payload, $data);
        $requestData['sign'] = $this->handle->PaySign($requestData);
        return $this->handle->request($requestData, 'secapi/pay/refund', true);
    }

    public function refundQuery($order_id, $refund_id = null)
    {
        $data = [
            'out_trade_no' => $order_id,
            'out_refund_no' => $order_id . 'rf'
        ];

        if ($refund_id) {
            $data['out_refund_no'] = $refund_id;
        }

        $requestData = array_merge($this->payload, $data);
        $requestData['sign'] = $this->handle->PaySign($requestData);
        return $this->handle->request($requestData, 'pay/refundquery');
    }

    public function verify($content = '', bool $refund = false)
    {
        $data = $this->handle->fromXml($content);
        if ($data === false) {
            throw new \Exception("数据格式错误");
        }
        if ($refund) {
            $decrypt_data = $this->handle->decryptRefundContents($data['req_info']);
            $data = array_merge($this->handle->fromXml($decrypt_data), $data);
        }

        if ($refund || $this->handle->PaySign($data) === $data['sign']) {
            return $data;
        }
    }

    public function transfer(array $config)
    {
        $payload = [
            'mch_appid' => $this->config['app_id'],
            'mchid' => $this->config['mch_id'],
            'nonce_str' => Str::random(32),
            'spbill_create_ip' => '0.0.0.0'
        ];
        $data = array_merge($config, $payload);
        $data['sign'] = $this->handle->PaySign($data);
        return $this->handle->request($data, 'mmpaymkttransfers/promotion/transfers', true);
    }

    public function transfer_mini(array $config)
    {
        $payload = [
            'mch_appid' => $this->config['miniapp_id'],
            'mchid' => $this->config['mch_id'],
            'nonce_str' => Str::random(32),
            'spbill_create_ip' => '0.0.0.0'
        ];
        $data = array_merge($config, $payload);
        $data['sign'] = $this->handle->PaySign($data);
        return $this->handle->request($data, 'mmpaymkttransfers/promotion/transfers', true);
    }
    public function success()
    {
        return <<<EOT
<xml>
  <return_code><![CDATA[SUCCESS]]></return_code>
  <return_msg><![CDATA[OK]]></return_msg>
</xml>
EOT;
    }

}
