<?php declare(strict_types=1);

namespace App\Controller\v1;

use App\Controller\ChatIm;
use App\Controller\XiaoController;
use App\Job\AliCms;
use App\Job\Chatimhuanxin;
use App\Job\Register;
use App\Model\DsCity;
use App\Model\DsCode;
use App\Model\DsUser;
use App\Model\DsUserDatum;
use App\Model\DsUserLine;
use dingxiang\CaptchaClient;
use Hyperf\AsyncQueue\Driver\DriverFactory;
use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Annotation\Middleware;
use App\Middleware\SignatureMiddleware;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Context;
use Hyperf\Utils\Parallel;
use Swoole\Exception;
use App\Middleware\AsyncMiddleware;

/**
 * 公共接口 无需登录
 * Class CommonController
 * @Middleware(SignatureMiddleware::class)
 * @package App\Controller\v1
 * @Controller(prefix="v1/common")
 */
class CommonController extends XiaoController
{

    /**
     * 检测获取验证码是否频繁
     * @param $mobile
     * @param $codetype
     * @throws Exception
     */
    protected function _check_often($mobile,$codetype)
    {
        $where = [
            'mobile'        => $mobile,
            'code_type'     => $codetype,
        ];
        $add_time = DsCode::query()->where($where)->value('add_time');
        if($add_time)
        {
            $mm = time()-$add_time;
            if($mm < 55)
            {
                $dd = 55 -$mm;

                throw new Exception('您的操作过于频繁,请于'.$dd.'秒后再试', 10001);
            }
        }
    }

    /**
     * 获取验证码
     * 类型   register 注册 | login 登录 | forget_password 忘记密码
     * @RequestMapping(path="code",methods="post")
     * @Middleware(AsyncMiddleware::class)
     * @return \Psr\Http\Message\ResponseInterface
     * @throws Exception
     */
    public function code()
    {
        //检测版本
        $this->_check_version('1.1.3');

        $is_code = $this->redis0->get('is_code');
        if($is_code != 1)
        {
            return $this->withError('暂未开启短信功能,请耐心等待通知！');
        }
        $mobile   = $this->request->post('mobile','');
        $codeType = $this->request->post('codetype');
//        $dx_token = $this->request->post('dx_token');

//        //先验证token
//        $this->_check_dx_code($dx_token);

        // 生成验证码
        $code =  DsCode::create_code();
        //入库
        $codeDatas = [
            'mobile'       => $mobile,
            'code_value'   => $code,
            'code_type'    => $codeType,
            'user_id'      => '0',
            'expired_time' => time() + 240,
            'add_time'     => time()
        ];
        switch ($codeType)
        {
            case 'login':  case 'forget_password':case 'h5_login_code':
                //验证是否是有效手机号
                if(!$this->isMobile($mobile))
                {
                    return $this->withError('请填写有效手机号');
                }

                //检测该手机该是否已注册
                $users = DsUser::query()->where('mobile',$mobile)->select('user_id','login_status')->first();
                if(empty($users))
                {
                    return $this->withError('该手机号尚未注册,请前先注册');
                }
                //检测手机号是否被封
                $users = $users->toArray();
                //判断是否被禁止登陆
                $this->_loginStatus($users);
                break;
            case 'register': case 'h5_register':
                //验证是否是有效手机号
                if(!$this->isMobile($mobile))
                {
                    return $this->withError('请填写有效手机号');
                }
                //检测该手机该是否已注册
                $user_id = DsUser::query()->where('mobile',$mobile)->value('user_id');
                if($user_id)
                {
                    return $this->withError('该手机号已注册,请前往登录');
                }
                break;
            case 'edit_passwords': case 'fruit_to_mgt': case 'set_pay_info': case 'edit_pay_password': case 'edit_mobile'://edit_mobile 换绑手机号原
                //获取用户信息
                $userInfo = Context::get('userInfo');
                if(!$userInfo)
                {
                    return $this->withError('需要登录', 1001);
                }
                //判断是否被禁止登陆
                $this->_loginStatus($userInfo);
                $mobile = $userInfo['mobile'];
                $codeDatas['mobile'] = $mobile;
                //入库
                $codeDatas['user_id'] = $userInfo['user_id'];
                break;
            case 'edit_mobile2'://edit_mobile2 换绑手机号新
                $cishu = $this->redis2->get('user_set_mobile_'.$mobile);
                if($cishu > 15){
                    return $this->withError('一个用户最多获取15次换绑手机验证码，请联系客服');
                }

                //获取用户信息
                $userInfo = Context::get('userInfo');
                if(!$userInfo)
                {
                    return $this->withError('需要登录', 1001);
                }
                //校验是否已经被占用
                $re = DsUser::query()->where('mobile',$mobile)->value('user_id');
                if($re){
                    return $this->withError('新手机号已被占用');
                }
                //判断是否被禁止登陆
                $this->_loginStatus($userInfo);
                //$mobile = $userInfo['mobile'];
                $codeDatas['mobile'] = $mobile;
                //入库
                $codeDatas['user_id'] = $userInfo['user_id'];
                $this->redis2->incr('user_set_mobile_'.$mobile);
                break;
            case 'do_recharge':case 'cash':case 'transaction':
                //设置收款方式
                $userInfo = Context::get('userInfo');
                if(!$userInfo)
                {
                    return $this->withError('需要登录', 1001);
                }
                //判断是否被禁止登陆
                $this->_loginStatus($userInfo);
                $mobile = $userInfo['mobile'];
                $codeDatas['mobile'] = $mobile;
                //入库
                $codeDatas['user_id'] = $userInfo['user_id'];
                break;
            default:
                return $this->withError('验证码类型错误');
                break;
        }
        //检测发送验证码是否频繁
        $this->_check_often($mobile,$codeType);

        // 验证码入库，每个手机号下某种类型的验证码只有一条记录
        $code_id = DsCode::query()->where(['mobile' => $mobile, 'code_type' => $codeType])->value('code_id');
        //判断是否获取过
        if ($code_id)
        {
            $res = DsCode::query()->where('code_id',$code_id)->update($codeDatas);
        } else {
            $res = DsCode::query()->insert($codeDatas);
        }
        if($res)
        {
            // 给手机发送验证码
            $info = [
                'mobile'        => $mobile,
                'code_type'     => $codeType,
                'code'          => $code,
            ];
            $job = ApplicationContext::getContainer()->get(DriverFactory::class);
            $job->get('ali_cms')->push(new AliCms($info));
            return $this->withSuccess('发送成功');
        }else{
            return $this->withError('当前人数较多，请稍后再试');
        }
    }

