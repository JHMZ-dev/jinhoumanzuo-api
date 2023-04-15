<?php


namespace App\Service\Pay\Wechat\PayMethod;
use App\Service\Pay\Wechat\PayAbstract;


class OfficialPayment extends PayAbstract
{
    public function pay(array $params)
    {
        $data = [
            'spbill_create_ip' => '183.150.136.188',
            'trade_type' => 'JSAPI',
            'notify_url' => $this->config['notify_url'],
            'sign_type' => 'MD5'
        ];
        $data = array_merge($data, $params);
        $data['sign'] = $this->handle->PaySign($data);
        return $res = $this->handle->request($data,'pay/unifiedorder');
    }

}
