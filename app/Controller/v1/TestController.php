<?php

declare(strict_types=1);

namespace App\Controller\v1;

use App\Controller\async\Huafeido;
use App\Controller\XiaoController;
use App\Model\Chuli;
use App\Model\DsCinema;
use App\Model\DsCinemaVideoComment;
use App\Model\DsCinemaVideoCommentLike;
use App\Model\DsErrorLog;
use App\Model\DsUser;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;

use Hyperf\HttpServer\Annotation\AutoController;

use Hyperf\Utils\Context;

include_once BASE_PATH . '/extend/tencentim/Tencentimsdk.php';
use tencentim\Tencentimsdk;

use Hyperf\DbConnection\Db;




/**
 * @AutoController()
 */
class TestController extends XiaoController
{
    public function index(RequestInterface $request, ResponseInterface $response)
    {
        $res = [];

        $res['mm'] = password_hash('www9898980',PASSWORD_DEFAULT);


        return $this->withResponse('ok',$res);

    }

    /**
     * @access 生成唯一字符串
     * @param mixed    $type   [字符串的类型]
     * 0 = 纯数字字符串；
     * 1 = 小写字母字符串；
     * 2 = 大写字母字符串；
     * 3 = 大小写数字字符串；
     * 4 = 字符；
     * 5 = 数字，小写，大写，字符混合；
     * 6 = 数字+小写；
     * @param mixed    $length [字符串的长度]
     * @param mixed    $time   [是否带时间 1 = 带，0 = 不带]
     * @return string
     **/
    protected static function only_string($type = 6,$length = 6,$time=0){
        $str = $time == 0 ? '':date('YmdHis',time()).mt_rand(10000000,99999999);
        switch ($type) {
            case 0:
                for((int)$i = 0;$i <= $length;$i++){
                    if(mb_strlen($str) == $length){
                        $str = $str;
                    }else{
                        $str .= rand(0,9);
                    }
                }
                break;
            case 1:
                for((int)$i = 0;$i <= $length;$i++){
                    if(mb_strlen($str) == $length){
                        $str = $str;
                    }else{
                        $rand = "qwertyuioplkjhgfdsazxcvbnm";
                        $str .= $rand{mt_rand(0,25)};
                    }
                }
                break;
            case 2:
                for((int)$i = 0;$i <= $length;$i++){
                    if(mb_strlen($str) == $length){
                        $str = $str;
                    }else{
                        $rand = "QWERTYUIOPLKJHGFDSAZXCVBNM";
                        $str .= $rand{mt_rand(0,25)};
                    }
                }
                break;
            case 3:
                for((int)$i = 0;$i <= $length;$i++){
                    if(mb_strlen($str) == $length){
                        $str = $str;
                    }else{
                        $rand = "123456789qwertyuioplkjhgfdsazxcvbnmQWERTYUIOPLKJHGFDSAZXCVBNM";
                        $str .= $rand{mt_rand(0,35)};
                    }
                }
                break;
            case 4:
                for((int)$i = 0;$i <= $length;$i++){
                    if(mb_strlen($str) == $length){
                        $str = $str;
                    }else{
                        $rand = "!@#$%^&*()_+=-~`";
                        $str .= $rand{mt_rand(0,15)};
                    }
                }
                break;
            case 5:
                for((int)$i = 0;$i <= $length;$i++){
                    if(mb_strlen($str) == $length){
                        $str = $str;
                    }else{
                        $rand = "123456789qwertyuioplkjhgfdsazxcvbnmQWERTYUIOPLKJHGFDSAZXCVBNM!@#$%^&*()_+=-~`";
                        $str .= $rand{mt_rand(0,52)};
                    }
                }
            case 6:
                for((int)$i = 0;$i <= $length;$i++){
                    if(mb_strlen($str) == $length){
                        $str = $str;
                    }else{
                        $rand = "1234567890qwertyuioplkjhgfdsazxcvbnm";
                        $str .= $rand{mt_rand(0,35)};
                    }
                }
                break;
        }
        return $str;
    }

