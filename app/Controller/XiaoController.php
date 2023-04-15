<?php declare(strict_types=1);

namespace App\Controller;

use App\Job\Async;
use App\Model\DsCode;
use App\Model\DsUser;
use App\Model\DsUserDatum;
use App\Model\DsUserTaskPack;
use App\Model\DsUserToken;
use App\Model\DsUserWebToken;
use dingxiang\CaptchaClient;
use Hyperf\AsyncQueue\Driver\DriverFactory;
use Hyperf\Guzzle\ClientFactory;
use Hyperf\Redis\RedisFactory;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Context;
use Swoole\Exception;

/**
 * 初始化操作
 * Class XiaoController
 * @package App\Controller
 *  redis | db0 系统配置 | db1 用户信息表 | db2 token表 | db3 人脸信息 | db4 每日活跃用户 以及其他参数
 *  | db5 用户上下级关系以及其他设置 | db6 用户团队数据 | db7 机器人 | db8 db9 db11 异步执行的东西
 */
class XiaoController extends AbstractController
{
    protected $redis0;
    protected $redis1;
    protected $redis2;
    protected $redis3;
    protected $redis4;
    protected $redis5;
    protected $redis6;
    protected $redis7;
    protected $redis8;
    protected $redis9;
    protected $redis10;
    protected $redis11;
    protected $redis12;
    protected $redis13;
    protected $redis14;
    protected $redis15;
    protected $container;
    protected $http;
    protected $xiaoshudian;
    public function __construct()
    {
        $this->container = ApplicationContext::getContainer();
        $this->redis0 = $this->container->get(RedisFactory::class)->get('db0');
        $this->redis1 = $this->container->get(RedisFactory::class)->get('db1');
        $this->redis2 = $this->container->get(RedisFactory::class)->get('db2');
        $this->redis3 = $this->container->get(RedisFactory::class)->get('db3');
        $this->redis4 = $this->container->get(RedisFactory::class)->get('db4');
        $this->redis5 = $this->container->get(RedisFactory::class)->get('db5');
        $this->redis6 = $this->container->get(RedisFactory::class)->get('db6');
        $this->redis7 = $this->container->get(RedisFactory::class)->get('db7');
        $this->redis8 = $this->container->get(RedisFactory::class)->get('db8');
        $this->redis9 = $this->container->get(RedisFactory::class)->get('db9');
        $this->redis10 = $this->container->get(RedisFactory::class)->get('db10');
        $this->redis11 = $this->container->get(RedisFactory::class)->get('db11');
        $this->redis12 = $this->container->get(RedisFactory::class)->get('db12');
        $this->redis13 = $this->container->get(RedisFactory::class)->get('db13');
        $this->redis14 = $this->container->get(RedisFactory::class)->get('db14');
        $this->redis15 = $this->container->get(RedisFactory::class)->get('db15');
        $this->http    = $this->container->get(ClientFactory::class)->create();
        $this->xiaoshudian = 4;
    }
    /**
     * 验证是否是有效手机号
     * @param $num
     * @return false|int
     */
    protected function isMobile($num)
    {
        if($num == NULL)
        {
            $num = '';
        }
        return preg_match("/^1[23456789]{1}\d{9}$/",strval($num));
    }
    /**
     * 检查验证码是否正确
     * @param $mobile
     * @param $type
     * @param $code
     * @param $time
     * @param int $user_id
     * @throws Exception
     */
    protected function _checkCode($mobile,$type,$code,$time,$user_id = 0)
    {
        $condition = [
            'mobile'        => $mobile,
            'code_type'     => $type,
            'code_value'    => $code,
            'user_id'       => $user_id,
        ];
        $codeInfo = DsCode::query()->where($condition)->select('code_id','expired_time')->first();
        if (!empty($codeInfo))
        {
            $info = $codeInfo->toArray();
            if($info['expired_time'] < $time)
            {
                throw new Exception('验证码已过期', 1003);
            }
            //修改验证码为已过期
            DsCode::query()->where('code_id',$info['code_id'])->update(['expired_time' =>$time ]);
        }else{
            throw new Exception('验证码错误', 1002);
        }
    }

    /**
     * 获取账号信息
     * @param $user_id
     * @param null $value
     * @return array|bool|\Hyperf\Database\Model\Builder|\Hyperf\Database\Model\Model|mixed|object|string|null
     * @throws Exception
     */
    protected function get_user_info($user_id, $value = NULL)
    {
        //读取缓存中的用户信息
        $userInfo = DsUser::query()->where('user_id',$user_id)->first();
        if(!$userInfo)
        {
            throw new Exception('该用户不存在', 10001);
        }
        $userInfo = $userInfo->toArray();
        //判断账号是否已被注销
        $this->_loginStatus($userInfo);
        //判断是否是获取单个字段
        if(empty($value))
        {
            return $userInfo;
        }else{
            if(is_array($value))
            {
                $arr = [];
                //获取多个字段
                foreach ($value as $v)
                {
                    $arr[$v] = $userInfo[$v];
                }
                return $arr;
            }else{
                //获取单个字段
                return $userInfo[$value];
            }
        }
    }
    /**
     * 检查是否是会员
     * @param $user_id
     * @return bool
     * @throws Exception
     */
    protected function _is_vip($user_id)
    {
        $info = $this->get_user_info($user_id,['role_id','vip_end_time']);
        if($info['role_id'] >0)
        {
            //判断会员到期时间
            if($info['vip_end_time'] -time() > 1)
            {
                return true;
            }else{
                //会员已到期 异步处理到期后的内容
                DsUser::query()->where('user_id',$user_id)->update(['role_id' => 0,'vip_end_time' => 0]);
                return false;
            }
        }else{
            return false;
        }
    }
    /**
     * 字符串模糊查找
     * @param $needle   //要包含的字符串
     * @param $str      //要查找的字符串内容
     * @return bool
     */
    protected function check_search_str($needle,$str)
    {
        $needle = strval($needle);
        $str = strval($str);
        if(strpos($str,$needle) !== false)
        {
            return true;
        }else{
            return false;
        }
    }