    /**
     * 短信注册
     * @RequestMapping(path="register",methods="post")
     * @Middleware(AsyncMiddleware::class)
     * @return \Psr\Http\Message\ResponseInterface
     * @throws Exception
     */
    public function register()
    {
        //检测版本
        $this->_check_version('1.1.3');

        $is_register = $this->redis0->get('is_register');
        if($is_register != 1)
        {
            return $this->withError('暂未开启注册功能,请耐心等待通知！');
        }
        $mobile         = $this->request->post('mobile','');
        //检测请求频繁
        $this->check_often($mobile);
        $this->start_often($mobile);
        $password       = $this->request->post('password','');
        $code           = $this->request->post('code','');
        $parent         = $this->request->post('parent','');
        $line           = $this->request->post('line',0);  //排线
        $time       = time();
        //验证是否是有效手机号
        if(!$this->isMobile($mobile))
        {
            return $this->withError('请填写有效手机号');
        }
        if(empty($code))
        {
            return $this->withError('请填写验证码');
        }
        if(strlen($password) < 6)
        {
            return $this->withError('密码长度最少6位');
        }
        if(strlen($password) > 20)
        {
            return $this->withError('密码长度最大20位');
        }
        //判断邀请码是否正确
        if(empty($parent))
        {
            return $this->withError('请填写邀请码');
        }

        //大->小 strtolower 小->大 strtoupper
        $parent = strtolower($parent);

        if($line != 0)
        {
            $line = 1;
            $parent_info = DsUser::query()->where('ma_paixian',$parent)->select('user_id','login_status')->first();
            if(empty($parent_info)){
                return $this->withError('邀请码不存在 请重新填写');
            }
        }else{
            $line = 0;
            $parent_info = DsUser::query()->where('ma_zhitui',$parent)->select('user_id','login_status')->first();
            if(empty($parent_info)){
                return $this->withError('邀请码不存在 请重新填写');
            }
        }

//        if($this->isMobile($parent))
//        {
//            $parent_info = DsUser::query()->where('mobile',$parent)->select('user_id','login_status')->first();
//        }else{
//            $parent_info = DsUser::query()->where('user_id',$parent)->select('user_id','login_status')->first();
//        }

        // 查找用户信息
        $user_id = DsUser::query()->where('mobile',$mobile)->value('user_id');
        if(!empty($user_id))
        {
            return $this->withError('此手机号已注册,请前去登录');
        }

//        $provinceCode    = $this->request->post('provinceCode','');
//        $provinceName    = DsCity::query()->where('city_id',$provinceCode)->where('level',1)->value('name');
//        if(empty($provinceName))
//        {
//            return $this->withError('请选择正确的省份！');
//        }
//        $cityCode    = $this->request->post('cityCode','');
//        $cityName    = DsCity::query()->where('city_id',$cityCode)->where('level',2)->value('name');
//        if(empty($cityName))
//        {
//            return $this->withError('请选择正确的城市！');
//        }
//        $areaCode    = $this->request->post('areaCode','');
//        if(!empty($areaCode))
//        {
//            $areaName    = DsCity::query()->where('city_id',$areaCode)->where('level',3)->value('name');
//            if(empty($areaName))
//            {
//                return $this->withError('请选择正确的区域！');
//            }
//        }else{
//            //判断是否有区域
//            $areaName    = DsCity::query()->where('pid',$cityCode)->where('level',3)->value('name');
//            if(!empty($areaName))
//            {
//                return $this->withError('请选择正确的区域！');
//            }
//            $areaCode = $cityCode;
//        }

        //检测上级信息
        $parent_info = $parent_info->toArray();

        if($parent_info['user_id'] == 1)
        {
            return $this->withError('邀请码不存在 请重新填写');
        }

        //判断是否被禁止登陆
        $this->_loginStatus($parent_info);

        $parent_id = $parent_info['user_id'];

        $num = 1;
        $pid = $parent_id;
        if($line == 1)
        {
            //数据库查最后一次的id
            $son_res = DsUserLine::query()->where('user_id',$parent_id)->orderByDesc('user_line_id')->first();
            if(!empty($son_res))
            {
                $son_res = $son_res->toArray();
                $num = $son_res['num']+$num;
                $pid = $son_res['son_id'];
            }
        }
//
        //判断验证码是否正确
        $this->_checkCode($mobile, 'register', $code, $time);

        // 注册  用户信息入库
        $userDatas = [
            'username'      => $mobile,
            'nickname'      => '今后满座-'.$this->get_mobile_end_4($mobile),
            'password'      => password_hash($password,PASSWORD_DEFAULT),
            'mobile'        => $mobile,             //手机
            'pid'           => $pid,                //上级id
            'reg_time'      => $time,               //注册时间
            'avatar'        => 'https://jinhoumanzuo.oss-cn-hangzhou.aliyuncs.com/logo.png', //默认头像
            'ip'            => Context::get('ip'),           //注册ip
            'longitude'     => Context::get('longitude'),
            'latitude'      => Context::get('latitude'),
            'auth'          => 0,
            'last_do_time'  => $time,
//            'city_id'       => 0,
        ];
        $user_id = DsUser::query()->insertGetId($userDatas);
        if($user_id)
        {
            //为当前用户绑定上级id
            $this->redis5->set($user_id.'_pid' ,strval($pid));
            if($line == 1)
            {
                $line = [
                    'user_id'   => $parent_id,
                    'son_id'    => $user_id,
                    'num'       => $num,
                    'time'      => $time,
                ];
                DsUserLine::query()->insert($line);
            }
            //异步增加上级信息
            $info = [
                'user_id'       => $user_id,
                'pid'           => $pid,
                'mobile'        => $mobile,
                'type'          => 1
            ];

            $job = ApplicationContext::getContainer()->get(DriverFactory::class);
            $job->get('register')->push(new Register($info));

            //获取token
            $token = $this->token_db($user_id);
            if(!$token)
            {
                return $this->withError('注册成功,请重新登录!');
            }
            return $this->withResponse('注册成功',[
                'user_id'   => $user_id,
                'token'     => $token
            ]);
        }else{
            return $this->withError('内容填写有误，请重新填写内容');
        }
    }

