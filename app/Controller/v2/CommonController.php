<?php declare(strict_types=1);

namespace App\Controller\v2;

use App\Controller\async\YDYanZhen;
use App\Controller\ChatIm;
use App\Controller\XiaoController;
use App\Job\AliCms;
use App\Job\Chatimhuanxin;
use App\Job\Register;
use App\Model\DsBang;
use App\Model\DsCinemaUserOrder;
use App\Model\DsCity;
use App\Model\DsCode;
use App\Model\DsCzHuafeiUserOrder;
use App\Model\DsOffline;
use App\Model\DsPmSh;
use App\Model\DsTzH;
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

$add = BASE_PATH.'/extend/afs';
require_once $add . '/aliyun-php-sdk-core/Config.php';
use afs\Request\V20180112 as Afs;

/**
 * 公共接口 无需登录
 * Class CommonController
 * @Middleware(SignatureMiddleware::class)
 * @package App\Controller\v2
 * @Controller(prefix="v2/common")
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

//        //先验证token
        $yd_validate = $this->request->post('yd_validate');
        $captcha_id = $this->request->post('captcha_id',0);
        if(empty($yd_validate)){
            return $this->withError('验证不通过！');
        }
        if(empty($captcha_id)){
            return $this->withError('设备类型不存在！！');
        }
        if(!in_array($captcha_id,[2,3])){
            return $this->withError('设备类型不存在！');
        }
        $yd = make(YDYanZhen::class);
        $yd->code_verify($yd_validate,$mobile,$captcha_id);

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
            case 'redwrap_out'://发红包
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
            case 'offline_add'://商家入住
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

                $offline_status = DsOffline::query()->where('user_id',$userInfo['user_id'])->orderByDesc('offline_id')->first();
                if(!empty($offline_status)){
                    $offline_status = $offline_status->toArray();
                    if($offline_status['offline_status'] == '1'){
                        return $this->withError('你已通过，请不要重复提交');
                    }
                    if($offline_status['offline_status'] == '2'){
                        return $this->withError('正在审核中，请耐心等待');
                    }
                }
                break;
            case 'offline_xufei'://商家续费
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

                //查询是否存在店铺
                $offline_status = DsOffline::query()->where('user_id',$userInfo['user_id'])->orderByDesc('offline_id')->first();
                if(!empty($offline_status)){
                    if($offline_status['offline_status'] == '2'){
                        return $this->withError('正在审核，请稍后');
                    }
                    if($offline_status['offline_status'] != 1){
                        return $this->withError('你当前不是正常店铺，不能续费');
                    }
                }else{
                    return $this->withError('你还没入驻，请前去入驻');
                }
                break;
            case 'bang_add'://帮帮加入
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

                //查询是否存在店铺
                $offline_status = DsBang::query()->where('user_id',$userInfo['user_id'])->orderByDesc('bang_id')->first();
                if(!empty($offline_status)){
                    $offline_status = $offline_status->toArray();
                    if($offline_status['status'] == '1'){
                        if(time() > $offline_status['offline_end_time']){
                            return $this->withError('你已过期，请前去续费');
                        }
                        return $this->withError('你已通过，请不要重复提交');
                    }
                    if($offline_status['status'] == '2'){
                        return $this->withError('正在审核中，请耐心等待');
                    }
                }
                break;
            case 'bang_xufei'://帮帮续费
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

                //查询是否存在店铺
                $offline_status = DsBang::query()->where('user_id',$userInfo['user_id'])->orderByDesc('bang_id')->first();
                if(!empty($offline_status)){
                    $offline_status = $offline_status->toArray();
                    if($offline_status['status'] == '2'){
                        return $this->withError('正在审核，请稍后');
                    }
                    if($offline_status['status'] != 1){
                        return $this->withError('你当前不是正常店铺，不能续费');
                    }
                }else{
                    return $this->withError('你还没加入帮帮，请前去加入');
                }
                break;
            case 'vip_pay'://开通VIP
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

                if($this->_is_vip($userInfo['user_id']))
                {
                    return $this->withError('您已是会员,请前往续费');
                }
                break;
            case 'vip_xufei'://续费VIP
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

                if(!$this->_is_vip($userInfo['user_id']))
                {
                    return $this->withError('您还不是会员,请前往开通');
                }
                break;
            case 'puman_sh_do'://扑满赎回
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

                //判断上次是否再处理
                $sf = DsPmSh::query()->where('user_id',$userInfo['user_id'])->orderByDesc('sh_id')
                    ->select('type','sh_id')->first();
                if(!empty($sf))
                {
                    $sf = $sf->toArray();
                    if($sf['type'] == 0)
                    {
                        return $this->withError('请耐心等待上次赎回处理！');
                    }
                }
                break;
            case 'yp_to_tz'://影票兑通证
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
            case 'tz_to_yp'://通证兑影票
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
            case 'tz_hs_do'://通证数据-回收
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

                $tz_hs_do_status = $this->redis0->get('tz_hs_do_status');
                if($tz_hs_do_status != 1)
                {
                    return $this->withError('通证回收工作已全面结束，满座通证定价权成功交接自由市场。');
                }
                //判断上次是否再处理
                $sf = DsTzH::query()->where('user_id',$userInfo['user_id'])->orderByDesc('hs_id')
                    ->select('type','hs_id')->first();
                if(!empty($sf))
                {
                    $sf = $sf->toArray();
                    if($sf['type'] == 0)
                    {
                        return $this->withError('请耐心等待上次回收处理！');
                    }
                }
                break;
            case 'rent'://兑换流量
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

                //判断是否能兑换
                $rent_status = $this->redis0->get('rent_status');
                if($rent_status != 1)
                {
                    return $this->withError('该功能维护,请耐心等待通知！');
                }
                break;
            case 'dy_order'://电影票支付
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

                $cinema_order_data = DsCinemaUserOrder::query()->where('user_id',$userInfo['user_id'])->first();
                if(empty($cinema_order_data)){
                    return $this->withError('没有订单信息');
                }
                $cinema_order_data = $cinema_order_data->toArray();
                if(empty($cinema_order_data)){
                    return $this->withError('没有订单信息!');
                }
                if($cinema_order_data['order_status'] > 0){
                    return $this->withError('该订单已经支付，请前往查看详情');
                }
                if($cinema_order_data['status'] > 0){
                    return $this->withError('不是待支付状态');
                }
                break;
            case 'hf_order'://话费支付
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

                //检查当前id是否在充值中
                $re_order_start = DsCzHuafeiUserOrder::query()->where('user_id',$userInfo['user_id'])->where('status',2)->orderByDesc('cz_huafei_user_order_id')->first();
                if(!empty($re_order_start)){
                    return $this->withError('你有正在充值订单，请等待处理完成再下单！');
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
     * 短信注册
     * @RequestMapping(path="register",methods="post")
     * @Middleware(AsyncMiddleware::class)
     * @return \Psr\Http\Message\ResponseInterface
     * @throws Exception
     */
    public function register()
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
        //先验证token
        $wy_token   = $this->request->post('wy_token','');
        $wy_token_type   = $this->request->post('wy_token_type',1);
        $yd = make(YDYanZhen::class);
        $data = [
            'user_id'   => $mobile,
            'mobile'    => $mobile,
            'ip'        => Context::get('ip')?Context::get('ip'):'127.0.0.1',
            'business_id'   => $wy_token_type == 1?2:3,
        ];
        $deviceId = $yd->huanjing_verify($wy_token,$data);
        if(empty($deviceId))
        {
            return $this->withError('获取设备信息失败，请联系客服！');
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

        // 查找用户信息
        $user_id = DsUser::query()->where('mobile',$mobile)->value('user_id');
        if(!empty($user_id))
        {
            return $this->withError('此手机号已注册,请前去登录');
        }

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
            'deviceid'      => $deviceId,
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

        //先验证token
        $wy_token   = $this->request->post('wy_token','');
        $wy_token_type   = $this->request->post('wy_token_type',1);
        $yd = make(YDYanZhen::class);
        $data = [
            'user_id'   => $userInfo['user_id'],
            'mobile'    => $userInfo['mobile'],
            'ip'        => Context::get('ip')?Context::get('ip'):'127.0.0.1',
            'business_id'   => $wy_token_type == 1?2:3,
            'reg_ip'   => $userInfo['ip']?$userInfo['ip']:'',
        ];
        $deviceId = $yd->huanjing_verify($wy_token,$data);
        if(empty($deviceId))
        {
            return $this->withError('获取设备信息失败，请联系客服！');
        }
        //判断是否是第一次登录
        if($deviceId != $userInfo['deviceid'])
        {
            return $this->withError('满座放大镜监测到您在当前设备是首次登录，为了您账户的安全，请先用验证码进行登陆!');
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

        //先验证token
        $wy_token   = $this->request->post('wy_token','');
        $wy_token_type   = $this->request->post('wy_token_type',1);
        $yd = make(YDYanZhen::class);
        $data = [
            'user_id'   => $userInfo['user_id'],
            'mobile'    => $userInfo['mobile'],
            'ip'        => Context::get('ip')?Context::get('ip'):'127.0.0.1',
            'business_id'   => $wy_token_type == 1?2:3,
            'reg_ip'   => $userInfo['ip']?$userInfo['ip']:'',
        ];
        $deviceId = $yd->huanjing_verify($wy_token,$data);
        if(empty($deviceId))
        {
            return $this->withError('获取设备信息失败，请联系客服！');
        }
        if($deviceId != $userInfo['deviceid'])
        {
            $gai = DsUser::query()->where('user_id',$userInfo['user_id'])->update(['deviceid' => $deviceId]);
            if(!$gai)
            {
                return $this->withError('获取设备信息失败，请联系客服！');
            }
        }
        //清除之前的token
        $this->del_user_token_db($userInfo['user_id']);
        //获取token
        $token = $this->token_db($userInfo['user_id']);

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

    protected function add_huanxin_token($user_id)
    {
        $info = [
            'user_id'        => $user_id,
        ];
        $job = ApplicationContext::getContainer()->get(DriverFactory::class);
        $job->get('async')->push(new Chatimhuanxin($info));
    }
}