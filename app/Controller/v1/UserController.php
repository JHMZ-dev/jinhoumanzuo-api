<?php declare(strict_types=1);

namespace App\Controller\v1;

use App\Controller\XiaoController;
use App\Model\Chuli;
use App\Model\DsAdminSet;
use App\Model\DsCity;
use App\Model\DsUser;
use App\Model\DsUserTongzheng;
use App\Model\DsUserYingpiao;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Annotation\Middlewares;
use App\Middleware\SignatureMiddleware;
use App\Middleware\UserMiddleware;
use Hyperf\Utils\Context;
use Hyperf\RateLimit\Annotation\RateLimit;

/**
 * 用户接口
 * Class UserController
 * @Middlewares({
 *     @Middleware(SignatureMiddleware::class),
 *     @Middleware(UserMiddleware::class)
 * })
 * @package App\Controller\v1
 * @Controller(prefix="v1/user")
 */
class UserController extends XiaoController
{
    /**
     * 获取信息
     * @RequestMapping(path="get_info",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get_info()
    {
        //判断是否已认证
        $userInfo     = Context::get('userInfo');
        $user_id   = Context::get('user_id');
        $data['user_id'] = $user_id;
        $data['nickname']  = strval($userInfo['nickname']);
        $data['avatar']    = strval($userInfo['avatar']);
        $data['mobile']    = $this->replace_mobile_end($userInfo['mobile']);
        $data['auth']       = $userInfo['auth'];
        $data['auth_msg']     = '';
        $data['user_name'] = '';
        //是否实名
        if($userInfo['auth'] == 2)
        {
            $data['auth_msg']   = $userInfo['auth_error'];
        }
        if($userInfo['auth'] == 1)
        {
            $data['user_name'] = strval($userInfo['auth_name']);
        }
//        $data['yunc']   = $userInfo['yunc'];
        $data['group']   = $userInfo['group'];
//        $data['shufen']   = $userInfo['shufen'];
        $data['city_name'] = '';
//
//        if($userInfo['city_id'] != 0)
//        {
//            $re1 = DsCity::query()->where('city_id',$userInfo['city_id'])->first();
//            if(!empty($re1))
//            {
//                $re1 = $re1->toArray();
//                if($re1['level'] == 3)
//                {
//                    $re2 = DsCity::query()->where('city_id',$re1['pid'])->first()->toArray();
//                    $re3 = DsCity::query()->where('city_id',$re2['pid'])->first()->toArray();
//                    $data['city_name'] = $re3['name'] .'-'.$re2['name'] .'-'. $re1['name'];
//                }
//                if($re1['level'] == 2)
//                {
//                    $re3 = DsCity::query()->where('city_id',$re1['pid'])->first()->toArray();
//                    $data['city_name'] = $re3['name'] .'-'.$re1['name'];
//                }
//            }
//        }

        if($userInfo['vip_end_time']){
            if($userInfo['vip_end_time'] > time()){
                $time_sjc = $userInfo['vip_end_time'] - time();
                $data['end_time'] = '还有' . (int)($time_sjc/86400) .'天到期';
            }else{
                $data['end_time'] = '已过期';
            }
        }else{
            $data['end_time'] = '无';
        }

        $data['user_v_top']   = '直推2个1星用户可升级为2星用户';
        //查询用户升级下一级条件

        //是否设置支付密码
        if($userInfo['pay_password']){
            $data['is_pay_password']   = 1;
        }else{
            $data['is_pay_password']   = 0;
        }
        if($userInfo['usdt_address']){
            $data['usdt_address']   = $userInfo['usdt_address'];
        }else{
            $data['usdt_address']   = '';
        }
        if($userInfo['ali_name']){
            $data['ali_name']   = $userInfo['ali_name'];
        }else{
            $data['ali_name']   = '';
        }
        if($userInfo['ali_account']){
            $data['ali_account']   = $userInfo['ali_account'];
        }else{
            $data['ali_account']   = '';
        }

        $data['auth_name'] = '';
        if($userInfo['auth_name']){
            $data['auth_name'] = Chuli::str_hide($userInfo['auth_name']);
        }

        $data['auth_num'] = '';
        if($userInfo['auth_num']){
            $data['auth_num'] = Chuli::str_hide($userInfo['auth_num'],6,6);
        }


        $data['is_vip']   = 0;
        if($this->_is_vip($user_id))
        {
            $data['is_vip']   = 1;
        }

        $info22 = [
            'type'          => '6',
            'user_id'       => $user_id,
        ];
        $this->yibu($info22);

        $info22 = [
            'type'          => '7',
            'user_id'       => $user_id,
        ];
        $this->yibu($info22);

        return $this->withResponse('获取成功',$data);
    }

    /**
     * 设置城市
     * @RequestMapping(path="set_city2",methods="post")
     */
    public function set_city2()
    {
        $user_id   = Context::get('user_id');

        $latitude = Context::get('latitude');
        $longitude = Context::get('longitude');
        if(!empty($latitude) && !empty($longitude)){
            $this->redis2->set('latitude_'.$user_id,$latitude,2000);
            $this->redis2->set('longitude_'.$user_id,$longitude,2000);
        }
        $cityCode = $this->request->post('cityCode');//中文
        if(!empty($cityCode)){
            if(is_numeric($cityCode)){
                $c = DsCity::query()->where('city_id',$cityCode)->where('level',2)->value('city_id');
                if(!empty($c)){
                    $cityCode = $c;
                }else{
                    return $this->response->json([
                        'code' => 500,
                        'msg' => '请确认城市是否正确',
                    ]);
                    //$cityCode = 110100;
                }
            }else{
                $c = DsCity::query()->where('name','like',$cityCode.'%')->orderByDesc('level')->get()->toArray();
                if($c && is_array($c)){
                    if(!empty($c[0]['level']))
                    {
                        if($c[0]['level'] == '2')
                        {
                            $cityCode = $c[0]['city_id'];
                        }
                    }
                    if(!empty($c[1]['level']))
                    {
                        $cityCode = $c[1]['city_id'];
                    }
                }else{
                    return $this->response->json([
                        'code' => 500,
                        'msg' => '请确认城市是否正确!',
                    ]);
                }
            }
        }else{
            return $this->response->json([
                'code' => 500,
                'msg' => '请确认地址是否正确!!',
            ]);
            //$cityCode = 110100;
        }
        $this->redis2->set('cityCode_'.$user_id,$cityCode,86400*7);
        return $this->response->json([
            'code' => 200,
            'msg' => '操作成功',
        ]);
    }