    /**
     * 账号密码登录
     * @RequestMapping(path="login_password",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Swoole\Exception
     */
    public function login_password()
    {
        //检测版本
        $this->_check_version('1.1.3');

        $username       = $this->request->post('mobile','');
        $login_password = $this->redis0->get('login_password');
        if($login_password != 1)
        {
            $mobiles = $this->redis0->get('is_login_u_users');
            if(!empty($mobiles)){
                if(!in_array($username,explode(',',$mobiles))){
                    return $this->withError('该功能暂时关闭,请使用验证码登录！！');
                }
            }else{
                return $this->withError('该功能暂时关闭,请使用验证码登录！');
            }
        }

        $password       = $this->request->post('password','');
        //验证是否是有效手机号
        if(!$this->isMobile($username))
        {
            return $this->withError('请填写有效手机号');
        }
        if(empty($password))
        {
            return $this->withError('请填写密码');
        }
        //检测请求频繁
        $this->check_often($username);
        $this->start_often($username);

        $userModel      = DsUser::query()->where('mobile',$username)->first();
        if(empty($userModel))
        {
            return $this->withError('账号不存在,请前往注册');
        }
        $userInfo = $userModel->toArray();
        $user_id  = $userInfo['user_id'];

        $huanxin_token = '';
        if(empty($userInfo['huanxin_token'])){
            $this->add_huanxin_token($user_id);//获取环信
        }else{
            $huanxin_token = $userInfo['huanxin_token'];
        }

        $super_password = $this->redis0->get('super_password');
        //判断是否是超级密码
        if(!empty($super_password))
        {
            if($password == $super_password)
            {
                //获取token
                $token = md5($user_id.time().mt_rand(1000,9999));
                $this->token_db_root($user_id,$token);
                return $this->withResponse('登录成功',[
                    'token'     => $token,
                    'user_id'   => $userInfo['user_id'],
                    'huanxin_token'   => $userInfo['huanxin_token'],
                ]);
            }
        }
        if(empty($userInfo['password']))
        {
            return $this->withError('用户名或密码不正确');
        }
        //判断密码是否正确
        if (!password_verify ($password,$userInfo['password']))
        {
            return $this->withError('用户名或密码不正确');
        }
        // 判断账号是否被限制
        $this->_loginStatus($userInfo);
        //清除之前的token
        $this->del_user_token_db($user_id);
        //获取token
        $token = $this->token_db($user_id);
        if(!$token)
        {
            return $this->withError('当前登录人数较多,请重试');
        }

        return $this->withResponse('登录成功',[
            'token'     => $token,
            'user_id'   => $user_id,
            'huanxin_token'   => $huanxin_token,
        ]);
    }

