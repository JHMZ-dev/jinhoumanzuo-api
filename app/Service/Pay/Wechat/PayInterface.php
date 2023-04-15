<?php


namespace App\Service\Pay\Wechat;


interface PayInterface
{
    public function pay(array $params);
}