    public function test22(){
        $cinema_list = $this->only_string(6,6);

        return $this->withSuccess('ok',200,$cinema_list);
    }


    //获取 UserSig
    protected function get_UserSign($sdkappid,$tx_key,$userid, $expire = 86400*360){
        //判断token是否存在如果不存在就生成
        $redis = $this->redis2->get('tx_sign_'.$userid);
        if($redis){
            return $this->redis2->get('tx_sign_'.$userid);
        }
        try {
            $tLSSigAPIv2 = new Tencentimsdk($sdkappid, $tx_key);
            $sign = $tLSSigAPIv2->genUserSig($userid, $expire);
            if($sign){
                $this->redis2->set('tx_sign_'.$userid,$sign,$expire-2);
                return $sign;
            }else{
                return false;
            }
        } catch (\Throwable $e) {
             DsErrorLog::add_log('错误获取im',json_encode($e->getMessage()),'错误获取im');
        }
    }

    //导入用户
    public function daoru_im_user()
    {

        $user_id = Context::get('user_id');
        $userInfo = Context::get('userInfo');


        $user_id = 10;
        $userInfo = ['nickname'=>'hahahah'];

        $Nick = $userInfo['nickname'];
        $FaceUrl = 'http://oss.100ncy.cn/upload/2023-02-25/291a5c83d5977dd30dd0896a3a1758f1a41e6ab1.jpeg';

        $sdkappid = '1400726929';
        $tx_key = '513eab21fe8645034932b76fa3ab41ff96b0122d4742f8695b7c9d175758ac7c ';
        $tencent_api_url = 'https://console.tim.qq.com';//腾讯域名
        $ver = 'v4';//版本
        $identifier = 'administrator';//管理员用户名 UserID
        $md5_num = md5('tx_' . rand(100000,999999) . time());

        $usersig = $this->get_UserSign($sdkappid,$tx_key,$identifier);
        if(!$usersig){
            DsErrorLog::add_log('错误获取im的sign',$user_id,'获取用户签名错误');
        }
        $servicename = 'im_open_login_svc';
        $command = 'account_import';
        $url = $tencent_api_url . '/' . $ver . '/' . $servicename . '/' . $command . '?sdkappid=' . $sdkappid . '&identifier=' . $identifier . '&usersig=' . $usersig . '&random=' . $md5_num . '&contenttype=json';
        $data = [
            'UserID' => (string)$user_id,
            'Nick' => (string)$Nick,
            "FaceUrl" => (string)$FaceUrl,
        ];
        try {

            $cinema_info = $this->http->post($url, ['headers' =>['Content-Type' => 'application/json'], 'form_params' => $data])->getBody()->getContents();
            if(empty($cinema_info))
            {
                DsErrorLog::add_log('错误获取im的sign',$user_id,'获取用户签名错误-返回接口失败');
            }else{
                $re = json_decode($cinema_info,true);
                if(empty($re)){
                    DsErrorLog::add_log('错误获取im-json空',json_encode($re),'错误获取im-json空');
                }

                if($re['ActionStatus'] == 'OK'){
                    if($re['ErrorCode'] != 0){
                        DsErrorLog::add_log('错误获取im的sign',json_encode($re),'获取用户签名错误-返回状态失败');
                    }
                    return $this->withSuccess('ok');
                }else{
                    DsErrorLog::add_log('错误获取im-code',json_encode($re),'错误获取im-code');
                }
            }

        } catch (\Throwable $e) {
            DsErrorLog::add_log('错误获取im',json_encode($e->getMessage()),'错误获取im');
        }
    }


    /**
     *
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function test_chf(){
//        20230316505210212220224
//        20230316575399537239178
//        20230316485356518571323
//        20230316554856546202844
//        20230316999757564158218
//        2023031697515356965737
//        20230316100575342515039
//        20230316559710149358777
//        20230316495556569340410
return false;
        $chf = make(Huafeido::class);
        $data['re'] = $chf->zhichong_add_pinwei(23);

        return $this->withResponse('ok',$data);

    }
}