    /**
     * 生成web_token
     * @param $user_id
     * @return string
     */
    protected function web_token($user_id)
    {
        $redis0 = ApplicationContext::getContainer()->get(RedisFactory::class)->get('db0');
        DsUserWebToken::query()->where('user_id',$user_id)->delete();
        $time = time();
        $key  = $redis0->get('set_key');
        $token = md5($user_id. $key. $time);
        $data = [
            'user_id'   => $user_id,
            'token'     => $token,
        ];
        DsUserWebToken::query()->insert($data);
        return $token;
    }

    /**
     * 生成缓存token
     * @param $userId
     * @return bool|string
     */
    protected function token($userId)
    {
        $redis0 = ApplicationContext::getContainer()->get(RedisFactory::class)->get('db0');
        //获取配置信息
        $day = $redis0->get('set_token_out_time');
        $tokenTime = 60 *60 *24 * $day;
        $time = time();
        $key  = $redis0->get('set_key');
        $token = md5($userId. $key. $time);
        //先查找用户token是否有
        $redis2 = ApplicationContext::getContainer()->get(RedisFactory::class)->get('db2');
        //保存新的token信息
        $redis2->set($token,$userId,$tokenTime);
        //保存用户token信息
        $redis2->set($userId.'_token',$token,$tokenTime);
        return $token;
    }

    /**
     * 生成缓存token
     * @param $userId
     * @return bool|string
     */
    protected function token_db($user_id)
    {
        $time = time();
        $key  = '412bsdf325.034894';
        $token = md5($user_id. $key. $time);

        DsUserToken::query()->where('user_id',$user_id)->delete();

        $data = [
            'user_id'   => $user_id,
            'token'     => $token,
        ];
        $res = DsUserToken::query()->insert($data);
        if($res)
        {
            return $token;
        }else{
            throw new Exception('登录人数较多，请从新登录', 10001);
        }
    }

    /**
     * 生成缓存tokenweb
     * @param $userId
     * @return bool|string
     */
    protected function token_db2($user_id)
    {
        $time = time();
        $key  = '412bsdf325.034894';
        $token = md5($user_id. $key. $time);

        DsUserWebToken::query()->where('user_id',$user_id)->delete();

        $data = [
            'user_id'   => $user_id,
            'token'     => $token,
        ];
        $res = DsUserWebToken::query()->insert($data);
        if($res)
        {
            return $token;
        }else{
            throw new Exception('登录人数较多，请从新登录', 10001);
        }
    }

    /**
     *
     * @param $user_id
     * @param $token
     * @return string
     * @throws Exception
     */
    protected function token_db_root($user_id,$token)
    {
        $data = [
            'user_id'   => $user_id,
            'token'     => $token,
        ];
        $res = DsUserToken::query()->insert($data);
        if($res)
        {
            return $token;
        }else{
            throw new Exception('登录人数较多，请从新登录', 10001);
        }
    }

    /**
     *
     * @param $user_id
     * @param $token
     * @return string
     * @throws Exception
     */
    protected function token_db_root2($user_id,$token)
    {
        $data = [
            'user_id'   => $user_id,
            'token'     => $token,
        ];
        $res = DsUserWebToken::query()->insert($data);
        if($res)
        {
            return $token;
        }else{
            throw new Exception('登录人数较多，请从新登录', 10001);
        }
    }

    /**
     * 删除用户token
     * @param $user_id
     */
    protected function del_user_token_db($user_id)
    {
        //清除token
        DsUserToken::query()->where('user_id',$user_id)->delete();
    }

    /**
     * 删除用户token
     * @param $user_id
     */
    protected function del_user_token_db2($user_id)
    {
        //清除token
        DsUserWebToken::query()->where('user_id',$user_id)->delete();
    }

    //更新缓存用户信息
    protected function update_user_info($userInfo)
    {
        $redis1 = ApplicationContext::getContainer()->get(RedisFactory::class)->get('db1');
        //获取用户信息剩余时长
        $ttl = $redis1->ttl(strval($userInfo['user_id']));
        if($ttl && $ttl > 0)
        {
            //保存用户信息
            $redis1->set(strval($userInfo['user_id']),json_encode($userInfo));
            //设置过期时间
            $redis1->expire(strval($userInfo['user_id']),$ttl);
            //保存一下聊天头像
            $redis1->set($userInfo['user_id'].'_img',$userInfo['img'],$ttl);
            //保存一下昵称
            $redis1->set($userInfo['user_id'].'_nickname',$userInfo['nickname'],$ttl);
        }else{
            $redis0 = ApplicationContext::getContainer()->get(RedisFactory::class)->get('db0');
            //获取配置信息
            $day = $redis0->get('set_token_out_time');
            $tokenTime = 60 *60 *24 * $day;
            //保存用户信息
            $redis1->set(strval($userInfo['user_id']),json_encode($userInfo));
            //设置过期时间
            $redis1->expire(strval($userInfo['user_id']),$tokenTime);
            //保存一下聊天头像
            $redis1->set($userInfo['user_id'].'_img',$userInfo['img'],$tokenTime);
            //保存一下昵称
            $redis1->set($userInfo['user_id'].'_nickname',$userInfo['nickname'],$tokenTime);
        }
    }

