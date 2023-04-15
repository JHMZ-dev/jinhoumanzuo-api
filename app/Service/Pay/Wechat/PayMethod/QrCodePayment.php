<?php


namespace App\Service\Pay\Wechat\PayMethod;
use App\Service\Pay\Wechat\PayAbstract;


class QrCodePayment extends PayAbstract
{
    public function pay(array $params)
    {
        $data = [
            'spbill_create_ip' => '127.0.0.1',
            'trade_type' => 'NATIVE',
            'notify_url' => $this->config['notify_url'],
            'sign_type' => 'MD5'
        ];
        $data = array_merge($data, $params);
        $data['sign'] = $this->handle->PaySign($data);
        $res = $this->handle->request($data,'pay/unifiedorder');
        return $res['code_url'];
    }

}
