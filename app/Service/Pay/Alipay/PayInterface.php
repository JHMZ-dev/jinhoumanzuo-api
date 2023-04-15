<?php


namespace App\Service\Pay\Alipay;


interface PayInterface
{
    function pay(array $payload,array $params);
}
