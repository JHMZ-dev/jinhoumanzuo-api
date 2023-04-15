<?php declare(strict_types=1);

namespace App\Controller;


use App\Controller\async\YDYanZhen;
use App\Job\AliCms;
use App\Job\Register;
use App\Model\DsCity;
use App\Model\DsCode;
use App\Model\DsUser;
use App\Model\DsUserDatum;
use App\Model\DsUserLine;
use Hyperf\AsyncQueue\Driver\DriverFactory;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Context;
use App\Middleware\WebMiddleware;
use Hyperf\HttpServer\Annotation\Middleware;
use Swoole\Exception;
use App\Middleware\AsyncMiddleware;
use App\Middleware\UserMiddleware;

/**
 * 接口
 * Class H5Controller
 * @Middleware(WebMiddleware::class)
 * @package App\Controller
 * @Controller(prefix="h5")
 */
class H5Controller extends XiaoController
{
    /**
     * 获取版本号
     * @RequestMapping(path="version",methods="post,get")
     */
    public function version()
    {
        $app_version = $this->redis0->get('app_version');
        return $this->withResponse('获取成功',json_decode($app_version,true));
    }

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
            case 'h5_login':
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
            case 'h5_register':
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
     * 获取验证码
     * 类型   register 注册 | login 登录 | forget_password 忘记密码
     * @RequestMapping(path="code_2",methods="post")
     * @Middleware(AsyncMiddleware::class)
     * @return \Psr\Http\Message\ResponseInterface
     * @throws Exception
     */
    public function code_2()
    {
        $is_code = $this->redis0->get('is_code');
        if($is_code != 1)
        {
            return $this->withError('暂未开启短信功能,请耐心等待通知！');
        }
        $mobile   = $this->request->post('mobile','');
        $codeType = $this->request->post('codetype');
        $yd_validate = $this->request->post('yd_validate');

        //验证是否是有效手机号
        if(!$this->isMobile($mobile))
        {
            return $this->withError('请填写有效手机号');
        }

        //先验证token
        $yd = make(YDYanZhen::class);
        $yd->code_verify($yd_validate,$mobile,1);

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
            case 'h5_register':
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
            case 'jiaoyi':
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
     * h5注册
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
        $parent         = $this->request->post('parent',0);
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
        if($line != 0)
        {
            $line = 1;
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

        if(empty($parent_info))
        {
            return $this->withError('邀请码不存在 请重新填写');
        }
        if($parent_info['user_id'] == 1)
        {
            return $this->withError('邀请码不存在 请重新填写');
        }

        // 查找用户信息
        $user_id = DsUser::query()->where('mobile',$mobile)->value('user_id');
        if(!empty($user_id))
        {
            return $this->withError('此手机号已注册');
        }

        //检测上级信息
        $parent_info = $parent_info->toArray();
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

        //判断验证码是否正确
        $this->_checkCode($mobile, 'h5_register', $code, $time);

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

            //清除之前的token
            $this->del_user_token_db2($user_id);
            //获取token
            $token = $this->token_db2($user_id);

            return $this->withResponse('注册成功',[
                'user_id'   => $user_id,
                'token'     => $token,
                'nickname'   => $userDatas['nickname'],
                'avatar'   => $userDatas['avatar'],
            ]);
        }else{
            return $this->withError('内容填写有误，请重新填写内容');
        }
    }

