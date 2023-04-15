<?php

declare(strict_types=1);

namespace App\Controller\v1;

use App\Controller\XiaoController;

use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Annotation\Middlewares;
use App\Middleware\SignatureMiddleware;
use App\Middleware\UserMiddleware;
use Hyperf\Utils\Context;
use App\Model\DsErrorLog;
use App\Model\DsUserRenlianLog;
use App\Model\DsUser;
use Swoole\Exception;

/**
 * @Middlewares({
 *     @Middleware(SignatureMiddleware::class),
 *     @Middleware(UserMiddleware::class)
 * })
 * @package App\Controller\v1
 * @Controller(prefix="v1/renlian")
 */
class RenlianController  extends XiaoController
{
    //*人脸 api 实名认证 公安一所实人认证，支持Android/IOS/H5/小程序/公众号/浏览器，多种活体(眨眼，摇头，点头，张嘴，远近，读数)
    //*依赖权威公安库(公安一所)进行实人认证，支持多种活体类型及多平台(H5，IOS, Android)，全链路加密，接入极简。
    //* https://market.aliyun.com/products/57126001/cmapi00046546.html?spm=5176.2020520132.101.4.4f837218TDiMWP#sku=yuncode4054600001

    protected $url_rl = 'https://apprpv.market.alicloudapi.com';//地址

    protected $AppKey = '204147664';
    protected $AppSecret = 'UUXYnL8E521npXW6npOy4xGxhtY9DbDI';
    protected $AppCode = '44f0b8c9ee46467b908ca9daacc321b4';


    /**生成唯一号
     * @return string
     */
    protected function get_number(){
        return  date('Ymd').substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
    }

    /**校验用户是否已经 通过
     * @return void
     * @throws Exception
     */
    protected function check_user()
    {
        $user_id = Context::get('user_id');
        if($user_id){
            $re = DsUser::query()->where('user_id',$user_id)->where('auth',0)->first();
            if(empty($re)){
                throw new Exception('你已通过认证', 10001);
            }
        }
    }

