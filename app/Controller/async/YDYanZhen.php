<?php declare(strict_types=1);

namespace App\Controller\async;

use AlibabaCloud\SDK\Cloudauth\V20190307\Cloudauth;

use App\Model\DsAuthError;
use App\Model\DsUser;
use App\Model\DsWyError;
use Darabonba\OpenApi\Models\Config;
use AlibabaCloud\SDK\Cloudauth\V20190307\Models\InitFaceVerifyRequest;
use AlibabaCloud\SDK\Cloudauth\V20190307\Models\DescribeFaceVerifyRequest;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Redis\RedisFactory;
use Hyperf\Guzzle\ClientFactory;
use Hyperf\Utils\Context;
use Swoole\Exception;


/**
 * 易盾验证
 * Class YDYanZhen
 * @package App\Controller
 */
class YDYanZhen
{
    protected $container;
    protected $http;
    protected $captcha_h5 = '2afefb7448d54e68a4dbd4277095f2d7';
    protected $captcha_ios = '527415c6c9124165a138c8f8e4058fcd';
    protected $captcha_android = 'bea1df5a8e7e411583d7b112bc8e25af';
    protected $secret_id = '76f2e9ec5227d45becd0f07d903fbb2b';
    protected $secret_key = 'd1e7fc96d4b81c43c20f85809af2891b';
    protected $version = 'v2';
    protected $api_url = "http://c.dun.163yun.com/api/v2/verify";

    /**
     * YDYanZhen constructor.
     */
    public function __construct()
    {
        $this->container = ApplicationContext::getContainer();
        $this->http   = ApplicationContext::getContainer()->get(ClientFactory::class)->create();
    }

    /**
     * 发起二次校验请求
     * @param $validate //二次校验数据
     * @param $user //用户mobile 方便查询
     * @param $captcha_id //不同类型不同
     */
    public function code_verify($validate, $mobile,$captcha_id)
    {
        $params = array();
        if($captcha_id == 1)
        {
            $params["captchaId"] = $this->captcha_h5;
        }elseif ($captcha_id == 2)
        {
            $params["captchaId"] = $this->captcha_android;
        }else{
            $params["captchaId"] = $this->captcha_ios;
        }

        $params["validate"] = strval($validate);
        $params["user"] = "{'mobile':$mobile}";
        // 公共参数
        $params["secretId"] = $this->secret_id;
        $params["version"] = $this->version;
        $params["timestamp"] = sprintf("%d", round(microtime(true)*1000));// time in milliseconds
        $params["nonce"] = sprintf("%d", rand()); // random int
        $params["signature"] = $this->sign($params);
        try {
            $result = $this->http->post($this->api_url, ['headers' =>['Content-Type' => 'application/x-www-form-urlencoded'], 'form_params' => $params])->getBody()->getContents();
            if(empty($result))
            {
                throw new Exception('您的操作过快，请稍后重试！', 10001);
            }else {
                $re = json_decode($result, true);
                if(!$re['result'])
                {
                    if($re['msg'] == 'OK')
                    {
                        throw new Exception('该token已被验证！请刷新并重新验证！', 10001);
                    }else{
                        throw new Exception('您的图形验证未通过！请刷新并重新验证！', 10001);
                    }
                }
            }
        }catch (\Exception $exception)
        {
            throw new Exception($exception->getMessage(), 10001);
        }
    }

    /**
     * 计算参数签名
     * @param $params //请求参数
     */
    private function sign( $params)
    {
        ksort($params); // 参数排序
        $buff="";
        foreach($params as $key=>$value){
            $buff .=$key;
            $buff .=$value;
        }
        $buff .= $this->secret_key;
        return md5(mb_convert_encoding($buff, "utf8", "auto"));
    }

    /**
     * 生成签名方法
     * 不同openapi的签名参数不同
     * @param $params
     * @return string
     */
    function gen_signature( $params)
    {
        ksort($params);
        $buff="";
        foreach($params as $key=>$value){
            if($value !== null) {
                $buff .=$key;
                $buff .=$value;
            }
        }
        $buff .= $this->secret_key;
        return md5($buff);
    }