    /**
     * 设置城市
     * @RequestMapping(path="set_city",methods="post")
     */
    public function set_city()
    {
        $user_id   = Context::get('user_id');
        $userInfo       = Context::get('userInfo');

        if($userInfo['city_id'] != 0)
        {
            return $this->withError('您已设置城市信息');
        }
        $provinceCode    = $this->request->post('provinceCode','');
        $provinceName    = DsCity::query()->where('city_id',$provinceCode)->where('level',1)->value('name');
        if(empty($provinceName))
        {
            //return $this->withError('请选择正确的省份！');
        }
        $cityCode    = $this->request->post('cityCode','');
        $cityName    = DsCity::query()->where('city_id',$cityCode)->where('level',2)->value('name');
        if(empty($cityName))
        {
            return $this->withError('请选择正确的城市！');
        }
        $areaCode    = $this->request->post('areaCode','');
        if(!empty($areaCode))
        {
            $areaName    = DsCity::query()->where('city_id',$areaCode)->where('level',3)->value('name');
            if(empty($areaName))
            {
                //return $this->withError('请选择正确的区域！');
            }
        }else{
            //判断是否有区域
            $areaName    = DsCity::query()->where('pid',$cityCode)->where('level',3)->value('name');
            if(!empty($areaName))
            {
               // return $this->withError('请选择正确的区域！');
            }
            $areaCode = $cityCode;
        }
        $res = DsUser::query()->where('user_id',$user_id)->update(['city_id' => $areaCode]);
        if($res)
        {
            return $this->withSuccess('操作成功');
        }else{
            return $this->withError('请稍后再试');
        }
    }