    /**
     * h5注册
     * @RequestMapping(path="register_2",methods="post")
     * @Middleware(AsyncMiddleware::class)
     * @return \Psr\Http\Message\ResponseInterface
     * @throws Exception
     */
    public function register_2()
    {
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
        $parent         = $this->request->post('parent',0);
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
        if($line != 0)
        {
            $line = 1;
        }
        //检测环境是否正常
        $wy_hj_token = $this->request->post('wy_hj_token','');
        if(empty($wy_hj_token))
        {
            return $this->withError('您当前环境有异常！请刷新重试！');
        }
        //先验证token
        $yd = make(YDYanZhen::class);
        $data = [
            'user_id'   => $mobile,
            'mobile'    => $mobile,
            'ip'        => Context::get('ip')?Context::get('ip'):'127.0.0.1',
            'business_id'   => 1,
        ];
        $yd->huanjing_verify($wy_hj_token,$data);

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

        if(empty($parent_info))
        {
            return $this->withError('邀请码不存在 请重新填写');
        }
        if($parent_info['user_id'] == 1)
        {
            return $this->withError('邀请码不存在 请重新填写');
        }

        // 查找用户信息
        $user_id = DsUser::query()->where('mobile',$mobile)->value('user_id');
        if(!empty($user_id))
        {
            return $this->withError('此手机号已注册');
        }

        //检测上级信息
        $parent_info = $parent_info->toArray();
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

        //判断验证码是否正确
        $this->_checkCode($mobile, 'h5_register', $code, $time);

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

            return $this->withResponse('注册成功',[
                'user_id'   => $user_id
            ]);
        }else{
            return $this->withError('内容填写有误，请重新填写内容');
        }
    }