    /**
     *
     * @param $validate
     * @param $data
     * @return mixed
     * @throws Exception
     */
    public function huanjing_verify($validate, $data)
    {
        $params = array();
        // 公共参数
        $params["businessId"] = $this->return_business_id($data['business_id']);
        $params["secretId"] = $this->secret_id;
        $params["timestamp"] = time() * 1000;
        $params["nonce"] = 'mmm888f73yyy59440583zzz9bfcc79de'; // random int
        $params["version"] = "500";
        //用户参数
        $params["token"] = strval($validate);
        $params["ip"] = strval($data['ip']);
        $params["roleId"] = strval($data['user_id']);
        $params["account"] = strval($data['user_id']);
        $params["phone"] = strval($data['mobile']);
        if(!empty($data['reg_ip']))
        {
            $params["registerIp"] = strval($data['reg_ip']);
        }

        $params["signature"] = $this->gen_signature($params);
        $params = $this->to_utf8($params);

        try {
//            $result = $this->http->post('http://ir-open.dun.163.com/v5/risk/check',
//                [
//                    'headers' =>['Content-Type' => 'application/json',],
//                    'body' => json_encode($params)
//                ])->getBody()->getContents();
            $result = $this->wy_curl_post('http://ir-open.dun.163.com/v5/risk/check',$params);
            if(empty($result))
            {
                throw new Exception('您的操作过快，请稍后重试！', 10001);
            }else {
                $re = json_decode($result, true);
                var_dump($re);
                if(!empty($re['code']))
                {
                    if($re['code'] != 200)
                    {
                        throw new Exception($re['msg'], 10001);
                    }else{
                        if($re['data']['riskLevel'] == 2)
                        {
                            //中风险 记录日志
                            $datac = [
                                'user_id'   => $data['user_id'],
                                'mobile'   => $data['mobile'],
                                'riskLevel'   => $re['data']['riskLevel'],
                                'ip'   => $data['ip'],
                                'taskId'   => $re['data']['taskId'],
                                'time'  => time(),
                            ];
                            if(!empty($re['data']['deviceId']))
                            {
                                $datac['deviceId'] = $re['data']['deviceId'];
                            }
                            if(!empty($re['data']['deviceInfo']))
                            {
                                $datac['deviceInfo'] = json_encode($re['data']['deviceInfo']);
                            }
                            if(!empty($re['data']['matchedRules']))
                            {
                                $datac['matchedRules'] = json_encode($re['data']['matchedRules']);
                            }
                            if(!empty($re['data']['hitInfos']))
                            {
                                $datac['hitInfos'] = json_encode($re['data']['hitInfos']);
                            }
                            if(!empty($re['data']['sdkRespData']))
                            {
                                $datac['sdkRespData'] = $re['data']['sdkRespData'];
                            }
                            DsWyError::query()->insert($datac);
                            throw new Exception('满座放大镜监测到您当前设备环境异常，请更换设备后重试！', 10001);
                        }
                        if($re['data']['riskLevel'] == 3)
                        {
                            //高风险 记录日志
                            $datac = [
                                'user_id'   => $data['user_id'],
                                'mobile'   => $data['mobile'],
                                'riskLevel'   => $re['data']['riskLevel'],
                                'ip'   => $data['ip'],
                                'taskId'   => $re['data']['taskId'],
                                'time'  => time(),
                            ];
                            if(!empty($re['data']['deviceId']))
                            {
                                $datac['deviceId'] = $re['data']['deviceId'];
                            }
                            if(!empty($re['data']['deviceInfo']))
                            {
                                $datac['deviceInfo'] = json_encode($re['data']['deviceInfo']);
                            }
                            if(!empty($re['data']['matchedRules']))
                            {
                                $datac['matchedRules'] = json_encode($re['data']['matchedRules']);
                            }
                            if(!empty($re['data']['hitInfos']))
                            {
                                $datac['hitInfos'] = json_encode($re['data']['hitInfos']);
                            }
                            if(!empty($re['data']['sdkRespData']))
                            {
                                $datac['sdkRespData'] = $re['data']['sdkRespData'];
                            }
                            DsWyError::query()->insert($datac);
                            throw new Exception('满座放大镜监测到您当前设备环境存在巨大风险，请更换设备后重试！', 10001);
                        }
                        return $re['data']['deviceId'];
                    }
                }else{
                    throw new Exception('您的操作过快，请重新提交验证！', 10001);
                }
            }
        }catch (\Exception $exception)
        {
            throw new Exception($exception->getMessage(), 10001);
        }
    }

    protected function wy_curl_post($url,$params)
    {
        $ch = curl_init();
        $json = json_encode($params);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        // 设置超时时间
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        // POST数据
        curl_setopt($ch, CURLOPT_POST, 1);
        // 把post的变量加上
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:'.'application/json'));
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }

    /**
     * multipart/form-data
     * @param $param
     * @return array
     */
    protected function formPost($param)
    {
        $multipart = [];
        foreach ($param as $k => $v) {
            $multipart[] = [
                'name' => $k,
                'contents' => $v,
            ];
        }
        return $multipart;
    }

    /**
     * 将输入数据的编码统一转换成utf8
     */
    protected function to_utf8($params)
    {
        $utf8s = array();
        foreach ($params as $key => $value) {
            $utf8s[$key] = is_string($value) ? mb_convert_encoding($value, "utf8", "auto") : $value;
        }
        return $utf8s;
    }

    protected function return_business_id($type)
    {
        switch ($type)
        {
            case 1:#H5注册保护
                return '8102d5090dadb497ed27c9800a7a24cc';
                break;
            case 2:#android登录保护
                return 'fdf61bc628a4c47f5b05226385f23a05';
                break;
            case 3:#iOS注册登录保护
                return '93b85dda0419c8daa3f13d1ef33595c2';
                break;
            case 4:#android影视资源整合
                return '656eb08e9b53874811c86b6f33ea850c';
                break;
            case 5:#iOS影视资源整合
                return '7356d1b25409229d32cece1593708872';
                break;
        }
    }
}
