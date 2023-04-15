<?php declare(strict_types=1);

namespace App\Controller;

use App\Model\DsErrorLog;
use App\Model\DsUser;
use Hyperf\Guzzle\ClientFactory;
use Hyperf\Redis\RedisFactory;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Context;
use Swoole\Exception;

/**
 * 阿里云发送短信
 * Class AliCmsSend
 * @package App\Controller
 */
class ChatIm
{

    private $hx_host = '';//环信即时通讯 IM 分配的用于访问 RESTful API 的域名。
    private $hx_org_name = '';//环信即时通讯 IM 为每个公司（组织）分配的唯一标识。
    private $hx_app_name = '';//你在环信即时通讯云控制台创建应用时填入的应用名称。
    private $appKey = '';
    private $ClientID = '';
    private $ClientSecret = '';

    protected $redis2;
    protected $http;

    public function __construct()
    {
        // 配置参数 正式
        $this->hx_host = 'http://a1.easemob.com';
        $this->hx_org_name = '1172230208209331';//环信即时通讯 IM 分配的用于访问 RESTful API 的域名。
        $this->hx_app_name = 'jhmz';//环信即时通讯 IM 为每个公司（组织）分配的唯一标识。
        $this->appKey = '1172230208209331#jhmz';//你在环信即时通讯云控制台创建应用时填入的应用名称。
        $this->ClientID = 'YXA6rCnBtsiRT8uIeDV4Lv7H_g';
        $this->ClientSecret = 'YXA6Kl1ii9rueFUy4ehzBcanAmdzXdM';

        // 配置参数 测试
//        $this->hx_host = 'http://a1.easemob.com';
//        $this->hx_org_name = '1172230208209331';//环信即时通讯 IM 分配的用于访问 RESTful API 的域名。
//        $this->hx_app_name = 'demo';//环信即时通讯 IM 为每个公司（组织）分配的唯一标识。
//        $this->appKey = '1172230208209331#demo';//你在环信即时通讯云控制台创建应用时填入的应用名称。
//        $this->ClientID = 'YXA6EhEjjf9FRNSYtZMxKSgneA';
//        $this->ClientSecret = 'YXA68AEfzSgAU_xj6ZkxBOKRbUkVQqA';

        $this->container = ApplicationContext::getContainer();
        $this->redis2 = $this->container->get(RedisFactory::class)->get('db2');
        $this->http   = ApplicationContext::getContainer()->get(ClientFactory::class)->create();
    }

    //App Token 鉴权 异步
    protected function AppToken()
    {
        $hx_access_token = $this->redis2->get('hx_access_token');
        if(!empty($hx_access_token)){
            return $hx_access_token;
        }
        $url = $this->hx_host.'/'.$this->hx_org_name.'/'.$this->hx_app_name.'/token';
        $params = [
            'grant_type' => 'client_credentials',
            'client_id' => $this->ClientID,
            'client_secret' => $this->ClientSecret,
            'ttl' => 0,
        ];
        $r_info = $this->http->post($url, ['headers' =>['Content-Type' => 'application/json','Accept'=> 'application/json'], 'json' => $params])->getBody()->getContents();
        if(empty($r_info))
        {
            DsErrorLog::add_log('App Token返回空',json_encode($params),'App Token返回空');
            return false;
        }else{
            $re = json_decode($r_info,true);
            if($re){
                if(!empty($re['access_token'])){
                    $this->redis2->set('hx_access_token',$re['access_token']);
                    return $re['access_token'];
                }
            }else{
                DsErrorLog::add_log('App Token返回解析空',json_encode($params),'App Token返回空');
            }
            return false;
        }
    }

    //User Token 鉴权
    public function UserToken($user_id)
    {
        $hx_access_token = $this->redis2->get('hx_user_token_get_'.$user_id);
        if(!empty($hx_access_token)){
            //return $hx_access_token;
        }

        $url = $this->hx_host.'/'.$this->hx_org_name.'/'.$this->hx_app_name.'/token';
        $params = [
            'grant_type' => 'inherit',
            'username' => $user_id,
            'autoCreateUser' => false,//当用户不存在时，是否自动创建用户。自动创建用户时，需保证授权方式（grant_type）必须为 inherit，API 请求 header 中使用 App token 进行鉴权。
            'ttl' => 0,//永久
        ];
        try {
            $r_info = $this->http->post($url, ['headers' =>['Content-Type' => 'application/json','Accept'=> 'application/json','Authorization'=> 'Bearer '.$this->AppToken()], 'json' => $params])->getBody()->getContents();
            if(empty($r_info))
            {
                DsErrorLog::add_log('User Token鉴权返回空!',json_encode($params),'User Token鉴权返回空!');
                return false;
            }else{
                $re = json_decode($r_info,true);
                if($re){
                    if(!empty($re['access_token'])){
                        $re2 = DsUser::query()->where('user_id',$user_id)->update(['huanxin_token' =>$re['access_token']]);

                        if(empty($re2)){
                            DsErrorLog::add_log('User Token鉴权加入修改失败',json_encode($params),'User Token鉴权加入修改失败');
                            return false;
                        }
                        //$this->redis2->set('hx_user_token_get_'.$user_id,strval($re['access_token']),60);
                        return $re['access_token'];
                    }
                    return false;
                }else{
                    DsErrorLog::add_log('User Token鉴权返回解析空',json_encode($params),'User Token鉴权返回解析空');
                }
                return false;
            }
        } catch(\Throwable $ex){
            //DsErrorLog::add_log('User Token鉴权返回空',json_encode($ex->getMessage()),'User Token鉴权返回空');
            return false;
        }
    }

