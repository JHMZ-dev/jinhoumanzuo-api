<?php

$wx_app_id          = 'wx42f418764335c123';
$wx_app_secret      = '2f49efdb23917ad82e1858f95a872bed';
$url                = 'https://apiok.ewjyw.com';
$wx_notify_url      = '/pay/wxpay';
$wx_mch_id          = '1603809248';
$wx_key             = 'AAAAaaaaBBBBbbbbCCCCccccDDDDdddd';

return [
    'wechat' => [
        'appid' => $wx_app_id,
        'app_id' => $wx_app_id,
        'miniapp_id' => $wx_mch_id,
        'mch_id' => $wx_mch_id,
        'key' => $wx_key,
        'notify_url' => $url.$wx_notify_url,
        'notify_url_refund' => $url.$wx_notify_url,
        'mode' => env('WECHAT_PAY_MODE', 'normal'), // dev沙箱模式 normal 普通
        'cert' => BASE_PATH . '/storage/wxSsl/apiclient_cert.pem',
        'ssl_key' => BASE_PATH . '/storage/wxSsl/apiclient_key.pem',
        'server_ip' => ''
    ],
    'alipay' => [
        'pid' => '',
        'appid' => '',
        'mode' => 'prod',
        'notify_url' => env('DOMAIN') . '/callback/ali_pay/pay',
        'return_url' => env('DOMAIN') . '/callback/alipay/return',
        'ali_public_key' => BASE_PATH . '/storage/alipay/alipayCertPublicKey_RSA2.crt',
        'private_key' => BASE_PATH . '/storage/alipay/private_key.text',
        'app_cert_path' => BASE_PATH . '/storage/alipay/appCertPublicKey_2021001146677128.crt',
        'app_cert_root_path' => BASE_PATH . '/storage/alipay/alipayRootCert.crt'
    ],
    'request' => [
        'timeout' => 5.0
    ]
];