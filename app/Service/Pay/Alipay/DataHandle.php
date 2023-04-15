<?php


namespace App\Service\Pay\Alipay;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Guzzle\ClientFactory;
use Hyperf\Redis\RedisFactory;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Str;
use Psr\Container\ContainerInterface;

class DataHandle
{
    protected  $api_url;
    protected  $config;
    protected  $http;
    public function __construct(ContainerInterface $container)
    {
        $redis = ApplicationContext::getContainer()->get(RedisFactory::class)->get('db0');
        $ali_app_id         = '2021003171656607';
        $ali_private_key    = $redis->get('ali_private_key');
        $url                = $redis->get('url');
        $ali_notify_url     = '/pay/alipay';
        $ali_public_key     = $redis->get('ali_public_key');

        $this->config = [
            'appid'             => $ali_app_id,
            'mode'              => 'normal',
            'notify_url'        => $url.$ali_notify_url,
            'return_url'        => '',
            'ali_public_key'        => BASE_PATH.'/app/Service/Pay/Alipay/cert/zhi_fu_bao_gong_shi.crt',
            'private_key'           => BASE_PATH.'/app/Service/Pay/Alipay/cert/private_key.txt',
            'app_cert_path'         => BASE_PATH.'/app/Service/Pay/Alipay/cert/ying_yong_gong_shi.crt',
            'app_cert_root_path'    => BASE_PATH.'/app/Service/Pay/Alipay/cert/zhi_fu_bao_gen.crt',
        ];

        $this->api_url =  $this->config['mode'] == 'dev' ? 'https://openapi.alipaydev.com/gateway.do' : 'https://openapi.alipay.com/gateway.do';
        $this->auth_url = $this->config['mode'] == 'dev' ? 'https://openauth.alipaydev.com/oauth2/publicAppAuthorize.htm' : 'https://openauth.alipay.com/oauth2/publicAppAuthorize.htm';
        $this->api_url .= "?charset=utf-8";
        $this->http = $container->get(ClientFactory::class)->create();
    }

    public function clientHttp()
    {
        return $this->http;
    }

    public function getUrl()
    {
        return $this->api_url;
    }

    public function getAuthUrl()
    {
        return $this->auth_url;
    }

    public function request(array $params)
    {
        $res = $this->clientHttp()->post($this->api_url, [
            'form_params' => $params,
        ]);

        $content = $res->getBody()->getContents();
//        logs('ali_pay_info')->info($content);
        $result = json_decode($content, true);
        return $this->verifyResult($params, $result);
    }

    public function verifyResult($params, $res)
    {
        if (isset($res['error_response'])) {
            throw new \Exception($res['error_response']['sub_msg']);
        }
        $method = str_replace('.', '_', $params['method']) . '_response';
        if ($method == 'alipay_system_oauth_token_response' && isset($res[$method]['access_token'])) {
            return $res[$method];
        }
        if (!isset($res['sign']) || $res[$method]['code'] != '10000') {
            throw new \Exception($res[$method]['sub_msg'] . ";err_code:" . $res[$method]['code'],$res[$method]['code']);
        }

        if ($this->verifySign($res, true, $res['sign'])) {
            throw new \Exception("支付宝返回数据异常:" . json_encode($res, JSON_UNESCAPED_UNICODE));
        }

        return $res[$method];
    }

    public function verifySign(array $data, $sync = false, $sign = null)
    {
        $publicKey = $this->getPublicKey();
        $sign = $sign ?? $data['sign'];
        $toVerify = $sync ? json_encode($data, JSON_UNESCAPED_UNICODE) : $this->getSignContent($data, true);
        $isVerify = openssl_verify($toVerify, base64_decode($sign), $publicKey, OPENSSL_ALGO_SHA256) === 1;

        return $isVerify;
    }

    public function sign(array $params)
    {
        $sign = "";
        $privateKey = $this->getPrivateKey();
        openssl_sign($this->getSignContent($params), $sign, $privateKey, OPENSSL_ALGO_SHA256);
        return base64_encode($sign);
    }

