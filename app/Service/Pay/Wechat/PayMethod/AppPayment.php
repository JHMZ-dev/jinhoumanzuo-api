<?php


namespace App\Service\Pay\Wechat\PayMethod;

use App\Service\Pay\Wechat\PayAbstract;
use Hyperf\Utils\Str;

class AppPayment extends PayAbstract
{
    public function pay(array $params)
    {
         $params['appid'] = $this->config['app_id'];

        $data = [
            'spbill_create_ip' => '0.0.0.0',
            'trade_type' => 'APP',
            'notify_url' => $this->config['notify_url'],
            'sign_type' => 'MD5'
        ];
        $data = array_merge($data, $params);


        $data['sign'] = $this->handle->PaySign($data);
        $appPayInfo = $this->handle->request($data,'pay/unifiedorder');

        $pay_request = [
            'appid' => $this->config['app_id'],
            'partnerid' => $this->config['mch_id'],
            'prepayid' => $appPayInfo['prepay_id'],
            'timestamp' => strval(time()),
            'noncestr' => Str::random(),
            'package' => 'Sign=WXPay',
        ];

        $pay_request['sign'] = $this->handle->PaySign($pay_request);

        return $pay_request;
    }

}