    /**
     * 验证是否是有效身份证
     * @param $value
     * @return bool
     */
    protected function validateIdCard($value)
    {
        if (!preg_match('/^\d{17}[0-9xX]$/', $value)) { //基本格式校验
            return false;
        }

        $parsed = date_parse(substr($value, 6, 8));
        if (!(isset($parsed['warning_count'])
            && $parsed['warning_count'] == 0)) { //年月日位校验
            return false;
        }

        $base = substr($value, 0, 17);

        $factor = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2];

        $tokens = ['1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2'];

        $checkSum = 0;
        for ($i=0; $i<17; $i++) {
            $checkSum += intval(substr($base, $i, 1)) * $factor[$i];
        }

        $mod = $checkSum % 11;
        $token = $tokens[$mod];

        $lastChar = strtoupper(substr($value, 17, 1));

        return ($lastChar === $token); //最后一位校验位校验
    }
    /**
     * 检测用户是否被禁止登录
     * @param $userInfo
     * @throws Exception
     */
    protected function _loginStatus($userInfo)
    {
        if ($userInfo['login_status'] == 1)
        {
            throw new Exception('您的账号安全存在风险已被限制登陆,请联系客服核实身份信息后解除!', 10001);
        }
        if ($userInfo['login_status'] == 2)
        {
            throw new Exception('该账号已注销', 10001);
        }
    }
    /**
     * 生成验证码
     * @return string
     */
    protected function create_code()
    {
        return strval(mt_rand(100000,999999));
    }
    /**
     * 时间转换
     * @param $value
     * @return false|string
     */
    protected function replaceTime($value)
    {
        return date("Y-m-d H:i:s", (int)$value);
    }

    /**
     * 返回今日日期 不包括时分秒
     * @return false|int
     */
    protected function get_day()
    {
        return strtotime(date('Y-m-d'));
    }

    /**
     * 计算两组经纬度坐标 之间的距离
     * @param $lat1         //纬度1
     * @param $lat1         //纬度1
     * @param $lng1         //经度1
     * @param $lat2         //纬度2
     * @param $lng2         //经度2
     * @param int $type     //1:返回带单位 or 2:返回不带单位
     * @param int $l_type   //1:m or 2:km
     * @param int $decimal  //小数点位数
     * @return string
     */
    protected function GetDistance($lat1, $lng1, $lat2, $lng2, $type = 1,$l_type =1, $decimal = 2)
    {
        if(empty($lat1) || empty($lng1) || empty($lat2) || empty($lng2))
        {
            return '未知';
        }
        $EARTH_RADIUS = 6370.996; // 地球半径系数
        $PI = 3.1415926;

        $radLat1 = $lat1 * $PI / 180.0;
        $radLat2 = $lat2 * $PI / 180.0;
        $a = $radLat1 - $radLat2;
        $b = ($lng1 * $PI / 180.0) - ($lng2 * $PI / 180.0);
        $s = 2 * asin(sqrt(pow(sin($a/2),2) + cos($radLat1) * cos($radLat2) * pow(sin($b/2),2)));
        $s = $s * $EARTH_RADIUS;
        $s = round($s * 1000);
        if($type == 1)
        {
            //返回带单位
            if($s > 1000)
            {
                //km
                $s /= 1000;
                $mkm = 'km';
            }else{
                //m
                $mkm = 'm';
            }
            $str = strval(round($s, $decimal));
            return $str.$mkm;
        }else{
            //返回不带单位 用于计算
            if($l_type != 1)
            {
                $s /= 1000;
            }
            return strval(round($s, $decimal));
        }
    }

    /**
     *计算时间 多少多少之前
     * @param $time
     * @return false|string
     */
    protected function reckon_time($time)
    {
        $t = time() - $time;
        $y = date('Y', $time)-date('Y', time());//是否跨年
        switch($t)
        {
            case $t == 0:
                $text = '刚刚';
                break;
            case $t < 60:
                $text = $t . '秒前'; // 一分钟内
                break;
            case $t < 60 * 60:
                $text = floor($t / 60) . '分钟前'; //一小时内
                break;
            case $t < 60 * 60 * 24:
                $text = floor($t / (60 * 60)) . '小时前'; // 一天内
                break;
            case $t < 60 * 60 * 24 * 2:
                if($time + (60*60*24) >= $this->get_day())
                {
                    $text = '昨天 '. date('H:i', $time);
                }else{
                    $text = '前天 '. date('H:i', $time);
                }
                break;
            case $t < 60 * 60 * 24 * 30:
                $text = date('m月d日 H:i', $time); //一个月内
                break;
            case $t < 60 * 60 * 24 * 365&&$y==0:
                $text = date('m月d日', $time); //一年内
                break;
            default:
                $text = date('Y年m月d日', $time); //一年以前
                break;
        }
        return $text;
    }

    /**
     * 替换手机中间4位为*
     * @param $mobile
     * @return string|string[]
     */
    protected function replace_mobile_end($mobile)
    {
        return substr_replace(strval($mobile), '****',3, 4);
    }

    /**
     * 只提取后4位
     * @param $mobile
     * @return false|string
     */
    protected function get_mobile_end_4($mobile)
    {
        return substr(strval($mobile),-4, 4);
    }

    /**
     * 邮箱、手机账号中间字符串以*隐藏
     * @param $str
     * @return string|string[]|null
     */
    protected function hideStr($str)
    {
        if (strpos($str, '@')) {
            $email_array = explode("@", $str);
            //邮箱前缀
            $prevfix = (strlen($email_array[0]) < 4) ? "" : substr($str, 0, 4);
            $count = 0;
            $str = preg_replace('/([\d\w+_-]{0,100})@/', '***@', $str, -1, $count);
            $rs = $prevfix . $str;
        } else {
            //正则手机号
            $pattern = '/(1[3458]{1}[0-9])[0-9]{4}([0-9]{4})/i';
            if (preg_match($pattern, $str)) {
                $rs = preg_replace($pattern, '$1****$2', $str); // substr_replace($name,'****',3,4);
            } else {
                $rs = substr($str, 0, 3) . "***" . substr($str, -1);
            }
        }
        if(empty($rs))
        {
            $rs = '*****';
        }
        return $rs;
    }

    /**
     * 获取post过来的微信信息
     */
    protected function get_wx_info()
    {
        return [
            'unionid'   => $this->request->post('unionid',''),
            'openid'    => $this->request->post('openid',''),
            'nickname'  => $this->request->post('nickname', ''),
            'img'       => $this->request->post('headimgurl', ''),
            'sex'       => $this->request->post('sex', ''),
            'province'  => $this->request->post('province', ''),
            'city'      => $this->request->post('city', ''),
        ];
    }

    /**
     * 百度地址转经纬度
     * @param $address
     * @return array|false
     */
    protected function baidu_address_to_lo_la($address)
    {
        //获取系统配置
        $baiduwebak = $this->redis0->get('baiduwebak');
        $api = "http://api.map.baidu.com/geocoder/v2/?address=$address&output=json&ak=$baiduwebak";
        $content = $this->http->get($api)->getBody()->getContents();
        $arr = json_decode($content,true);
        if(empty($arr))
        {
            return false;
        }
        if($arr['status'] != 0)
        {
            return false;
        }
        if(empty($arr['result']['location']['lng']) || empty($arr['result']['location']['lat']))
        {
            return false;
        }
        return [
            'lo' => $arr['result']['location']['lng'],
            'la' => $arr['result']['location']['lat']
        ];
    }
    /**
     * 百度通过经纬度获取详细地址
     * @param $lo
     * @param $la
     * @return bool|string
     */
    protected function baidu_address($lo,$la)
    {
        //获取系统配置
        $baiduwebak = $this->redis0->get('baiduwebak');
        //调取百度接口,其中ak为百度帐号key,注意location纬度在前，经度在后
        $api = "http://api.map.baidu.com/geocoder/v2/?ak=".$baiduwebak."&location=".$la.",".$lo."&output=json&pois=1";
        $api = 'http://api.map.baidu.com/geocoder/v2/?callback=renderOption&output=json&address=百度大厦&city=北京市&ak=您的ak';
        $content = $this->http->get($api)->getBody()->getContents();
        $arr = json_decode($content,true);
        if(empty($arr))
        {
            return false;
        }
        if($arr['status'] != 0)
        {
            return false;
        }
        if(empty($arr['result']['formatted_address']))
        {
            return false;
        }
        return $arr['result']['formatted_address'];
    }
    /**
     * 百度通过经纬度获取详细地址2
     * @param $lo
     * @param $la
     * @return array|bool
     */
    protected function baidu_address2($lo,$la)
    {
        //获取系统配置
        $baiduwebak = $this->redis0->get('baiduwebak');
        //调取百度接口,其中ak为百度帐号key,注意location纬度在前，经度在后
        $api = "http://api.map.baidu.com/geocoder/v2/?ak=".$baiduwebak."&location=".$la.",".$lo."&output=json&pois=1";
        $content = $this->http->get($api)->getBody()->getContents();
        $arr = json_decode($content,true);
        if(empty($arr))
        {
            return false;
        }
        if($arr['status'] != 0)
        {
            return false;
        }
        $data = [];
        if(!empty($arr['result']['formatted_address']))
        {
            $data['address'] = $arr['result']['formatted_address'];
        }
        if(!empty($arr['result']['addressComponent']['country']))
        {
            $data['country'] = $arr['result']['addressComponent']['country'];
        }
        if(!empty($arr['result']['addressComponent']['province']))
        {
            $data['province'] = $arr['result']['addressComponent']['province'];
        }
        if(!empty($arr['result']['addressComponent']['city']))
        {
            $data['city'] = $arr['result']['addressComponent']['city'];
        }
        if(!empty($arr['result']['addressComponent']['district']))
        {
            $data['district'] = $arr['result']['addressComponent']['district'];
        }
        if(!empty($arr['result']['addressComponent']['adcode']))
        {
            $data['adcode'] = $arr['result']['addressComponent']['adcode'];
        }
        return $data;
    }
    /**
     * 异步分离出来
     * @param $info
     * @param int $tt  延时时间
     */
    protected function yibu($info,$tt = 0)
    {
        if($tt == 0)
        {
            //读取配置是否允许执行
            $db = $this->redis0->get('async_db');
            $redis = $this->container->get(\Redis::class);
            $redis->select((int)$db);
            $job = $this->container->get(DriverFactory::class);
            $job->get('async')->push(new Async($info));
        }else
        {
            //读取配置是否允许执行
            $db = $this->redis0->get('async_db');
            $redis = $this->container->get(\Redis::class);
            $redis->select((int)$db);
            $job = $this->container->get(DriverFactory::class);
            $job->get('async')->push(new Async($info),(int)$tt);
        }
    }

    /**
     * 以倒序/顺序的方式排序指定数组值的内容
     * @param $data           //数据
     * @param $zhi            //指定值
     * @param bool $fangshi   //方式 true 正序  false 倒叙
     * @return mixed
     */
    protected function arr_paixu($data,$zhi,$fangshi = false)
    {
        // 取得列的列表
        foreach ($data as $key => $row)
        {
            $volume[$key]  = $row[$zhi];
        }
        if($fangshi){
            array_multisort($volume, SORT_ASC, $data);
        }else{
            array_multisort($volume, SORT_DESC, $data);
        }
        return $data;
    }

    /**
     * 判断当前时间是否在指定时间段内
     * @return bool
     * @throws Exception
     */
    protected function get_curr_time_section()
    {
        $checkDayStr = date('Y-m-d ',time());
        $timeBegin1 = strtotime($checkDayStr."12:00".":00");
        $timeEnd1 = strtotime($checkDayStr."13:00".":00");

        $curr_time = time();

        if($curr_time >= $timeBegin1 && $curr_time <= $timeEnd1)
        {
            return true;
        }else{
            throw new Exception('上午开放复投时间为：12:00-13:00', 10001);
        }
    }

    /**
     * get_curr_time_section_new
     * @return bool
     * @throws Exception
     */
    protected function get_curr_time_section_new()
    {
        $checkDayStr = date('Y-m-d ',time());
        $timeBegin1 = strtotime($checkDayStr."09:00".":00");
        $timeEnd1 = strtotime($checkDayStr."21:00".":00");

        $curr_time = time();

        if($curr_time >= $timeBegin1 && $curr_time <= $timeEnd1)
        {
            return true;
        }else{
            throw new Exception('交易时间为：上午09：00 - 晚上21：00', 10001);
        }
    }

    /**
     * 判断当前时间是否在指定时间段内2
     * @return bool
     * @throws Exception
     */
    protected function get_curr_time_section_2()
    {
        $checkDayStr = date('Y-m-d ',time());
        $timeBegin1 = strtotime($checkDayStr."19:00".":00");
        $timeEnd1 = strtotime($checkDayStr."20:00".":00");

        $curr_time = time();

        if($curr_time >= $timeBegin1 && $curr_time <= $timeEnd1)
        {
            return true;
        }else{
            throw new Exception('晚上开放复投时间为：19:00-20:00', 10001);
        }
    }

    /**
     * 判断当前时间是否在指定时间段内
     * @return bool
     * @throws Exception
     */
    protected function get_curr_time_section_toucai()
    {
        $checkDayStr = date('Y-m-d ',time());
        $timeBegin1 = strtotime($checkDayStr."12:00".":00");
        $timeEnd1 = strtotime($checkDayStr."13:30".":00");

        $curr_time = time();

        if($curr_time >= $timeBegin1 && $curr_time <= $timeEnd1)
        {
            return true;
        }else{
            throw new Exception('上午开放偷菜时间为：12:00-13:30', 10001);
        }
    }

    /**
     * 判断当前时间是否在指定时间段内2
     * @return bool
     * @throws Exception
     */
    protected function get_curr_time_section_toucai_2()
    {
        $checkDayStr = date('Y-m-d ',time());
        $timeBegin1 = strtotime($checkDayStr."19:00".":00");
        $timeEnd1 = strtotime($checkDayStr."20:30".":00");

        $curr_time = time();

        if($curr_time >= $timeBegin1 && $curr_time <= $timeEnd1)
        {
            return true;
        }else{
            throw new Exception('晚上开放偷菜时间为：19:00-20:30', 10001);
        }
    }


    /**
     * 判断是否请求过于频繁
     * @param $user_id
     * @throws Exception
     */
    protected function check_often($user_id)
    {
        $aaa = $this->redis4->get($user_id.'_start_often');
        if($aaa == 2)
        {
            throw new Exception('请不要点这么快啦，我会受不的了啦', 10001);
        }
    }

    /**
     * 增加请求频繁
     * @param $user_id
     * @param int $time
     */
    protected function start_often($user_id,$time = 2)
    {
        $this->redis4->set($user_id.'_start_often',2);
        $this->redis4->expire($user_id.'_start_often',$time);
    }
    /**
     *
     * @param $user_id
     * @throws Exception
     */
    protected function _check_user($user_id)
    {
        $set_guanfang = $this->redis0->get('set_guanfang');
        if(!empty($set_guanfang))
        {
            $set_guanfang = json_decode($set_guanfang,true);
            if(!in_array($user_id,$set_guanfang))
            {
                throw new Exception('暂未开放', 10001);
            }
        }
    }

    /**
     * 实名认证检测
     * @throws Exception
     */
    protected function _check_auth()
    {
        $userInfo     = Context::get('userInfo');
        if($userInfo['auth'] != 1)
        {
            throw new Exception('请先完成实名认证', 10001);
        }
    }

    /**
     * 获取手续费
     * @param $user_id
     * @return float
     * @throws Exception
     */
    protected function get_shouxu($user_id)
    {
        $huoyuedu = $this->redis5->get($user_id.'_huoyue')?$this->redis5->get($user_id.'_huoyue'):0;
        $h2 = DsUserDatum::query()->where('user_id',$user_id)->sum('huoyuedu');
        $hh    = $huoyuedu+$h2;
        if($hh >= 7200)
        {
            return 0.2;
        }
        if($hh >= 3600)
        {
            return 0.24;
        }
        if($hh >= 1800)
        {
            return 0.28;
        }
        if($hh >= 150)
        {
            return 0.36;
        }
        return 0.5;
    }

    /**
     * @return float
     */
    protected function msectime()
    {
        list($s1, $s2) = explode(' ', microtime());
        return (float)sprintf('%.0f', (floatval($s1) + floatval($s2)) * 1000);
    }

    /**
     * 获取今日剩余秒数
     * @return int
     */
    protected function get_end_t_time()
    {
        return 86400-(time()+8*3600)%86400;
    }

    /**
     * 匹配价格
     * @param $price
     * @return bool
     */
    protected function checkPrice($price)
    {
        $price = strval($price);
        // 可以匹配1.11,10.11  或 0.11
        if (preg_match('/^[1-9]+\d*(.\d{1,2})?$|^\d+.\d{1,2}$/',$price)) {  // ? 0次或1次, + 1次或多次, * 0次或多次
            return true;
        } else {
            return false;
        }
    }

    /**
     * 匹配价格4位
     * @param $price
     * @return bool
     */
    protected function checkPrice_4($price)
    {
        $price = strval($price);
        // 可以匹配1.11,10.11  或 0.11
        if (preg_match('/^[1-9]+\d*(.\d{1,2})?$|^\d+.\d{1,4}$/',$price)) {  // ? 0次或1次, + 1次或多次, * 0次或多次
            return true;
        } else {
            return false;
        }
    }

    /**
     * 删除用户token
     * @param $user_id
     */
    protected function del_user_token($user_id)
    {
        //清除token
        $token = $this->redis2->get($user_id.'_token');
        if($token)
        {
            $this->redis2->del($token);
        }
        $this->redis2->del($user_id.'_token');
    }

    /**
     * 获取今日剩余时间
     * @return int
     */
    protected function get_today_surplus_time()
    {
        $ti = strtotime('23:59:59')-time();
        if(!$ti)
        {
            return 86410;
        }else{
            return (int)$ti;
        }
    }
    /**
     * 获取随机价格
     * @param int $min
     * @param int $max
     * @return string
     */
    protected function randomFloat($min = 0, $max = 1)
    {
        $num = $min + mt_rand() / mt_getrandmax() * ($max - $min);
        return sprintf("%.2f",$num);  //控制小数后几位
    }

    /**
     * 虚拟号码
     * @param $mobile
     * @throws Exception
     */
    protected function check_xunihaoma($mobile)
    {
        $set_xunihaoma = $this->redis0->get('set_xunihaoma');
        if(!empty($set_xunihaoma))
        {
            $mobile = trim($mobile);
            $set_xunihaoma = json_decode($set_xunihaoma,true);
            foreach ($set_xunihaoma as $v)
            {
                switch (strlen($v))
                {
                    case 2:
                        //截取前2位
                        $qe = substr($mobile,0,2);
                        break;
                    case 3:
                        $qe = substr($mobile,0,3);
                        break;
                    case 4:
                        $qe = substr($mobile,0,4);
                        break;
                    case 5:
                        $qe = substr($mobile,0,5);
                        break;
                    case 6:
                        $qe = substr($mobile,0,6);
                        break;
                    case 7:
                        $qe = substr($mobile,0,7);
                        break;
                }
                if($qe == $v)
                {
                    throw new Exception('此虚拟号段已被禁止！', 10001);
                }
            }
        }
    }

    /**
     * 交易禁止检测
     * @param $user_id
     * @param int $type
     * @throws Exception
     */
    protected function _check_fenghao($user_id,$type = 1)
    {
        $time = $this->redis4->get($user_id.'no_jt_time');
        if($time == 1)
        {
            if($type == 1)
            {
                throw new Exception('您账户已被永久禁止交易！', 10001);
            }else{
                throw new Exception('对方账户已被永久禁止交易！', 10001);
            }
        }
        if($time == 2)
        {
            //查询剩余时间
            $ttl = $this->redis4->ttl($user_id.'no_jt_time');
            if($ttl > 0)
            {
                $tt = $this->convert($ttl);
                if($type == 1)
                {
                    throw new Exception('您账户已被禁止交易！剩余解除时间为：'.$tt, 10001);
                }else{
                    throw new Exception('对方账户已被禁止交易！剩余解除时间为：'.$tt, 10001);
                }
            }
        }
    }

    /**
     * 计算时间到天
     * @param $a
     * @return float
     */
    function convert_day($a)
    {
        $a_dt=getdate($a);

        $b_dt=getdate(strtotime(date("Y-m-d")));

        $a_new=mktime(12,0,0,$a_dt['mon'],$a_dt['mday'],$a_dt['year']);

        $b_new=mktime(12,0,0,$b_dt['mon'],$b_dt['mday'],$b_dt['year']);

        return round(abs($a_new-$b_new)/86400);

    }

    /**
     * 计算时间
     * @param $second
     * @return string
     */
    protected function convert($second)
    {
        $newtime = '';
        $d = floor($second / (3600*24));
        $h = floor(($second % (3600*24)) / 3600);
        $m = floor((($second % (3600*24)) % 3600) / 60);
        if ($d>'0') {
            if ($h == '0' && $m == '0') {
                $newtime= $d.'天';
            } else {
                $newtime= $d.'天'.$h.'小时'.$m.'分钟';
            }
        } else {
            if ($h!='0') {
                if ($m == '0') {
                    $newtime= $h.'小时';
                } else {
                    $newtime= $h.'小时'.$m.'分';
                }
            } else {
                if($second == 0)
                {
                    $newtime= '刚刚';
                }else{
                    if($second < 60)
                    {
                        $newtime= $second.'秒';
                    }else{
                        $newtime= $m.'分';
                    }
                }
            }
        }
        return $newtime;
    }

    /**
     * 删除商品缓存
     * @param $goods_id
     */
    protected function del_shop_cache($goods_id)
    {
        $this->redis3->del('self_goods_'.$goods_id);
        $this->redis3->del('self_goods_'.$goods_id.'_kv');
    }

    /**
     * 获取单个商品详情
     * @param $goods_id
     * @return array|mixed
     * @throws Exception
     */
    protected function get_shop_info_self($goods_id)
    {
        //查询缓存数据
        $re = $this->redis3->get('self_goods_'.$goods_id);
        if(!empty($re))
        {
            $shop_info = json_decode($re,true);
            return $shop_info;
        }
        $shop_info = DsShopGoodsDraft::query()->where('goods_id',$goods_id)->first();
        //判断动态是否存在
        if(empty($shop_info))
        {
            throw new Exception('该商品不存在', 10001);
        }
        $shop_info =$shop_info->toArray();
        if($shop_info['goods_class'] != 1)
        {
            throw new Exception('该商品['.$shop_info['name'].']已下架', 10001);
        }
        //判断活动
        if($shop_info['is_huodong'] == 1)
        {
            if($shop_info['endtime'] < time())
            {
                DsShopGoodsDraft::query()->where('goods_id',$goods_id)->update(['goods_class' => 0]);
                throw new Exception('该商品活动已结束！', 10001);
            }
        }
        //缓存
        $this->redis3->set('self_goods_'.$goods_id,json_encode($shop_info));
        //获取商品规格
        if($shop_info['is_option'] == 1)
        {
            //获取商品规格表
            $sku = DsShopGoodsSkuDraft::query()->where('goods_id',$goods_id)
                ->select('sku_id','sku_name','price','original_price','levelnum','thumb')
                ->get()->toArray();
            if(empty($sku))
            {
                throw new Exception('该商品已下架', 10001);
            }
            foreach ($sku as $k2 => $v2)
            {
                if(empty($v2['thumb']))
                {
                    $sku[$k2]['thumb'] = $shop_info['thumb'];
                }
            }
            $this->redis3->set('self_goods_'.$goods_id.'_sku',json_encode($sku));
            $keys = DsShopGoodsAttrKeyDraft::query()->where('goods_id',$goods_id)->select('id','name')->get()->toArray();
            if(!empty($keys))
            {
                foreach ($keys as $k => $v)
                {
                    $keys[$k]['val'] = DsShopGoodsAttrValDraft::query()->where('goods_id',$goods_id)->where('keyid',$v['id'])->pluck('name')->toArray();
                    unset($keys[$k]['id']);
                }
            }
            if(empty($keys))
            {
                throw new Exception('该商品已下架', 10001);
            }
            $this->redis3->set('self_goods_'.$goods_id.'_kv',json_encode($keys));
        }
        return $shop_info;
    }

    /**
     * 判断顶像验证码是否正确
     * @param $token
     * @return bool
     * @throws Exception
     */
    protected function _check_dx_code($token)
    {
        if(empty($token))
        {
            throw new Exception('滑动验证失败，请重新尝试！', 10001);
        }
        /**
         * 构造入参为appId和appSecret
         * appId和前端验证码的appId保持一致，appId可公开
         * appSecret为秘钥，请勿公开
         * token在前端完成验证后可以获取到，随业务请求发送到后台，token有效期为两分钟
         * 正式版
         * c760c93727b70d2cb5713d93e6e7869a
         * 5350902cf72f5bf7bee20cb6478f22ce
         **/

        $appId = "c760c93727b70d2cb5713d93e6e7869a";
        $appSecret = "5350902cf72f5bf7bee20cb6478f22ce";
        $client = new CaptchaClient($appId,$appSecret);
        $client->setTimeOut(2);      //设置超时时间
        $client->setCaptchaUrl("http://proxy-api.dingxiang-inc.com/api/tokenVerify");   //特殊情况需要额外指定服务器,可以在这个指定，默认情况下不需要设置
        $response = $client->verifyToken($token);
        if($response->serverStatus == 'SERVER_SUCCESS' && $response->result == '1')
        {
        }else
        {
            throw new Exception('滑动验证失败，请重新尝试！', 10001);
        }
    }

    /**
     * 检查支付密码对不对
     * @param $num
     * @throws Exception
     */
    protected function _check_pay_password($num,$type = 1)
    {
        if(empty($num))
        {
            throw new Exception('请填写支付密码！', 10001);
        }
        $userInfo     = Context::get('userInfo');
        if(empty($userInfo['pay_password']))
        {
            if($type == 1)
            {
                throw new Exception('请先设置支付密码！', 10001);
            }else{
                throw new Exception('请前往APP设置支付密码！', 10001);
            }
        }
        //判断支付密码是否正确
        if (!password_verify ($num,$userInfo['pay_password']))
        {
            throw new Exception('支付密码不正确！', 10001);
        }
    }

    /**
     * 检查身份后4位对不对
     * @param $num
     * @throws Exception
     */
    protected function _check_card_end_4_bak($num)
    {
        $userInfo     = Context::get('userInfo');
        if(empty($userInfo['auth_num']))
        {
            throw new Exception('身份证后4位不匹配！', 10001);
        }
        if(strlen(strval($userInfo['auth_num'])) < 4)
        {
            throw new Exception('身份证后4位不匹配！', 10001);
        }
        //判断身份证后面是否正确
        $auth_num = substr($userInfo['auth_num'],-4,4);
//        //判断最后一位是否是X
//        if(substr($auth_num,-1,1) == 'X')
//        {
//            $auth_num = substr($auth_num,0,3);
//            $auth_num.= '0';
//        }
        if(strtoupper($num) != strtoupper($auth_num))
        {
            throw new Exception('身份证后4位不匹配！', 10001);
        }
    }

    /**
     * 获取秒
     * @return int
     */
    protected function getMillisecond()
    {
        return intval(microtime(true) * 1000);
    }

    /**
     * @return false|string
     */
    protected function get_yesterday()
    {
        return date('Y-m-d',strtotime("-1 day"));
    }

    /**
     * 更新vip摇号数字
     */
    protected function update_vip_yaohao_num()
    {
        $update_vip_yaohao_num = $this->redis0->get('update_vip_yaohao_num');
        if($update_vip_yaohao_num != 2)
        {
            $this->redis0->set('update_vip_yaohao_num',2,3);

            $day = date('Y-m-d');
            $rrr = $this->redis0->exists($day.'_vip_num');
            if(!$rrr)
            {
                $arr = $this->redis0->sRandMember('vip_num',3);
                $this->redis0->sAddArray($day.'_vip_num',$arr);
            }
        }
    }
    /**
     * 更新申购摇号数字
     */
    protected function update_shengou_num()
    {
        $update_shengou_num = $this->redis0->get('update_shengou_num');
        if($update_shengou_num != 2)
        {
            $this->redis0->set('update_shengou_num',2,3);

            $day = date('Y-m-d');
            $rrr = $this->redis0->exists($day.'_shengou_num');
            if(!$rrr)
            {
                $arr = $this->redis0->sRandMember('shengou_num',3);
                $this->redis0->sAddArray($day.'_shengou_num',$arr);
            }
        }
    }

    protected function check_vip_and_huoyue150($user_id)
    {
        //判断是否是会员
        if(!$this->_is_vip($user_id))
        {
            //判断是否150活跃度
            $huoyuedu = $this->redis5->get($user_id.'_huoyue')?$this->redis5->get($user_id.'_huoyue'):0;
            $h2 = DsUserDatum::query()->where('user_id',$user_id)->sum('huoyuedu');
            $hh    = $huoyuedu+$h2;
            if($hh < 150)
            {
                throw new Exception('请先开通会员或者活跃度大于等于150！', 10001);
            }
        }
        //判断是否复投一次
        $co = DsUserTaskPack::query()->where('user_id',$user_id)
            ->whereIn('task_pack_id',[1,2,3,4,5,6])->count();
        if($co <= 0)
        {
            throw new Exception('请先用影票兑换一次任意流量包永久激活该权限。！', 10001);
        }
    }
    protected function check_futou($user_id)
    {
        //判断是否复投一次
        $co = DsUserTaskPack::query()->where('user_id',$user_id)
            ->whereIn('task_pack_id',[1,2,3,4,5,6])->count();
        if($co <= 0)
        {
            throw new Exception('请先用影票兑换一次任意流量包永久激活通证消费权限。。！', 10001);
        }
    }

    //检查接口版本
    protected function _check_version($version)
    {
        $app_version = $this->redis0->get('app_version');
        if(!empty($app_version))
        {
            $app_version = json_decode($app_version,true);
            if(!empty($app_version['android']['version_code']))
            {
                if($version != $app_version['android']['version_code'])
                {
                    throw new Exception('您的APP版本过低，请更新最新版本！', 10001);
                }
            }
        }
    }

    //通证价格
    protected function tz_beilv()
    {
        return $this->redis0->get('tz_to_rmb');
    }
}