<?php declare(strict_types=1);

namespace App\Controller;

use Hyperf\Redis\RedisFactory;
use Hyperf\Utils\ApplicationContext;

/**
 * 阿里云发送短信
 * Class AliCmsSend
 * @package App\Controller
 */
class AliCmsSend
{

    // 保存错误信息

    public $error;

    // Access Key ID

    private $accessKeyId = '';

    // Access Access Key Secret

    private $accessKeySecret = '';

    // 签名

    private $signName = '';

    public function __construct()
    {
        // 配置参数
        $this->accessKeyId = 'LTAI5tSdy2L2bNaVezVCK4Nx';

        $this->accessKeySecret = '6bxKcyY4gOdxSnu3QjU2y73cXav8wO';

        $this->signName = '满座影业';
    }

    /**
     * @param $string
     * @return string|string[]|null
     */
    private function percentEncode($string)
    {

        $string = urlencode ( strval($string) );

        $string = preg_replace ( '/\+/', '%20', $string );

        $string = preg_replace ( '/\*/', '%2A', $string );

        $string = preg_replace ( '/%7E/', '~', $string );

        return $string;

    }

    /*
     * 签名
     */
    private function computeSignature($parameters, $accessKeySecret)
    {

        ksort ( $parameters );

        $canonicalizedQueryString = '';

        foreach ( $parameters as $key => $value ) {

            $canonicalizedQueryString .= '&' . $this->percentEncode ( $key ) . '=' . $this->percentEncode ( $value );

        }

        $stringToSign = 'GET&%2F&' . $this->percentencode ( substr ( $canonicalizedQueryString, 1 ) );

        $signature = base64_encode ( hash_hmac ( 'sha1', $stringToSign, $accessKeySecret . '&', true ) );

        return $signature;

    }

    /**
     * 注册接口
     * @param $mobile
     * @param $cont
     * @return bool
     */
    public function send_maichu($mobile, $cont)
    {
        $params = array (   //此处作了修改

            'SignName' => $this->signName,

            'Format' => 'JSON',

            'Version' => '2017-05-25',

            'AccessKeyId' => $this->accessKeyId,

            'SignatureVersion' => '1.0',

            'SignatureMethod' => 'HMAC-SHA1',

            'SignatureNonce' => uniqid (),

            'Timestamp' => gmdate ( 'Y-m-d\TH:i:s\Z' ),

            'Action' => 'SendSms',

            'TemplateCode' => 'SMS_215225233',  //模板id

            'PhoneNumbers' => $mobile,

            'TemplateParam' => json_encode($cont),
        );

        //var_dump($params);die;

        // 计算签名并把签名结果加入请求参数

        $params ['Signature'] = $this->computeSignature ( $params, $this->accessKeySecret );

        // 发送请求（此处作了修改）
        $url = 'http://dysmsapi.aliyuncs.com/?' . http_build_query ( $params );

        $ch = curl_init ();

        curl_setopt ( $ch, CURLOPT_URL, $url );

        curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, FALSE );

        curl_setopt ( $ch, CURLOPT_SSL_VERIFYHOST, FALSE );

        curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );

        curl_setopt ( $ch, CURLOPT_TIMEOUT, 10 );

        $result = curl_exec ( $ch );

        curl_close ( $ch );

        $result = json_decode ( $result, true );

        if(isset($result['Code']))
        {
            if($result['Code'] == 'OK')
            {
                return true;
            }else{
                return false;
            }
        }else{
            return false;
        }
    }
    /**
     * 注册接口
     * @param $mobile
     * @param $cont
     * @return bool
     */
    public function send_register($mobile, $cont)
    {
        $params = array (   //此处作了修改

            'SignName' => $this->signName,

            'Format' => 'JSON',

            'Version' => '2017-05-25',

            'AccessKeyId' => $this->accessKeyId,

            'SignatureVersion' => '1.0',

            'SignatureMethod' => 'HMAC-SHA1',

            'SignatureNonce' => uniqid (),

            'Timestamp' => gmdate ( 'Y-m-d\TH:i:s\Z' ),

            'Action' => 'SendSms',

            'TemplateCode' => 'SMS_268605045',  //模板id

            'PhoneNumbers' => $mobile,

            'TemplateParam' => json_encode($cont),
        );

        //var_dump($params);die;

        // 计算签名并把签名结果加入请求参数

        $params ['Signature'] = $this->computeSignature ( $params, $this->accessKeySecret );

        // 发送请求（此处作了修改）
        $url = 'http://dysmsapi.aliyuncs.com/?' . http_build_query ( $params );

        $ch = curl_init ();

        curl_setopt ( $ch, CURLOPT_URL, $url );

        curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, FALSE );

        curl_setopt ( $ch, CURLOPT_SSL_VERIFYHOST, FALSE );

        curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );

        curl_setopt ( $ch, CURLOPT_TIMEOUT, 10 );

        $result = curl_exec ( $ch );

        curl_close ( $ch );

        $result = json_decode ( $result, true );

        if(isset($result['Code']))
        {
            if($result['Code'] == 'OK')
            {
                return true;
            }else{
                return false;
            }
        }else{
            return false;
        }
    }
    /**
     * 国际注册接口
     * @param $mobile
     * @param $cont
     * @param $co
     * @return bool
     */
    public function send_register_guoji($mobile, $cont,$co)
    {
        $params = array (   //此处作了修改

            'SignName' => '亿网嘉元信息技术有限公司',

            'Format' => 'JSON',

            'Version' => '2017-05-25',

            'AccessKeyId' => $this->accessKeyId,

            'SignatureVersion' => '1.0',

            'SignatureMethod' => 'HMAC-SHA1',

            'SignatureNonce' => uniqid (),

            'Timestamp' => gmdate ( 'Y-m-d\TH:i:s\Z' ),

            'Action' => 'SendSms',

            'TemplateCode' => 'SMS_208580973',  //模板id

            'PhoneNumbers' => $co.$mobile,

            'TemplateParam' => json_encode($cont),
        );

        //var_dump($params);die;

        // 计算签名并把签名结果加入请求参数

        $params ['Signature'] = $this->computeSignature ( $params, $this->accessKeySecret );

        // 发送请求（此处作了修改）
        $url = 'http://dysmsapi.aliyuncs.com/?' . http_build_query ( $params );

        $ch = curl_init ();

        curl_setopt ( $ch, CURLOPT_URL, $url );

        curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, FALSE );

        curl_setopt ( $ch, CURLOPT_SSL_VERIFYHOST, FALSE );

        curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );

        curl_setopt ( $ch, CURLOPT_TIMEOUT, 10 );

        $result = curl_exec ( $ch );

        curl_close ( $ch );

        $result = json_decode ( $result, true );
        var_dump($result);
        if(isset($result['Code']))
        {
            if($result['Code'] == 'OK')
            {
                return true;
            }else{
                return false;
            }
        }else{
            return false;
        }
    }
    /**
     * 交易接口
     * @param $mobile
     * @return bool
     */
    public function sell_out($mobile)
    {
        $params = array (   //此处作了修改

            'SignName' => $this->signName,

            'Format' => 'JSON',

            'Version' => '2017-05-25',

            'AccessKeyId' => $this->accessKeyId,

            'SignatureVersion' => '1.0',

            'SignatureMethod' => 'HMAC-SHA1',

            'SignatureNonce' => uniqid (),

            'Timestamp' => gmdate ( 'Y-m-d\TH:i:s\Z' ),

            'Action' => 'SendSms',

            'TemplateCode' => 'SMS_262575664',  //模板id

            'PhoneNumbers' => $mobile,

        );

        //var_dump($params);die;

        // 计算签名并把签名结果加入请求参数

        $params ['Signature'] = $this->computeSignature ( $params, $this->accessKeySecret );

        // 发送请求（此处作了修改）
        $url = 'http://dysmsapi.aliyuncs.com/?' . http_build_query ( $params );

        $ch = curl_init ();

        curl_setopt ( $ch, CURLOPT_URL, $url );

        curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, FALSE );

        curl_setopt ( $ch, CURLOPT_SSL_VERIFYHOST, FALSE );

        curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );

        curl_setopt ( $ch, CURLOPT_TIMEOUT, 10 );

        $result = curl_exec ( $ch );

        curl_close ( $ch );

        $result = json_decode ( $result, true );
        if(isset($result['Code']))
        {
            if($result['Code'] == 'OK')
            {
                return true;
            }else{
                return false;
            }
        }else{
            return false;
        }
    }


    /**
     * 批量通知接口
     * @param $mobile
     * @return bool
     */
    public function batch_sms($mobile)
    {
        $arr = [];
        for ($i=0;$i<count($mobile);$i++)
        {
            array_push($arr,'亿网科技');
        }
        $params = array (   //此处作了修改

            'SignNameJson' => json_encode($arr),

            'Format' => 'JSON',

            'Version' => '2017-05-25',

            'AccessKeyId' => $this->accessKeyId,

            'SignatureVersion' => '1.0',

            'SignatureMethod' => 'HMAC-SHA1',

            'SignatureNonce' => uniqid (),

            'Timestamp' => gmdate ( 'Y-m-d\TH:i:s\Z' ),

            'Action' => 'SendBatchSms',

            'TemplateCode' => 'SMS_211210089',  //模板id

            'PhoneNumberJson' => json_encode($mobile),

        );

        //var_dump($params);die;

        // 计算签名并把签名结果加入请求参数

        $params ['Signature'] = $this->computeSignature ( $params, $this->accessKeySecret );

        // 发送请求（此处作了修改）
        $url = 'http://dysmsapi.aliyuncs.com/?' . http_build_query ( $params );

        $ch = curl_init ();

        curl_setopt ( $ch, CURLOPT_URL, $url );

        curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, FALSE );

        curl_setopt ( $ch, CURLOPT_SSL_VERIFYHOST, FALSE );

        curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );

        curl_setopt ( $ch, CURLOPT_TIMEOUT, 10 );

        $result = curl_exec ( $ch );

        curl_close ( $ch );

        $result = json_decode ( $result, true );
        var_dump($result);
        if(isset($result['Code']))
        {
            if($result['Code'] == 'OK')
            {
                return true;
            }else{
                return false;
            }
        }else{
            return false;
        }
    }

    /**
     *
     * @param $mobile
     * @param $cont
     * @return bool
     */
    public function jiaoyi_ok($mobile)
    {
        $params = array (   //此处作了修改

            'SignName' => $this->signName,

            'Format' => 'JSON',

            'Version' => '2017-05-25',

            'AccessKeyId' => $this->accessKeyId,

            'SignatureVersion' => '1.0',

            'SignatureMethod' => 'HMAC-SHA1',

            'SignatureNonce' => uniqid (),

            'Timestamp' => gmdate ( 'Y-m-d\TH:i:s\Z' ),

            'Action' => 'SendSms',

            'TemplateCode' => 'SMS_262490682',  //模板id

            'PhoneNumbers' => $mobile,

        );

        //var_dump($params);die;

        // 计算签名并把签名结果加入请求参数

        $params ['Signature'] = $this->computeSignature ( $params, $this->accessKeySecret );

        // 发送请求（此处作了修改）
        $url = 'http://dysmsapi.aliyuncs.com/?' . http_build_query ( $params );

        $ch = curl_init ();

        curl_setopt ( $ch, CURLOPT_URL, $url );

        curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, FALSE );

        curl_setopt ( $ch, CURLOPT_SSL_VERIFYHOST, FALSE );

        curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );

        curl_setopt ( $ch, CURLOPT_TIMEOUT, 10 );

        $result = curl_exec ( $ch );

        curl_close ( $ch );

        $result = json_decode ( $result, true );
        if(isset($result['Code']))
        {
            if($result['Code'] == 'OK')
            {
                return true;
            }else{
                return false;
            }
        }else{
            return false;
        }
    }
    /**
     *
     * @param $mobile
     * @param $code
     */
    public function send_verify33($mobile)
    {
        $content = '【UEP】亲爱的UEP用户,您的订单已匹配成功,请及时查看!';
        //查询参数
        $m = md5('yangyang324');
        $data="ddtkey=ibih&secretkey=$m&mobile=$mobile&content=$content";
        $url="http://112.124.17.46:7001/sms_token";
        $ch=curl_init();

        curl_setopt($ch, CURLOPT_URL,$url );

        curl_setopt($ch, CURLOPT_POST, 1);

        curl_setopt($ch, CURLOPT_HEADER,0);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, 5);

        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        $result = curl_exec ( $ch );
        var_dump($result);
        curl_close ( $ch );
    }
    /**
     * @param $mobile
     * @param $cont
     * @return bool
     */
    public function send_verify($mobile)
    {
        $redis0= ApplicationContext::getContainer()->get(RedisFactory::class)->get('db0');
        $content = $redis0->get('transaction_sms');
        //查询参数
        $m = md5('yangyang324');
        $data="ddtkey=ibih&secretkey=$m&mobile=$mobile&content=$content";

        $url="http://112.124.17.46:7001/sms_token";

        $ch=curl_init();

        curl_setopt($ch, CURLOPT_URL,$url );

        curl_setopt($ch, CURLOPT_POST, 1);

        curl_setopt($ch, CURLOPT_HEADER,0);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, 5);

        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        $result = curl_exec ( $ch );

        curl_close ( $ch );
    }

    /**
     * @param $mobile
     * @param $verify_code
     * @return bool
     */
    public function send_verify2($mobile, $cont)
    {
        $params = array (   //此处作了修改

            'SignName' => $this->signName,

            'Format' => 'JSON',

            'Version' => '2017-05-25',

            'AccessKeyId' => $this->accessKeyId,

            'SignatureVersion' => '1.0',

            'SignatureMethod' => 'HMAC-SHA1',

            'SignatureNonce' => uniqid (),

            'Timestamp' => gmdate ( 'Y-m-d\TH:i:s\Z' ),

            'Action' => 'SendSms',

            'TemplateCode' => 'SMS_180062211',  //模板id

            'PhoneNumbers' => $mobile,

            'TemplateParam' => json_encode($cont),
        );

        //var_dump($params);die;

        // 计算签名并把签名结果加入请求参数

        $params ['Signature'] = $this->computeSignature ( $params, $this->accessKeySecret );

        // 发送请求（此处作了修改）
        $url = 'http://dysmsapi.aliyuncs.com/?' . http_build_query ( $params );

        $ch = curl_init ();

        curl_setopt ( $ch, CURLOPT_URL, $url );

        curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, FALSE );

        curl_setopt ( $ch, CURLOPT_SSL_VERIFYHOST, FALSE );

        curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );

        curl_setopt ( $ch, CURLOPT_TIMEOUT, 10 );

        $result = curl_exec ( $ch );

        curl_close ( $ch );

        $result = json_decode ( $result, true );
        if(isset($result['Code']))
        {
            if($result['Code'] == 'OK')
            {
                return true;
            }else{
                return false;
            }
        }else{
            return false;
        }
    }
    /**
     * 获取详细错误信息
     * @param $status
     * @return mixed
     */
    public function getErrorMessage($status) {

        // 阿里云的短信 乱八七糟的(其实是用的阿里大于)

        // https://api.alidayu.com/doc2/apiDetail?spm=a3142.7629140.1.19.SmdYoA&apiId=25450

        $message = array (

            'InvalidDayuStatus.Malformed' => '账户短信开通状态不正确',

            'InvalidSignName.Malformed' => '短信签名不正确或签名状态不正确',

            'InvalidTemplateCode.MalFormed' => '短信模板Code不正确或者模板状态不正确',

            'InvalidRecNum.Malformed' => '目标手机号不正确，单次发送数量不能超过100',

            'InvalidParamString.MalFormed' => '短信模板中变量不是json格式',

            'InvalidParamStringTemplate.Malformed' => '短信模板中变量与模板内容不匹配',

            'InvalidSendSms' => '触发业务流控',

            'InvalidDayu.Malformed' => '变量不能是url，可以将变量固化在模板中'

        );

        if (isset ( $message [$status] )) {

            return $message [$status];

        }

        return $status;

    }

}