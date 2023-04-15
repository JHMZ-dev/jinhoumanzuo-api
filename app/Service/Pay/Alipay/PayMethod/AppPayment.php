<?php


namespace App\Service\Pay\Alipay\PayMethod;


use App\Service\Pay\Alipay\PayAbstract;

class AppPayment extends PayAbstract
{
    function pay(array $payload,array $params)
    {
        unset($payload['return_url']);
        $requestData = array_merge($params,['product_code' => 'QUICK_MSECURITY_PAY']);
        //$requestData = $params;
        $payload['method'] = 'alipay.trade.app.pay';
        $payload['biz_content'] = json_encode($requestData);
        $payload['sign'] = $this->handle->sign($payload);
        return http_build_query($payload);
    }
}