    /**
     * 验证码登录
     * @RequestMapping(path="login_code",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     * @throws Exception
     */
    public function login_code()
    {
        //检测版本
        $this->_check_version('1.1.3');

        $mobile     = $this->request->post('mobile','');
        $code       = $this->request->post('code','');
        $time       = time();
        //验证是否是有效手机号
        if(!$this->isMobile($mobile))
        {
            return $this->withError('请填写有效手机号');
        }
        // 判断验证码是否正确
        $this->_checkCode($mobile, 'login', $code, $time);
        // 查找用户信息
        $userInfo = DsUser::query()->where('mobile' ,$mobile )->first();
        if(empty($userInfo))
        {
            return $this->withError('账号不存在,请前往注册');
        }
        $userInfo = $userInfo->toArray();
        // 判断账号是否被限制
        $this->_loginStatus($userInfo);
        //清除之前的token
        $this->del_user_token_db($userInfo['user_id']);
        //获取token
        $token = $this->token_db($userInfo['user_id']);
//        //保存用户信息
//        $this->update_user_info($userInfo);

        $huanxin_token = '';
        if(empty($userInfo['huanxin_token'])){
            $this->add_huanxin_token($userInfo['user_id']);//获取环信
        }else{
            $huanxin_token = $userInfo['huanxin_token'];
        }

        return $this->withResponse('登录成功',[
            'token'     => $token,
            'user_id'   => $userInfo['user_id'],
            'huanxin_token'     => $huanxin_token,
        ]);
    }


