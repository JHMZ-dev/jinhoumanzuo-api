<?php


namespace App\Service\Pay\Wechat\PayMethod;
use App\Service\Pay\Wechat\PayAbstract;


class MiniAppPayment extends PayAbstract
{
    /**
     * @param array $params
     *  * [
     * 'body' => $title,
     * 'out_trade_no' => $orders_id,
     * 'total_fee' => $fee * 100,
     * 'openid' => $openid
     * ]
     */
    public function pay($params)
    {
        $params['appid'] = $this->config['miniapp_id'];
        $data = [
            'spbill_create_ip' => '0.0.0.0',
            'trade_type' => 'JSAPI',
            'notify_url' => $this->config['notify_url'],
            'sign_type' => 'MD5'
        ];
        $data = array_merge($data, $params);
        $data['sign'] = $this->handle->PaySign($data);
        $res = $this->handle->request($data,'pay/unifiedorder');
        return $this->handle->HandlePayData($res['prepay_id'],$this->config['miniapp_id']);
    }

}
