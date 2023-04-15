<?php declare(strict_types=1);

namespace App\Controller;

use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Annotation\Middleware;
use App\Middleware\UserMiddleware;
use Hyperf\HttpServer\Annotation\Middlewares;
use App\Middleware\SignatureMiddleware;
use Hyperf\Utils\Context;
use OSS\OssClient;
use OSS\Core\OssException;
use Swoole\Exception;

/**
 * 阿里云通用上传接口
 * Class UserController
 * @Middlewares({
 *     @Middleware(SignatureMiddleware::class),
 *     @Middleware(UserMiddleware::class)
 * })
 * @package App\Controller
 * @Controller(prefix="ali_upload")
 */
class AliFileController extends XiaoController
{

    /**
     * @RequestMapping(path="file",methods="post")
     */
    public function file()
    {
        $userInfo     = Context::get('userInfo');

        if(!$this->request->hasFile('file'))
        {
            return $this->withError('请先上传文件');
        }
        $file = $this->request->file('file');
        //获取文件哈希信息
//        $haxi = md5_file($file->getPathname());
        //判断哈希值是否存在 存在则直接返回文件路径
//        $url = $this->redis3->get($haxi);
//        if($url)
//        {
//            return $this->withResponse('上传成功',['url' => $url ]);
//        }
        //不存在则上传文件
        $ex = $file->getExtension();
        $ex = strtolower(strval($ex));

        //获取配置中的信息
//        $extension = $this->redis0->get('file_extension');
//        $extension = json_decode($extension,true);

        //$extension = ['jpg','jpeg','png','gif','bmp','pdf','rtf','mp4','mp3','rm','rmvb','mkv','3gp','mov','xls','xlsx'];
        $extension = ['bmp','jpg','png','tif','gif','pcx','tga','exif','fpx','svg'];

        //判断后缀是否允许上传
        if(!in_array($ex,$extension))
        {
            return $this->withError('只能上传图片jpg/png/gif');
        }

        //上传到阿里云
        $info = [
            'file'  => $file->getPathname(),
            'ex'    => $ex
        ];
        $img_path = $this->aliupload($info);
        //写入缓存信息
//        $this->redis3->set($haxi,$qnyurl.'/'.$res);
//        $this->redis3->sAdd('hash',$haxi);
        //返回信息
        $ali_oss_url = 'https://jinhoumanzuo.oss-cn-hangzhou.aliyuncs.com';
        return $this->withResponse('上传成功',['url' => $ali_oss_url.'/'.$img_path ]);
    }

    /**
     * 阿里云上传
     * @param $info
     * @return mixed|string
     * @throws Exception
     */
    public function aliupload($info)
    {
        $user_id       = Context::get('user_id');
        if(!$user_id)
        {
            $user_id = mt_rand(100000,999999);
        }

// 阿里云账号AccessKey拥有所有API的访问权限，风险很高。强烈建议您创建并使用RAM用户进行API访问或日常运维，请登录RAM控制台创建RAM用户。
        $accessKeyId = "LTAI5tE8iguurb4wVa3A2yxf";
        $accessKeySecret = "X9i0AZu5ORk3WAal9d1opAXjsmAy8D";
// Endpoint以杭州为例，其它Region请按实际情况填写。
        $endpoint = "http://oss-cn-hangzhou.aliyuncs.com";
// 填写Bucket名称，例如examplebucket。
        $bucket= "jinhoumanzuo";
// <yourObjectName>表示上传文件到OSS时需要指定包含文件后缀，不包含Bucket名称在内的完整路径，例如abc/efg/123.jpg。

        //生成文件名字
        $date = date("Ymd");
        $key = md5($date.date('Ymdhis').$user_id).'.'.$info['ex'];
        $object = $date.'/'.$key;

        try{
            $ossClient = new OssClient($accessKeyId, $accessKeySecret, $endpoint);
            $res = $ossClient->uploadFile($bucket, $object, $info['file']);
            if(!empty($res['x-oss-hash-crc64ecma']) && !empty($res['content-md5']) && !empty($res['etag']))
            {
                return $object;
            }else{
                throw new Exception('上传出错，请从新选择图片！', 10001);
            }
        } catch(OssException $e) {
            throw new Exception($e->getMessage(), 10001);
        }

    }

}