    //授权注册用户
    public function user_add($user_id)
    {
        $huanxin_token = DsUser::query()->where('user_id',$user_id)->value('huanxin_token');
        if($huanxin_token){
            return true;
        }
        $url = $this->hx_host.'/'.$this->hx_org_name.'/'.$this->hx_app_name.'/users';
        $params = [
            'username' => strval($user_id),
            'nickname' => 'mz-用户',
        ];
        try {
            $r_info = $this->http->post($url, ['headers' =>['Content-Type' => 'application/json','Accept'=> 'application/json','Authorization'=> 'Bearer '.$this->AppToken()], 'json' => $params])->getBody()->getContents();

            if(empty($r_info))
            {
                DsErrorLog::add_log('授权注册返回空',json_encode($params),'授权注册返回空');
            }else{
                $re = json_decode($r_info,true);
                if(!$re){
                    DsErrorLog::add_log('授权注册返回解析空',json_encode($params),'授权注册返回解析空');
                    return false;
                }
                return true;
            }
        } catch(\Throwable $ex){
            //var_dump($ex->getMessage());
            if($ex->getCode() == 401)
            {
                //更新管理员的token
                //$this->redis2->del('hx_access_token');
                return false;
            }

            if($ex->getCode() == 400)
            {
                //更新管理员的token
                //$this->redis2->del('hx_access_token');
                //已注册
                return false;
            }

        }
    }


    /**
     * 获取用户token ，没有就先创建再获取
     */
    public function get_user_token($user_id)
    {
        $res = $this->user_add($user_id);
        if(!$res){
            //return false;
        }
        $re = $this->UserToken($user_id);
        if(!$re){
            return false;
        }
        return true;
    }

    /**
     * 只负责注册im
     * @param $user_id
     * @return bool
     */
    public function get_user_add_do($user_id)
    {
        $res = $this->user_add($user_id);
        if(!$res){
            return false;
        }
        return true;
    }

    /**
     * 只是负责获取token并更新到数据库
     * @param $user_id
     * @return bool
     */
    public function get_user_token_do($user_id)
    {
        return $this->UserToken($user_id);
    }

    public function del_user($entities){

        $url = $this->hx_host.'/'.$this->hx_org_name.'/'.$this->hx_app_name.'/users';
        $params = [
          'entities' => $entities
        ];

        return true;
        try {
            $r_info = $this->http->delete($url, ['headers' =>['Accept' => 'application/json','Authorization'=> 'Bearer '.$this->AppToken()], 'json' => $params])->getBody()->getContents();
            var_dump($r_info);
            if(empty($r_info))
            {

            }else{
                $re = json_decode($r_info,true);

                var_dump($re);
            }
        } catch(\Throwable $ex){
            var_dump($ex->getMessage());
        }
        return true;
    }

    //批量拉人入群
    public function add_qun_user($group_id=0,$user_list=[]){
        if(!empty($user_list) && !empty($group_id) && is_array($user_list)){
            if(count($user_list) > 60){
                return false;
            }
            $url = $this->hx_host.'/'.$this->hx_org_name.'/'.$this->hx_app_name.'/chatgroups/'.$group_id.'/users';
            $params = [
                'usernames' => $user_list,
            ];
            try {
                $r_info = $this->http->post($url, ['headers' =>['Content-Type' => 'application/json','Accept'=> 'application/json','Authorization'=> 'Bearer '.$this->AppToken()], 'json' => $params])->getBody()->getContents();
                if(empty($r_info))
                {
                    DsErrorLog::add_log('批量入群返回空!',json_encode($params),'批量入群返回空!');
                    return false;
                }else{
                    $re = json_decode($r_info,true);
                    if($re){

                        return true;
                    }else{
                        DsErrorLog::add_log('批量入群解析空',json_encode($params),'批量入群解析空');
                    }
                    return false;
                }
            } catch(\Throwable $ex){
                if($ex->getCode() == 403){
                    //户已经在群内
                    //var_dump('已经在群内');
                    return false;
                }else{
                    DsErrorLog::add_log('批量入群返回空',json_encode($ex->getMessage()),'批量入群返回空');
                }
                return false;
            }
        }
    }
}