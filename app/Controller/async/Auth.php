<?php declare(strict_types=1);

namespace App\Controller\async;

use AlibabaCloud\SDK\Cloudauth\V20190307\Cloudauth;

use App\Model\DsAuthError;
use App\Model\DsUser;
use Darabonba\OpenApi\Models\Config;
use AlibabaCloud\SDK\Cloudauth\V20190307\Models\InitFaceVerifyRequest;
use AlibabaCloud\SDK\Cloudauth\V20190307\Models\DescribeFaceVerifyRequest;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Redis\RedisFactory;
use Hyperf\Guzzle\ClientFactory;
use Hyperf\Utils\Context;
use Swoole\Exception;

// Download：https://github.com/aliyun/openapi-sdk-php
// Usage：https://github.com/aliyun/openapi-sdk-php/blob/master/README.md

/**
 * 阿里云实名认证
 * Class Auth
 * @package App\Controller
 */
class Auth
{
    protected $container;
    protected $redis5;
    protected static $accessKeyId = 'LTAI4G6iMxQFDXqpeuRuqxcf';
    protected static $accessKeySecret = 'XTgCiE0Lc1wEWPphnGlrlKxhUBjDju';
    /**
     * Auth constructor.
     */
    public function __construct()
    {
        $this->container = ApplicationContext::getContainer();
        $this->redis5 = $this->container->get(RedisFactory::class)->get('db5');
    }

    /**
     * 使用AK&SK初始化账号Client
     * @return Cloudauth
     */
    public static function createClient(){
        $config = new Config([
            // 您的AccessKey ID
            "accessKeyId" => self::$accessKeyId,
            // 您的AccessKey Secret
            "accessKeySecret" => self::$accessKeySecret,
        ]);
        // 访问的域名
        $config->endpoint = "cloudauth.aliyuncs.com";
        return new Cloudauth($config);
    }


    /**
     * 金融级活体人脸认证
     * @param $info
     * @return array
     * @throws Exception
     */
    public static function main($info)
    {
        try {
            $client = self::createClient();
            $initFaceVerifyRequest = new InitFaceVerifyRequest([
                "sceneId" => 1000003326,
                "outerOrderNo" => $info['order_sn'],
                "productCode" => "PV_FV",
                "certType" => "IDENTITY_CARD",
                "certName" => $info['name'],
                "certNo" => $info['card'],
                "userId" => $info['user_id'],
                "model" => "LIVENESS",
                "crop" => "T",
                "metaInfo" => strval($info['metaInfo']),
                "faceContrastPictureUrl" => $info['face_url'],
            ]);
            // 复制代码运行请自行打印 API 的返回值
            return $client->initFaceVerify($initFaceVerifyRequest)->toMap();
        }catch(\Exception $exception)
        {
            //修改用户状态为认证失败,请重新上传图片
            DsUser::query()->where('user_id',$info['user_id'])->update(['auth' => 3,'auth_name' =>'','auth_num' =>'' ]);
            //删除图片
            DsUserAuthImgInfo::query()->where('user_id',$info['user_id'])->delete();
            $code = $exception->getCode();
            switch ($code)
            {
                case 412:
                    #欠费中
                    throw new Exception('当前实名认证正在更新中哦，请耐心等待~', 10001);
                    break;
                case 417: case 419: case 421: case 422:
                    #传入图片不可用
                    throw new Exception('传入的图片不可用!请提交重新上传！', 10001);
                    break;
                default:
                    throw new Exception($exception->getMessage(), 10001);
                    break;
            }
        }
    }

    /**
     * 金融级实人认证
     * @param $info
     * @return array
     * @throws Exception
     */
    public static function _shiren_($info)
    {
        $user_id    = Context::get('user_id');
        try {
            $client = self::createClient();
            $initFaceVerifyRequest = new InitFaceVerifyRequest([
                "productCode" => "ID_PRO",
                "certType" => "IDENTITY_CARD",
                "certName" => $info['name'],
                "certNo" => $info['card'],
                "metaInfo" => strval($info['metaInfo']),
                "sceneId" => 1000006457,
                "outerOrderNo" => $info['order_sn'],
                "model" => "MULTI_ACTION",
            ]);
            // 复制代码运行请自行打印 API 的返回值
            $res =  $client->initFaceVerify($initFaceVerifyRequest)->toMap();
            if(empty($res['body']['Code']))
            {
                throw new Exception('当前实名人数较多，请从新打开APP再试', 10001);
            }
            if($res['body']['Code'] != 200)
            {
                DsAuthError::add_log($user_id, $res['body']['Code'],$res['body']['Message'],'','',1);
                throw new Exception($res['body']['Message'], 10001);
            }
            return [
                'RequestId' => $res['body']['RequestId'],
                'CertifyId' => $res['body']['ResultObject']['CertifyId'],
            ];
        }catch(\Exception $exception)
        {
            //修改用户状态为认证失败
            DsUser::query()->where('user_id',$info['user_id'])->update(['auth' => 3,'auth_name' =>'','auth_num' =>'' ]);
            $code = $exception->getCode();
            switch ($code)
            {
                case 412:
                    #欠费中
                    throw new Exception('当前实名认证正在更新中哦，请耐心等待~', 10001);
                    break;
                break;
                default:
                    DsAuthError::add_log($user_id, $code,$exception->getMessage(),$exception->getFile(),$exception->getLine(),1);
                    throw new Exception($exception->getMessage(), 10001);
                    break;
            }
        }
    }


    /**
     *
     * @param $certifyId
     */
    public static function res_check($certifyId)
    {
        $user_id    = Context::get('user_id');
        try {
            $client = self::createClient();
            $describeFaceVerifyRequest = new DescribeFaceVerifyRequest([
                "certifyId" => $certifyId,
                "sceneId" => 1000006457
            ]);

            // 复制代码运行请自行打印 API 的返回值
            return $client->describeFaceVerify($describeFaceVerifyRequest)->toMap();

        }catch(\Exception $exception)
        {
            $code = $exception->getCode();
            switch ($code)
            {
                case 424:
                    #身份认证记录不存在
                    throw new Exception('请先扫人脸~', 10001);
                    break;
                break;
                default:
                    DsAuthError::add_log($user_id, $code,$exception->getMessage(),$exception->getFile(),$exception->getLine(),2,$certifyId);
                    if($exception->getMessage() == 'Z5128')
                    {
                        throw new Exception('刷脸失败，认证未通过，请重新尝试！', 10001);
                    }else{
                        throw new Exception($exception->getMessage(), 10001);
                    }
                    break;
            }
        }

    }
}