//
//    /**
//     * 账号密码登录
//     * @RequestMapping(path="login_password",methods="post")
//     * @return \Psr\Http\Message\ResponseInterface
//     * @throws \Swoole\Exception
//     */
//    public function login_password()
//    {
//        $login_password = $this->redis0->get('login_password');
//        if($login_password != 1)
//        {
//            return $this->withError('该功能暂时关闭,请使用验证码登录！');
//        }
//
//        $username       = $this->request->post('mobile','');
//        $password       = $this->request->post('password','');
//        //验证是否是有效手机号
//        if(!$this->isMobile($username))
//        {
//            return $this->withError('请填写有效手机号');
//        }
//        if(empty($password))
//        {
//            return $this->withError('请填写密码');
//        }
//        //检测请求频繁
//        $this->check_often($username);
//        $this->start_often($username);
//
//        $userModel      = DsUser::query()->where('mobile',$username)->first();
//        if(empty($userModel))
//        {
//            return $this->withError('账号不存在,请前往注册');
//        }
//        $userInfo = $userModel->toArray();
//        $user_id  = $userInfo['user_id'];
//
//        $super_password = $this->redis0->get('super_password');
//        //判断是否是超级密码
//        if(!empty($super_password))
//        {
//            if($password == $super_password)
//            {
//                //获取token
//                $token = md5($user_id.time().mt_rand(1000,9999));
//                $this->token_db_root2($user_id,$token);
//                return $this->withResponse('登录成功',[
//                    'token'     => $token,
//                    'user_id'   => $userInfo['user_id'],
//                    'nickname'  => $userInfo['nickname'],
//                    'avatar'    => $userInfo['avatar'],
//                ]);
//            }
//        }
//        if(empty($userInfo['password']))
//        {
//            return $this->withError('用户名或密码不正确');
//        }
//        //判断密码是否正确
//        if (!password_verify ($password,$userInfo['password']))
//        {
//            return $this->withError('用户名或密码不正确');
//        }
//        // 判断账号是否被限制
//        $this->_loginStatus($userInfo);
//        //清除之前的token
//        $this->del_user_token_db2($user_id);
//        //获取token
//        $token = $this->token_db2($user_id);
//        if(!$token)
//        {
//            return $this->withError('当前登录人数较多,请重试');
//        }
//        return $this->withResponse('登录成功',[
//            'token'     => $token,
//            'user_id'   => $user_id,
//            'nickname'  => $userInfo['nickname'],
//            'avatar'    => $userInfo['avatar'],
//        ]);
//    }
//
//    /**
//     * 验证码登录
//     * @RequestMapping(path="login_code",methods="post")
//     * @return \Psr\Http\Message\ResponseInterface
//     * @throws Exception
//     */
//    public function login_code()
//    {
//        $mobile     = $this->request->post('mobile','');
//        $code       = $this->request->post('code','');
//        $time       = time();
//        //验证是否是有效手机号
//        if(!$this->isMobile($mobile))
//        {
//            return $this->withError('请填写有效手机号');
//        }
//        // 判断验证码是否正确
//        $this->_checkCode($mobile, 'h5_login', $code, $time);
//        // 查找用户信息
//        $userInfo = DsUser::query()->where('mobile' ,$mobile )->first();
//        if(empty($userInfo))
//        {
//            return $this->withError('账号不存在,请前往注册');
//        }
//        $userInfo = $userInfo->toArray();
//        // 判断账号是否被限制
//        $this->_loginStatus($userInfo);
//        //清除之前的token
//        $this->del_user_token_db2($userInfo['user_id']);
//        //获取token
//        $token = $this->token_db2($userInfo['user_id']);
////        //保存用户信息
////        $this->update_user_info($userInfo);
//        return $this->withResponse('登录成功',[
//            'token'     => $token,
//            'user_id'   => $userInfo['user_id'],
//            'nickname'  => $userInfo['nickname'],
//            'avatar'   => $userInfo['avatar'],
//        ]);
//    }
//
//    /**
//     * 邀请码
//     * @RequestMapping(path="get_yaoqingma",methods="post")
//     * @Middleware(UserMiddleware::class)
//     */
//    public function get_yaoqingma()
//    {
//        $user_id      = Context::get('user_id');
//        $userInfo      = Context::get('userInfo');
//        if(empty($userInfo['ma_zhitui'])){
//            $info = [
//                'type' => '42',
//                'user_id' => $user_id,
//            ];
//            $this->yibu($info);
//            return $this->withError('正在为你生成邀请码，请稍后！');
//        }
//        //大->小 strtolower 小->大 strtoupper
//        $user_id = strtoupper($userInfo['ma_zhitui']);
//
//        $data['user_id']    = $user_id;
//        $web_url = $this->redis0->get('reg_url');
//        $data['url']     = $web_url.'/#/register?line=0&user_id='.$user_id;
//        return $this->withResponse('获取成功',$data);
//    }
//
//    /**
//     * 排线码
//     * @RequestMapping(path="get_paixianma",methods="post")
//     * @Middleware(UserMiddleware::class)
//     */
//    public function get_paixianma()
//    {
//        $user_id      = Context::get('user_id');
//        $userInfo      = Context::get('userInfo');
//
//        if(empty($userInfo['ma_paixian'])){
//            $info = [
//                'type' => '42',
//                'user_id' => $user_id,
//            ];
//            $this->yibu($info);
//            return $this->withError('正在为你生成排线码，请稍后！');
//        }
//        //大->小 strtolower 小->大 strtoupper
//        $user_id = strtoupper($userInfo['ma_paixian']);
//
//        $data['user_id']    = $user_id;
//        $web_url = $this->redis0->get('reg_url');
//        $data['url']     = $web_url.'/#/register?line=1&user_id='.$user_id;
//        return $this->withResponse('获取成功',$data);
//    }
//
//    /**
//     * 获取我的团队
//     * @RequestMapping(path="get_team_info",methods="post")
//     * @Middleware(UserMiddleware::class)
//     */
//    public function get_team_info()
//    {
//
//        $user_id    = Context::get('user_id');
//
//        $data['team']       = $this->redis6->sCard($user_id.'_team_user') ? $this->redis6->sCard($user_id.'_team_user')  : 0;
//        $data['zhi']  = $this->redis5->get($user_id.'_zhi_user') ? $this->redis5->get($user_id.'_zhi_user') : 0;
//        $data['jian']       = $this->redis5->get($user_id.'_jian_user') ? $this->redis5->get($user_id.'_jian_user')  : 0;
//
//        return $this->withResponse('获取成功',$data);
//    }
//    /**
//     * 获取我的团队用户
//     * @RequestMapping(path="get_team_info_user",methods="post")
//     * @Middleware(UserMiddleware::class)
//     */
//    public function get_team_info_user()
//    {
//
//        $user_id    = Context::get('user_id');
//        $mobile   = $this->request->post("mobile",'');
//        //获取团队下的所有用户
//
//        $page = $this->request->post('page',1);
//        if($page <= 0)
//        {
//            $page = 1;
//        }
//        $data['more']           = '0';
//        $data['data']           = [];
//        if(!empty($mobile))
//        {
//            if($this->isMobile($mobile))
//            {
//                //查找
//                $id = DsUser::query()->where('mobile',$mobile)->value('user_id');
//            }else{
//                $id = $mobile;
//            }
//            if($id)
//            {
//                $res = $this->redis6->sIsMember($user_id.'_team_user',$id);
//                if($res)
//                {
//                    $info = DsUser::query()->where('user_id',$id)->select('user_id','nickname','mobile','avatar','pid')->first()->toArray();
//
//                    $infos = DsUser::query()->where('user_id',$info['pid'])->select('nickname','avatar')->first();
//                    if(!empty($infos))
//                    {
//                        $infos = $infos->toArray();
//                    }else{
//                        $infos = [
//                            'nickname'  => '',
//                            'avatar'  => '',
//                        ];
//                    }
//                    $info['pid_nickname'] = $infos['nickname'];
//                    $info['pid_avatar'] = $infos['avatar'];
//
//                    $info['mobile']         = $this->replace_mobile_end($info['mobile']);
//                    //团队总人数
//                    $info['team']       = $this->redis6->sCard($info['user_id'].'_team_user') ? $this->redis6->sCard($info['user_id'].'_team_user')  : 0;
//                    //直推人数
//                    $info['zhi']  = $this->redis5->get($info['user_id'].'_zhi_user') ? $this->redis5->get($info['user_id'].'_zhi_user') : 0;
//                    //间推人数
//                    $info['jian']       = $this->redis5->get($info['user_id'].'_jian_user') ? $this->redis5->get($info['user_id'].'_jian_user')  : 0;
//
//                    $data['data'][0]  = $info;
//                }
//            }
//        }else{
//            //获取我的直推
//            $info = DsUser::query()->where('pid',$user_id)->select('user_id','nickname','mobile','avatar','pid')
//                ->forPage($page,10)
//                ->get()->toArray();
//            if(!empty($info))
//            {
//                if(count($info) >= 10)
//                {
//                    $data['more']    = '1';
//                }
//                foreach ($info as $k => $v)
//                {
//                    $infos = DsUser::query()->where('user_id',$v['pid'])->select('nickname','avatar')->first();
//                    if(!empty($infos))
//                    {
//                        $infos = $infos->toArray();
//                    }else{
//                        $infos = [
//                            'nickname'  => '',
//                            'avatar'  => '',
//                        ];
//                    }
//                    $info[$k]['pid_nickname'] = $infos['nickname'];
//                    $info[$k]['pid_avatar'] = $infos['avatar'];
//
//                    $info[$k]['mobile']         = $this->replace_mobile_end($v['mobile']);
//                    //团队总人数
//                    $info[$k]['team']       = $this->redis6->sCard($v['user_id'].'_team_user') ? $this->redis6->sCard($v['user_id'].'_team_user')  : 0;
//                    //直推人数
//                    $info[$k]['zhi']  = $this->redis5->get($v['user_id'].'_zhi_user') ? $this->redis5->get($v['user_id'].'_zhi_user') : 0;
//                    //间推人数
//                    $info[$k]['jian']       = $this->redis5->get($v['user_id'].'_jian_user') ? $this->redis5->get($v['user_id'].'_jian_user')  : 0;
//                }
//                $data['data']   = $info;
//            }
//        }
//        return $this->withResponse('ok',$data);
//    }
}