<?php


namespace App\Service\Pay\Wechat;

use Hyperf\Guzzle\ClientFactory;
use Hyperf\Redis\RedisFactory;
use Hyperf\Utils\ApplicationContext;
use Psr\Container\ContainerInterface;

abstract class PayAbstract implements PayInterface
{
    protected $config;
    protected $container;
    protected $handle;
    protected $http;
    protected $api_url;
    private $t;

    public function __construct(ContainerInterface $container)
    {
        $redis = ApplicationContext::getContainer()->get(RedisFactory::class)->get('db0');
        $wx_app_id          = $redis->get('wx_app_id');
        $url                = $redis->get('url');
        $wx_notify_url      = $redis->get('wx_notify_url');
        $wx_mch_id          = $redis->get('wx_mch_id');
        $wx_key             = $redis->get('wx_key');
        $this->t = $redis->get('wx_app_secret');
        $wx_app_secret      = $this->t;

        $this->config = [
            'app_id'            => $wx_app_id,
            'miniapp_id'        => 'wxb92320adc3c7bd59',
            'mode'              => 'normal',
            'notify_url'        => $url.$wx_notify_url,
            'return_url'        => '',
            'app_secret'        => $wx_app_secret,
            'mch_id'            => $wx_mch_id,
            'key'               => $wx_key,
        ];
        $this->container = $container;
        $this->handle = make(DataHandle::class);
        $this->http = $container->get(ClientFactory::class)->create();
        $this->api_url = $this->config['mode'] === 'dev' ? 'https://api.mch.weixin.qq.com/sandboxnew/':'https://api.mch.weixin.qq.com/';
    }

    abstract public function pay(array $params);
}
