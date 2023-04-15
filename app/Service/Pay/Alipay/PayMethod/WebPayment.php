<?php


namespace App\Service\Pay\Alipay\PayMethod;


use App\Service\Pay\Alipay\PayAbstract;

class WebPayment extends PayAbstract
{
    function pay(array $payload, array $params)
    {
        unset($payload['return_url']);
        $requestData = array_merge($params,['product_code'=>'FAST_INSTANT_TRADE_PAY']);
        $payload['method'] = 'alipay.trade.page.pay';
        $payload['biz_content'] = json_encode($requestData);
        $payload['sign'] = $this->handle->sign($payload);
        return $this->handle->getUrl().'?'.http_build_query($payload);
    }

}
