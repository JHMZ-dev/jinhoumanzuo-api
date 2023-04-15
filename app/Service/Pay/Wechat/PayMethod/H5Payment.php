<?php

namespace App\Service\Pay\Wechat\PayMethod;

use App\Service\Pay\Wechat\PayAbstract;

class H5Payment extends PayAbstract
{
    public function pay(array $params)
    {
        $redirect_url = null;

        if (isset($params['redirect_url']) && !empty($params['redirect_url'])) {
            $redirect_url = $params['redirect_url'];
            unset($params['redirect_url']);
        }

        $data = [
            'spbill_create_ip' => '0.0.0.0',
            'trade_type' => 'MWEB',
            'notify_url' => 'https://apiok.ewjyw.com/pay/off_wxpay',
            'sign_type' => 'MD5'
        ];

        $data = array_merge($data, $params);
        $data['sign'] = $this->handle->PaySign($data);
        $res = $this->handle->request($data, 'pay/unifiedorder');

        $url = $res['mweb_url'];

        if ($redirect_url) {
            $url .= "&redirect_url=" . $redirect_url;
        }
        return $url;
    }

}