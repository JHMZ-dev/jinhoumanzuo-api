<?php


namespace App\Service\Pay\Alipay\PayMethod;


use App\Service\Pay\Alipay\PayAbstract;

class QrCodePayment extends PayAbstract
{
    function pay(array $payload,array $params)
    {
        $payload['method'] = 'alipay.trade.precreate';
        $payload['biz_content'] = json_encode($params,JSON_UNESCAPED_UNICODE);
        $payload['sign'] = $this->handle->sign($payload);
        return $this->handle->request($payload);
    }

}