    /**
     * 设置个人信息
     * @RequestMapping(path="set_info",methods="post")
     */
    public function set_info()
    {
        $userInfo       = Context::get('userInfo');
        $user_id        = Context::get('user_id');
        $type           = $this->request->post('type'); //1修改头像 2修改昵称 3修改微信号 4换绑 5设置支付密码
        $value          = $this->request->post('value',''); //内容
        switch ($type)
        {
            case '1':
                $avatar   = $value;
                if(empty($avatar))
                {
                    return $this->withError('请先上传头像');
                }
                if($avatar == $userInfo['avatar'])
                {
                    return $this->withSuccess('操作成功');
                }
                $update = ['avatar' => $avatar ];
                $userInfo['avatar'] = $avatar;
                break;
            case '2':
                $nickname   = $value;
                if(empty($nickname))
                {
                    return $this->withError('请填写昵称');
                }
                if($nickname == $userInfo['nickname'])
                {
                    return $this->withSuccess('操作成功');
                }
                $update = ['nickname' => $nickname ];
                $userInfo['nickname'] = $nickname;
                break;
            case '3':
                return $this->withError('即将开放');
                break;
            case '4':
                $cishu = $this->redis2->get('user_set_mobile_'.$user_id);
                if($cishu > 10){
                    return $this->withError('一个用户最多更换10次手机，请联系客服');
                }

                $mobile_new   = $value;
                if(empty($mobile_new))
                {
                    return $this->withError('请填写新手机号');
                }

                $code           = $this->request->post('code');
                $code_new           = $this->request->post('code_new');
                if(empty($code) || empty($code_new)){
                    return $this->withError('请填写原手机号/新手机号的验证码');
                }
                //判断验证码是否正确
                $this->_checkCode($userInfo['mobile'], 'edit_mobile', $code, time(),$user_id);
                $this->_checkCode($mobile_new, 'edit_mobile2', $code_new, time(),$user_id);

                if($mobile_new == $userInfo['mobile'])
                {
                    return $this->withSuccess('操作成功');
                }

                //$resuser = DsUser::query()->where('username',$mobile_new)->value('user_name');
                $update = ['mobile' => $mobile_new,'username' => $mobile_new,];
                $this->redis2->incr('user_set_mobile_'.$user_id);
                break;
            case '5':
                $pay_password   = strval($value);
                if(empty($pay_password))
                {
                    return $this->withError('请填写支付密码');
                }
                $code           = $this->request->post('code');
                //判断验证码是否正确
                $this->_checkCode($userInfo['mobile'], 'set_pay_info', $code, time(),$user_id);

                if(mb_strlen($pay_password) != 6){
                    return $this->withError('支付密码必须是6位');
                }
                if(!is_numeric($pay_password)){
                    return $this->withError('支付密码必须是6位数字');
                }
                $pay_password = password_hash($pay_password,PASSWORD_DEFAULT);
                $update = ['pay_password' => $pay_password ];

                break;
            default:
                return $this->withError('类型选择错误');
                break;
        }
        if(!empty($update))
        {
            $res = DsUser::query()->where('user_id',$user_id)->update($update);
            if($res)
            {
                return $this->withSuccess('操作成功');
            }else{
                return $this->withError('内容填写有误,请重新填写');
            }
        }
        return $this->withError('未做任何修改');
    }


    /**
     * 修改登录密码
     * @RequestMapping(path="edit_password",methods="post")
     * @RateLimit(create=1, consume=1, capacity=1, key={UserController::class, "_key"})
     */
    public function edit_password()
    {
        $user_id   = Context::get('user_id');
        $userInfo     = Context::get('userInfo');
        $old_password   = $this->request->post('old_password');
        $new_password   = $this->request->post('new_password');
        $new_password2  = $this->request->post('new_password2');
        if(empty($new_password) || empty($new_password2))
        {
            return $this->withError('新密码内容不能为空！');
        }
        if(strlen($new_password) < 6)
        {
            return $this->withError('新密码密码长度最少6位！');
        }
        if(strlen($new_password) > 20)
        {
            return $this->withError('新密码密码长度最大20位！');
        }
        if($new_password != $new_password2)
        {
            return $this->withError('新密码两次输入的不一致！');
        }
        if(empty($old_password)){
            return $this->withError('旧密码不能为空！');
        }
        //验证旧密码是否正确
        if (!password_verify ($old_password,$userInfo['password']))
        {
            return $this->withError('旧密码不正确！');
        }
        if($old_password == $new_password)
        {
            //清除token
            $this->del_user_token_db($user_id);
            return $this->withSuccess('操作成功！');
        }
        $password1 = password_hash($new_password,PASSWORD_DEFAULT);
        //处理信息
        $res = DsUser::query()->where('user_id',$user_id)->update(['password' =>$password1 ]);
        if($res)
        {
            //清除token
            $this->del_user_token_db($user_id);
            return $this->withSuccess('操作成功!');
        }else{
            return $this->withError('内容填写有误,请重新填写');
        }
    }

    /**
     * 退出登录
     * @RequestMapping(path="logout",methods="post")
     */
    public function logout()
    {
        $user_id   = Context::get('user_id');
        //清除token
        $this->del_user_token_db($user_id);
        return $this->withSuccess('操作成功');
    }