    /**
     * @RequestMapping(path="get_rl_token",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get_rl_token(){

        $certName = $this->request->post('certName','');
        $certNo = $this->request->post('certNo',0);
        $initMsg = $this->request->post('initMsg','');

        $this->check_user();

        $user_id = Context::get('user_id');

        if(empty($certName) || empty($certNo) || empty($initMsg)){
            return $this->withError('姓名、身份证号、初始化信息不能空');
        }

        $this->check_often($user_id);
        $this->start_often($user_id);

        if(!$this->validateIdCard($certNo)){
            return $this->withError('身份证号或姓名有误');
        }

        if(mb_strlen($certName) > 4){
            return $this->withError('身份证姓名最多4个字');
        }

        $ruser = DsUser::query()->where('auth',1)->where('auth_num',$certNo)->value('user_id');
        if(!empty($ruser)){
            return $this->withError('该身份证号已经实名');
        }

        //判读用户失败次数
        $ci = $this->redis3->get('auth_shibai_ci'.$user_id);
        if($ci >= 3)
        {
            return $this->withError('您实名认证失败次数较多，请联系客服！');
        }
        //判读用户获取token次数
        $ci3 = $this->redis3->get('auth_shibai_huoqu_ci'.$user_id);
        if($ci3 >= 3)
        {
            return $this->withError('您实名认证获取次数超过3次，请联系客服！');
        }

        try{
            $host = $this->url_rl;
            $path = "/init";
            $method = "POST";
            $appcode = $this->AppCode;
            $headers = array();
            array_push($headers, "Authorization:APPCODE " . $appcode);
            //需要自行安装UUID,需要给X-Ca-Nonce的值生成随机字符串，每次请求不能相同
            $uuidStr = $this->get_number();
            array_push($headers, "X-Ca-Nonce:" . $uuidStr);
            //根据API的要求，定义相对应的Content-Type
            array_push($headers, "Content-Type".":"."application/x-www-form-urlencoded; charset=UTF-8");
            $querys = "";
            $bodys = "bizId=".$uuidStr."&certName=".$certName."&certNo=".$certNo."&initMsg=".$initMsg;
            $url = $host . $path;

            $curl = curl_init();
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($curl, CURLOPT_FAILONERROR, false);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HEADER, true);
            if (1 == strpos("$".$host, "https://"))
            {
                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            }
            curl_setopt($curl, CURLOPT_POSTFIELDS, $bodys);

            $res = curl_exec($curl);


            if (!curl_errno($curl)){
                $hSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
                $rspMsg   = substr($res, $hSize);
                $header  = substr($res,0, $hSize);
            }else{
                return $this->withError('请求过于频繁请稍后再试');
            }
            $res2 = json_decode($rspMsg,true);
            if(empty($res2))
            {
                return $this->withError('请求过于频繁请稍后再试！');
            }

            $this->redis3->incr('auth_shibai_huoqu_ci'.$user_id);//用户获取token次数+1

            if($res2['code'] == '0000')
            {
                $data = [
                    'user_id' => $user_id,
                    'bizId' => $uuidStr,
                    'certName' => $certName,
                    'certNo' => $certNo,
                    'renlian_time' => time(),
                    'pass' => 'F',
                ];
                $r = DsUserRenlianLog::query()->insert($data);
                if(!$r){
                    DsErrorLog::add_log('人脸记录获取token添加失败',$user_id);
                    return $this->withError('请求过于频繁请稍后再试！');
                }
                return $this->withSuccess('ok',200,$res2['token']);
            }else{
                if(empty($res2['msg'])){
                    return $this->withError('请求过于频繁请稍后再试！');
                }
                return $this->withError($res2['msg']);
            }

        }catch (\Throwable $e) {
            return $this->withError($e->getMessage());
        }
    }

    /**
     * 人脸验证
     * @RequestMapping(path="renlian_do",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function renlian_do()
    {
        $token = $this->request->post('token_rl','');//认证token (初始化时候返回的)
        $verifyMsg = $this->request->post('verifyMsg','');//认证数据（认证完成后从sdk返回）

        $this->check_user();

        $user_id = Context::get('user_id');

        if(empty($token) || empty($verifyMsg)){
            return $this->withError('认证token、认证数据不能为空');
        }

        $this->check_often($user_id);
        $this->start_often($user_id);

        //判读用户失败次数
        $ci = $this->redis3->get('auth_shibai_ci'.$user_id);
        if($ci >= 3)
        {
            return $this->withError('您实名认证失败次数较多，请联系客服！');
        }

        $uuidStr = $this->get_number();

        try{
            $host = $this->url_rl;
            $path = "/verify";
            $method = "POST";
            $appcode = $this->AppCode;
            $headers = array();
            array_push($headers, "Authorization:APPCODE " . $appcode);
            //需要自行安装UUID,需要给X-Ca-Nonce的值生成随机字符串，每次请求不能相同
            array_push($headers, "X-Ca-Nonce:" . $uuidStr);
            //根据API的要求，定义相对应的Content-Type
            array_push($headers, "Content-Type".":"."application/x-www-form-urlencoded; charset=UTF-8");
            $querys = "";
            $bodys = "token=".$token."&verifyMsg=".$verifyMsg;
            $url = $host . $path;

            $curl = curl_init();
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($curl, CURLOPT_FAILONERROR, false);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HEADER, true);
            if (1 == strpos("$".$host, "https://"))
            {
                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            }
            curl_setopt($curl, CURLOPT_POSTFIELDS, $bodys);
            $res = curl_exec($curl);

            //分离头部和body
            if (!curl_errno($curl)){
                $hSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
                $rspMsg   = substr($res, $hSize);
                $header  = substr($res,0, $hSize);
            }else{
                return $this->withError('请求过多，请稍后再试');
            }

            $res2 = json_decode($rspMsg,true);
            if(empty($res2)){
                return $this->withError('请求过多，请稍后再试');
            }
            if(empty($res2['pass'])){
                return $this->withError('请求过多，请稍后再试!!');
            }
            if($res2['code'] == '0000' && $res2['pass'] == 'T'){
                $this->redis3->incr('auth_shibai_ci'.$user_id);//用户识别次数+1

                //校验是否同一个数据源
                $r_b = DsUserRenlianLog::query()->where('user_id',$user_id)->orderByDesc('renlian_log_id')->first();

                if($r_b['bizId'] != $res2['bizId']){
                    return $this->withError('和初始认证不一致');
                }

                $info = [
                    'type' => '35',
                    'user_id' => $user_id,
                    'res2' => $res2,
                ];
                $this->yibu($info);

                //修改用户表认证
//                $r_u = DsUser::query()->where('user_id',$user_id)->update(['auth'=>1,'auth_name'=>$res2['certName'],'auth_num'=>$res2['certNo'],]);
//                if(!$r_u){
//                    DsErrorLog::add_log('人脸记录通过,修改状态失败',$user_id);
//                }
//                $data = [
//                    'requestId' => $res2['requestId'],
//                    'livingType' => $res2['livingType'],
//                    'bestImg' => $res2['bestImg'],
//                    'pass' => $res2['pass'],
//                    'rxfs' => $res2['rxfs'],
//                ];
//                $r = DsUserRenlianLog::query()->where('user_id',$user_id)->where('bizId',$res2['bizId'])->update($data);
//                if(!$r){
//                    DsErrorLog::add_log('人脸记录核验成功修改失败',$user_id);
//                }

                return $this->withSuccess('ok',200,'成功');
            }else{
                if(empty($res2['msg'])){
                    return $this->withError('请求过多，请稍后再试~');
                }
                $this->redis3->incr('auth_shibai_ci'.$user_id);//用户识别次数+1
                return $this->withError($res2['msg']);
            }
        }catch (\Throwable $e) {
            return $this->withError($e->getMessage());
        }
    }

    /**
     * @RequestMapping(path="testrl",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function testrl(){
        return $this->withError('sdfasdfasdf');
    }

}