    public function getSignContent(array $data, $verify = false)
    {
        ksort($data);
        $stringToBeSigned = '';
        foreach ($data as $k => $v) {
            if ($verify && $k != 'sign' && $k != 'sign_type') {
                $stringToBeSigned .= $k . '=' . $v . '&';
            }
            if (!$verify && $v !== '' && !is_null($v) && $k != 'sign' && '@' != substr($v, 0, 1)) {
                $stringToBeSigned .= $k . '=' . $v . '&';
            }
        }
        return trim($stringToBeSigned, '&');
    }

    public function getPrivateKey()
    {
        $privateKey = $this->config['private_key'];
        if (is_file($privateKey)) {
            $privateKeyContent = file_get_contents($privateKey);
            $privateKeyContent = str_replace([" ", "　", "\t", "\n", "\r"], '', $privateKeyContent);

            return "-----BEGIN RSA PRIVATE KEY-----\n" .
                wordwrap($privateKeyContent, 64, "\n", true) .
                "\n-----END RSA PRIVATE KEY-----";
        } else {
            return "-----BEGIN RSA PRIVATE KEY-----\n" .
                wordwrap($privateKey, 64, "\n", true) .
                "\n-----END RSA PRIVATE KEY-----";
        }
    }

    public function getPublicKey()
    {
        $publicKey = $this->config['ali_public_key'];
        if (Str::endsWith($publicKey, '.crt')) {
            $publicKey = file_get_contents($publicKey);
        } else {
            $publicKey = "-----BEGIN PUBLIC KEY-----\n" .
                wordwrap($publicKey, 64, "\n", true) .
                "\n-----END PUBLIC KEY-----";
        }
        return $publicKey;
    }

    public function getCertSN()
    {
        $certPath = $this->config['app_cert_path'];
        $cert = file_get_contents($certPath);
        $ssl = openssl_x509_parse($cert);
        $SN = md5($this->array2string(array_reverse($ssl['issuer'])) . $ssl['serialNumber']);
        return $SN;
    }

    public function getRootCertSN()
    {
        $certPath = $this->config['app_cert_root_path'];
        if (!is_file($certPath)) {
            throw new \Exception('unknown certPath -- [getRootCertSN]');
        }
        $x509data = file_get_contents($certPath);
        if (false === $x509data) {
            throw new \Exception('Alipay CertSN Error -- [getRootCertSN]');
        }
        $kCertificateEnd = '-----END CERTIFICATE-----';
        $certStrList = explode($kCertificateEnd, $x509data);
        $md5_arr = [];
        foreach ($certStrList as $one) {
            if (!empty(trim($one))) {
                $_x509data = $one . $kCertificateEnd;
                openssl_x509_read($_x509data);
                $_certdata = openssl_x509_parse($_x509data);
                if (in_array($_certdata['signatureTypeSN'], ['RSA-SHA256', 'RSA-SHA1'])) {
                    $issuer_arr = [];
                    foreach ($_certdata['issuer'] as $key => $val) {
                        $issuer_arr[] = $key . '=' . $val;
                    }
                    $_issuer = implode(',', array_reverse($issuer_arr));
                    if (0 === strpos($_certdata['serialNumber'], '0x')) {
                        $serialNumber = self::bchexdec($_certdata['serialNumber']);
                    } else {
                        $serialNumber = $_certdata['serialNumber'];
                    }
                    $md5_arr[] = md5($_issuer . $serialNumber);
//                    logs()->debug('getRootCertSN Sub:', [$certPath, $_issuer, $serialNumber]);
                }
            }
        }

        return implode('_', $md5_arr);
    }


    private static function bchexdec($hex)
    {
        $dec = 0;
        $len = strlen($hex);
        for ($i = 1; $i <= $len; ++$i) {
            if (ctype_xdigit($hex[$i - 1])) {
                $dec = bcadd($dec, bcmul(strval(hexdec($hex[$i - 1])), bcpow('16', strval($len - $i))));
            }
        }

        return str_replace('.00', '', $dec);
    }

    function array2string($array)
    {
        $string = [];
        if ($array && is_array($array)) {
            foreach ($array as $key => $value) {
                $string[] = $key . '=' . $value;
            }
        }
        return implode(',', $string);
    }
}