    /**
     * 修改支付密码
     * @RequestMapping(path="edit_pay_password",methods="post")
     * @RateLimit(create=1, consume=1, capacity=1, key={UserController::class, "_key"})
     */
    public function edit_pay_password()
    {
        $user_id   = Context::get('user_id');
        $userInfo     = Context::get('userInfo');
        $new_password   = $this->request->post('new_password');
        $new_password2  = $this->request->post('new_password2');
        if(empty($new_password) || empty($new_password2))
        {
            return $this->withError('支付密码内容不能为空！');
        }
        if(!is_numeric($new_password))
        {
            return $this->withError('请设置支付密码为6位数字！');
        }
        if(strlen($new_password) != 6)
        {
            return $this->withError('支付密码长度只能为6位！');
        }
        if($new_password != $new_password2)
        {
            return $this->withError('两次输入密码的不一致！');
        }
        $code           = $this->request->post('code','');
        //判断验证码是否正确
        $this->_checkCode($userInfo['mobile'], 'edit_pay_password', $code, time(),$user_id);

        $password1 = password_hash($new_password,PASSWORD_DEFAULT);
        //处理信息
        $res = DsUser::query()->where('user_id',$user_id)->update(['pay_password' =>$password1 ]);
        if($res)
        {
            return $this->withSuccess('操作成功!');
        }else{
            return $this->withError('内容填写有误,请重新填写');
        }
    }

    /**
     * 注销用户
     * @RequestMapping(path="user_out_over",methods="post")
     */
    public function user_out_over()
    {
        $user_id   = Context::get('user_id');

        $name = '122'.rand(10000000,99999999);
        DsUser::query()->where('user_id',$user_id)->update([
            'mobile' => $name,
            'username' => $name,
        ]);

        //清除token
        $this->del_user_token_db($user_id);
        return $this->withSuccess('操作成功');
    }

