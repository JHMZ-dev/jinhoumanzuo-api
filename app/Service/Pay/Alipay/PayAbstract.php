<?php


namespace App\Service\Pay\Alipay;


use Psr\Container\ContainerInterface;

abstract class PayAbstract implements PayInterface
{
    protected  $handle;
    public function __construct(ContainerInterface $container)
    {
        $this->handle = make(DataHandle::class);
    }

    abstract function pay(array $payload,array $params);
}
