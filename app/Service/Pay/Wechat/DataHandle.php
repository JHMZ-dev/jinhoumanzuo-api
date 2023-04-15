<?php


namespace App\Service\Pay\Wechat;

use GuzzleHttp\Client;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Guzzle\ClientFactory;
use Hyperf\Redis\RedisFactory;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Str;
use Psr\Container\ContainerInterface;

class DataHandle
{
    protected $config;
    protected $http;
    protected $api_url;
    public function __construct(ContainerInterface $container)
    {
        $redis = ApplicationContext::getContainer()->get(RedisFactory::class)->get('db0');
        $wx_app_id          = $redis->get('wx_app_id');
        $url                = $redis->get('url');
        $wx_notify_url      = $redis->get('wx_notify_url');
        $wx_mch_id          = $redis->get('wx_mch_id');
        $wx_key             = $redis->get('wx_key');
        $wx_app_secret      = $redis->get('wx_app_secret');

        $this->config = [
            'app_id'            => $wx_app_id,
            'mode'              => 'normal',
            'notify_url'        => $url.$wx_notify_url,
            'return_url'        => '',
            'app_secret'        => $wx_app_secret,
            'mch_id'            => $wx_mch_id,
            'key'               => $wx_key,
            'cert'              => BASE_PATH.'/app/Service/Pay/Wechat/cert/apiclient_cert.pem',
            'ssl_key'           => BASE_PATH.'/app/Service/Pay/Wechat/cert/apiclient_key.pem',
        ];
        $this->api_url = $this->config['mode'] === 'dev' ? 'https://api.mch.weixin.qq.com/sandboxnew/':'https://api.mch.weixin.qq.com/';
        $this->http = $container->get(ClientFactory::class)->create();
    }

    public function clientHttp():Client
    {
        return ApplicationContext::getContainer()->get(ClientFactory::class)->create();
    }
    public  function HandlePayData($prepay_id,$appid){
        $pay = [];
        $pay['appId'] = $appid;
        $pay['timeStamp'] = strval(time());
        $pay['nonceStr'] = Str::random(30);
        $pay['package'] = 'prepay_id='.$prepay_id;
        $pay['signType'] = 'MD5';
        $pay['paySign'] = $this->PaySign($pay);
        return $pay;
    }

    public  function PaySign(array $data){
        $origin = [];
        ksort($data);
        foreach ($data as $key => $value) {
            if($value === '' || $value === null || $key == 'sign'){
                continue;
            }
            $origin[] = $key . '=' . $value;
        }
        $originSign = implode('&', $origin);
        $md5SignString = $originSign . '&key=' . $this->config['key'];
        $string = md5($md5SignString);
        return strtoupper($string);
    }

    public  function decryptRefundContents($contents): string
    {
        return openssl_decrypt(
            base64_decode($contents),
            'AES-256-ECB',
            md5($this->config['key']),
            OPENSSL_RAW_DATA
        );
    }

    public  function sign(array $rawData)
    {
        unset($rawData['sign']);
        ksort($rawData);
        $origin = [];
        foreach ($rawData as $key => $value) {
            $origin[] = $key . '=' . $value;
        }
        $originSign = implode('&', $origin);
        $md5SignString = $originSign . '&key=' . $this->config['key'];
        $string = md5($md5SignString);
        return strtoupper($string);
    }

    public  function toXml(array $data): string
    {
        $xml = '<xml>';
        foreach ($data as $key => $val) {
            $xml .= is_numeric($val) ? '<' . $key . '>' . $val . '</' . $key . '>' :
                '<' . $key . '><![CDATA[' . $val . ']]></' . $key . '>';
        }
        $xml .= '</xml>';

        return $xml;
    }

    public  function fromXml($xml)
    {
        libxml_disable_entity_loader(true);
        return json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA), JSON_UNESCAPED_UNICODE), true);
    }

    public function request(array $params,string $endpoint,$cert = false){
        $requestData = $this->toXml($params);
        $options['body'] = $requestData;

        if($cert){
            $options['cert'] = $this->config['cert'];
            $options['ssl_key'] = $this->config['ssl_key'];
        }

        $res = $this->clientHttp()->post($this->api_url.$endpoint,$options);

        $response = $res->getBody()->getContents();

        $result = $this->fromXml($response);

        if($result['return_code'] !== "SUCCESS"){
            throw new \Exception($result['return_msg'] ?? $result['retmsg']);
        }
        if($result['result_code'] !== "SUCCESS"){
            throw new \Exception($result['err_code_des']);
        }
        return $result;
    }
}