    /**
     *获取通证明细
     * @RequestMapping(path="get_user_tongzheng_logs",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get_user_tongzheng_logs(){
        $user_id  = Context::get('user_id');
        $userInfo     = Context::get('userInfo');
        $page     = $this->request->post('page',1);
        if($page <= 0)
        {
            $page = 1;
        }
        $data['content']           = '介绍介绍介绍介绍介绍介绍介绍介绍介绍介绍介绍介绍介绍介绍介绍介绍介绍介绍介绍介绍介绍';
        $data['more']           = '0';
        $data['num']         = strval($userInfo['tongzheng']);

        $data['data']         = [];
        $res = DsUserTongzheng::query()->where('user_id',$user_id)
            ->orderByDesc('user_tongzheng_id')
            ->forPage($page,10)->get()->toArray();
        if(!empty($res))
        {
            //查找是否还有下页数据
            if(count($res) == 10)
            {
                $data['more'] = '1';
            }
            //整理数据
            $datas = [];
            foreach ($res as $k => $v)
            {
                $datas[$k]['user_tongzheng_type'] = $v['user_tongzheng_type'];
                $datas[$k]['name'] = $v['user_tongzheng_cont'];
                $datas[$k]['num']  = strval($v['user_tongzheng_change']);
                $datas[$k]['time']  = $this->replaceTime($v['user_tongzheng_time']);
            }
            $data['data']           = $datas;
        }

        return $this->withResponse('获取成功',$data);
    }

    /**
     * 获取影票明细
     * @RequestMapping(path="get_user_yingpiao_logs",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get_user_yingpiao_logs(){
        $user_id  = Context::get('user_id');
        $userInfo     = Context::get('userInfo');
        $page     = $this->request->post('page',1);
        if($page <= 0)
        {
            $page = 1;
        }
        $data['content']           = '介绍介绍介绍介绍介绍介绍介绍介绍介绍介绍介绍介绍介绍介绍介绍介绍介绍介绍介绍介绍介绍';
        $data['more']           = '0';
        $data['num']         = strval($userInfo['yingpiao']);

        $data['data']         = [];
        $res = DsUserYingpiao::query()->where('user_id',$user_id)
            ->orderByDesc('user_yingpiao_id')
            ->forPage($page,10)->get()->toArray();
        if(!empty($res))
        {
            //查找是否还有下页数据
            if(count($res) == 10)
            {
                $data['more'] = '1';
            }
            //整理数据
            $datas = [];
            foreach ($res as $k => $v)
            {
                $datas[$k]['name'] = $v['user_yingpiao_cont'];
                $datas[$k]['type'] = $v['user_yingpiao_type'];
                $datas[$k]['num']  = strval($v['user_yingpiao_change']);
                $datas[$k]['time']  = $this->replaceTime($v['user_yingpiao_time']);
            }
            $data['data']           = $datas;
        }

        return $this->withResponse('获取成功',$data);
    }


    /**
     * 领袖榜
     * @RequestMapping(path="get_lingxiubang",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get_lingxiubang(){
        $user_id  = Context::get('user_id');
        $userInfo     = Context::get('userInfo');
        $type     = $this->request->post('type',1);//1影票 2扑满
        $day = date('Y-m-d',strtotime("-1 day"));
        $all_shouxu = $this->redis0->get($day.'_yp_to_tz_shouxu')?round($this->redis0->get($day.'_yp_to_tz_shouxu')*0.05,4):0;

        if($type == 1)
        {
            $paihangbang_yingpiao = $this->redis0->get('paihangbang_yingpiao');
            if($paihangbang_yingpiao)
            {
                $paihangbang_yingpiao = json_decode($paihangbang_yingpiao,true);
            }else{
                $paihangbang_yingpiao = [];
            }
            //影票
            $data = [
                'top_fenhong_all' => $all_shouxu,
                'fenhong_content' => DsAdminSet::query()->where('set_cname','phb_yingpiao')->value('set_cvalue'),
                'user_list' => $paihangbang_yingpiao
            ];
        }else{
            //扑满
            $data = [
                'top_fenhong_all' => 0,
                'fenhong_content' => DsAdminSet::query()->where('set_cname','phb_puman')->value('set_cvalue'),
                'user_list' => [
                    [
                        'img' => 'https://jinhoumanzuo.oss-cn-hangzhou.aliyuncs.com/logo.png',//头像
                        'nickname' => '昵称',//昵称
                        'tuandui_number' => 8888,//团队实名
                    ],
                    [
                        'img' => 'https://jinhoumanzuo.oss-cn-hangzhou.aliyuncs.com/logo.png',//头像
                        'nickname' => '昵称',//昵称
                        'tuandui_number' => 8888,//团队实名
                    ],
                    [
                        'img' => 'https://jinhoumanzuo.oss-cn-hangzhou.aliyuncs.com/logo.png',//头像
                        'nickname' => '昵称',//昵称
                        'tuandui_number' => 8888,//团队实名
                    ],
                    [
                        'img' => 'https://jinhoumanzuo.oss-cn-hangzhou.aliyuncs.com/logo.png',//头像
                        'nickname' => '昵称',//昵称
                        'tuandui_number' => 8888,//团队实名
                    ],
                    [
                        'img' => 'https://jinhoumanzuo.oss-cn-hangzhou.aliyuncs.com/logo.png',//头像
                        'nickname' => '昵称',//昵称
                        'tuandui_number' => 8888,//团队实名
                    ],
                    [
                        'img' => 'https://jinhoumanzuo.oss-cn-hangzhou.aliyuncs.com/logo.png',//头像
                        'nickname' => '昵称',//昵称
                        'tuandui_number' => 8888,//团队实名
                    ],
                    [
                        'img' => 'https://jinhoumanzuo.oss-cn-hangzhou.aliyuncs.com/logo.png',//头像
                        'nickname' => '昵称',//昵称
                        'tuandui_number' => 8888,//团队实名
                    ],
                    [
                        'img' => 'https://jinhoumanzuo.oss-cn-hangzhou.aliyuncs.com/logo.png',//头像
                        'nickname' => '昵称',//昵称
                        'tuandui_number' => 8888,//团队实名
                    ],
                    [
                        'img' => 'https://jinhoumanzuo.oss-cn-hangzhou.aliyuncs.com/logo.png',//头像
                        'nickname' => '昵称',//昵称
                        'tuandui_number' => 8888,//团队实名
                    ],
                    [
                        'img' => 'https://jinhoumanzuo.oss-cn-hangzhou.aliyuncs.com/logo.png',//头像
                        'nickname' => '昵称',//昵称
                        'tuandui_number' => 8888,//团队实名
                    ],
                ]
            ];
        }

        return $this->withResponse('ok',$data);
    }
    /**
     * 返回用户id
     * @return string
     */
    public static function _key(): string
    {
        $user_id    = Context::get('user_id');
        return strval($user_id);
    }
}