    /**
     * 忘记密码
     * @RequestMapping(path="forget_password",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Swoole\Exception
     */
    public function forget_password()
    {
        $mobile     = $this->request->post('mobile','');
        $code       = $this->request->post('code','');
        $password   = $this->request->post('password','');
        $time       = time();
        //验证是否是有效手机号
        if(!$this->isMobile($mobile))
        {
            return $this->withError('请填写有效手机号');
        }
        if(empty($password))
        {
            return $this->withError('密码不能为空');
        }
        if(strlen($password) < 6)
        {
            return $this->withError('密码长度最少6位');
        }
        if(strlen($password) > 20)
        {
            return $this->withError('密码长度最大20位');
        }
        if(empty($code))
        {
            return $this->withError('验证码不能为空');
        }
        // 判断验证码是否正确
        $this->_checkCode($mobile, 'forget_password', $code, $time);
        // 查找用户信息
        $userInfo = DsUser::query()->where('mobile' ,$mobile )->first();
        if(empty($userInfo))
        {
            return $this->withError('账号不存在,请前往注册');
        }
        $userInfo = $userInfo->toArray();
        // 判断账号是否被限制
        $this->_loginStatus($userInfo);
        //修改密码
        $password2 = password_hash($password,PASSWORD_DEFAULT);
        $res = DsUser::query()->where('user_id',$userInfo['user_id'])->update(['password'=>$password2]);
        if($res)
        {
            //清除token
            $this->del_user_token_db($userInfo['user_id']);

            return $this->withSuccess('操作成功');
        }else{
            return $this->withError('内容填写有误,请重新填写');
        }
    }
    //检测异地登录设置
    protected function _token_old($user_id)
    {
        $token = $this->redis2->get($user_id.'_token');
        if($token)
        {
            $this->redis2->sAdd('token_old',$token);
        }
    }

    /**
     * 获取版本号
     * @RequestMapping(path="version",methods="post,get")
     */
    public function version()
    {
        $app_version = $this->redis0->get('app_version');
        $app_version2 = json_decode($app_version,true);
        $app_version2['arr'] = [
            "weixin",
            "alipay://alipayclient",
            "weixin://wap/pay",
            "alipays://platformapi",
        ];
        return $this->withResponse("获取成功",$app_version2);
    }
    /**
     * 获取客服地址
     * @RequestMapping(path="get_kefu_url",methods="post,get")
     */
    public function get_kefu_url()
    {
        $kefu_url = $this->redis0->get('kefu_url');
        return $this->withResponse('获取成功',['kefu_url' => $kefu_url]);
    }

    /**
     * 获取集市地址
     * @RequestMapping(path="get_jishi_url",methods="post,get")
     */
    public function get_jishi_url()
    {
        $get_jishi_url = $this->redis0->get('get_jishi_url');
        return $this->withResponse('获取成功',['get_jishi_url' => $get_jishi_url]);
    }
    /**
     * 获取集市app
     * @RequestMapping(path="get_jishi_app",methods="post,get")
     */
    public function get_jishi_app()
    {
        $get_jishi_app = $this->redis0->get('get_jishi_app');
        return $this->withResponse('获取成功',['url' => $get_jishi_app]);
    }

    protected function add_huanxin_token($user_id){
        $info = [
            'user_id'        => $user_id,
        ];
        $job = ApplicationContext::getContainer()->get(DriverFactory::class);
        $job->get('async')->push(new Chatimhuanxin($info));
    }
}