<?php


namespace App\Service\Pay\Alipay\PayMethod;


use App\Service\Pay\Alipay\PayAbstract;

class UniTransfer extends PayAbstract
{
    function pay(array $payload, array $params)
    {
        $payload['method'] = 'alipay.fund.trans.uni.transfer';
        $payload['biz_content'] = json_encode($params);
        $payload['sign'] = $this->handle->sign($payload);
        return $this->handle->request($payload);
    }

}
