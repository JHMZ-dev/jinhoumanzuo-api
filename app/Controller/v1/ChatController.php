<?php

declare(strict_types=1);

namespace App\Controller\v1;

use App\Controller\ChatIm;
use App\Controller\XiaoController;
use App\Job\Chatimhuanxin;
use App\Model\DsChatRoomRank;
use App\Model\DsErrorLog;
use App\Model\DsRedwrapDatum;
use App\Model\DsRedwrapGet;
use App\Model\DsRedwrapOut;
use App\Model\DsUser;
use App\Model\DsUserDatum;
use App\Model\DsUserHuoyuedu;
use App\Model\DsUserPuman;
use App\Model\DsUserTaskPack;
use App\Model\DsUserTongzheng;
use Hyperf\AsyncQueue\Driver\DriverFactory;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\HttpServer\Annotation\AutoController;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Context;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Annotation\Middlewares;
use App\Middleware\SignatureMiddleware;
use App\Middleware\UserMiddleware;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\RateLimit\Annotation\RateLimit;

use Easemob\Auth;
use Easemob\User;
use Hyperf\Utils\Parallel;
use Swoole\Exception;


/**
 * 红包接口
 * Class UserController
 * @Middlewares({
 *     @Middleware(SignatureMiddleware::class),
 *     @Middleware(UserMiddleware::class)
 * })
 * @package App\Controller\v1
 * @Controller(prefix="v1/chat")
 */
class ChatController  extends XiaoController
{

    /**
     * 发红包-初始化
     * @RequestMapping(path="redwrap_csh",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function redwrap_csh(){
        $user_id   = Context::get('user_id',0);
        $userInfo   = Context::get('userInfo','');

        $data = [];
        $redwrap_type = DsRedwrapDatum::query()->get()->toArray();
        if(!empty($redwrap_type)){
            $data['redwrap_type'] = $redwrap_type;
        }else{
            $data['redwrap_type'] = [];
        }

        return $this->withResponse('获取成功',$data);

    }

    /**
     * 发红包 传统
     * @RequestMapping(path="redwrap_out",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function redwrap_out(){
        return $this->withError('更新');
        $user_id   = Context::get('user_id');
        $userInfo   = Context::get('userInfo','');

        $redwrap_switch = $this->redis0->get('redwrap_switch');
        if($redwrap_switch != 1){
            return $this->withError('暂未开放发红包功能');
        }

        $scene = $this->request->post('scene',1);//1单聊发红包 2群聊发红包
        $money = $this->request->post('money',0);//红包金额
        if(empty($scene))
        {
            return $this->withError('请选择发红包的类型单/群');
        }
        if(empty($money))
        {
            return $this->withError('请输入红包的金额');
        }
        if(!is_numeric($money))
        {
            return $this->withError('红包的金额只能是数字');
        }

        if($money > 400){
            return $this->withError('红包的数量最大只能是400');
        }

        $this->check_often($user_id);
        $this->start_often($user_id);

        //校验发红包是否频繁
        $user_id_number = $this->redis2->get('redwrap_user_out_number_'.$user_id);
        if(!empty($user_id_number)){
            if($user_id_number > 300){
                return $this->withError('当天发红包次数频繁，请联系客服！');
            }
            $this->redis2->incr('redwrap_user_out_number_'.$user_id);
        }else{
            $this->redis2->set('redwrap_user_out_number_'.$user_id,1,$this->get_end_t_time());
        }

        //检查支付密码对不对
//        $pay_password = $this->request->post('pay_password','');
//        $this->_check_pay_password($pay_password);

        $redwrap_res = [];
        $class = $this->request->post('redwrap_data_type',0);//1通证红包 2扑满红包
        if(empty($class)){
            return $this->withError('请选择红包类型');
        }
        $class_data = DsRedwrapDatum::query()->where('redwrap_data_type',$class)->orderByDesc('redwrap_data_id')->first();
        if(empty($class_data)){
            return $this->withError('没有该红包类型');
        }else{
            $class_data = $class_data->toArray();
        }
        if(empty($class_data['redwrap_data_img'])){
            return $this->withError('红包正在配置');
        }

        switch ($scene)
        {
            case '1'://单聊红包
                //校验领取人
                $redwrap_user_id = $this->request->post('redwrap_user_id',0);
                if(empty($redwrap_user_id)){
                    return $this->withError('领取人不能空');
                }
                $is_user = DsUser::query()->where('user_id',$redwrap_user_id)->value('user_id');
                if(empty($is_user)){
                    return $this->withError('领取人不存在');
                }
                if($redwrap_user_id == $user_id){
                    return $this->withError('自己不能给自己发');
                }

                //先扣除对应的余额
                switch ($class){
                    case '1':
                        if($money < 0.0001)
                        {
                            return $this->withError('红包的金额不正确');
                        }
                        if(!$this->checkPrice_4($money))
                        {
                            return $this->withError('最多只能填四位小数！');
                        }
                        $kou_yue = DsUserTongzheng::del_tongzheng($user_id,$money,'发红包给：'.$redwrap_user_id);
                        if(empty($kou_yue)){
                            return $this->withError('当前发红包的人数较多，请稍后再试');
                        }
                        break;
                    case '2':
                        if($money < 0.01)
                        {
                            return $this->withError('红包的金额不能小于0.01');
                        }
                        if(strlen(substr($money, strrpos($money, '.')+1)) > 2)
                        {
                            return $this->withError('红包的金额最多2位小数');
                        }
                        $kou_yue = DsUserPuman::del_puman($user_id,$money,'发红包给：'.$redwrap_user_id);
                        if(empty($kou_yue)){
                            return $this->withError('当前发红包的人数较多，请稍后再试');
                        }
                        break;
                    default:
                        return $this->withError('没开通该类型');
                        break;
                }
                $time = time();
                $data = [
                  'user_id' => $user_id,
                  'money' => $money,
                  'type' => 0,//红包类型 0普通红包 1专属红包 2拼手气红包
                  'class' => $class,//红包类型 id 1通证红包 2扑满红包
                  'scene' => $scene,//红包场景 1单聊 2群聊
                  'status' => 0,//状态： 0未领取，1领取中 ，2已领完
                  'num' => 1,//红包个数
                  'style' => $class_data['redwrap_data_img'],
                  'time' => $time,
                  'redwrap_user_id' => $redwrap_user_id,
                  'time_end' => $time + 86400,
                ];
                $res_bao_insert = DsRedwrapOut::query()->insertGetId($data);
                if(empty($res_bao_insert)){
                    //退回对应的余额
                    switch ($class){
                        case '1':
                            $tui_yue = DsUserTongzheng::add_tongzheng($user_id,$money,'发红包退回：'.$redwrap_user_id);
                            if(empty($tui_yue)){
                                DsErrorLog::add_log('红包-通证-退回失败',json_encode($data),$user_id);
                            }
                            break;
                        case '2':
                            $tui_yue = DsUserPuman::add_puman($user_id,$money,'发红包退回：'.$redwrap_user_id);
                            if(empty($tui_yue)){
                                DsErrorLog::add_log('红包-扑满-退回失败',json_encode($data),$user_id);
                            }
                            break;
                        default:
                            DsErrorLog::add_log('红包-没有该退回类型',json_encode($data),$user_id);
                            break;
                    }
                    return $this->withError('当前发红包人数较多，请稍后再试！');
                }
                $redwrap_res['redwrap_out_id'] = $res_bao_insert;
                break;
            case '2'://群聊红包
                return $this->withError('群聊红包开发测试中,请耐心等待');
                break;
            default:
                return $this->withError('没有该类型');
                break;
        }

        return $this->withResponse('下发成功',$redwrap_res);
    }

    /**
     * 领红包 传统
     * @RequestMapping(path="redwrap_get",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function redwrap_get(){
        return $this->withError('更新');
        $user_id   = Context::get('user_id');
        $userInfo   = Context::get('userInfo','');

        $redwrap_switch = $this->redis0->get('redwrap_switch');
        if($redwrap_switch != 1){
            return $this->withError('暂未开放红包功能');
        }

        //校验领红包是否频繁
        $user_id_number = $this->redis2->get('redwrap_user_get_number_'.$user_id);
        if(!empty($user_id_number)){
            if($user_id_number > 800){
                return $this->withError('当天领取红包次数频繁，请联系客服！');
            }
            $this->redis2->incr('redwrap_user_get_number_'.$user_id);
        }else{
            $this->redis2->set('redwrap_user_get_number_'.$user_id,1,$this->get_end_t_time());
        }

        $this->check_often($user_id);
        $this->start_often($user_id);

        $redwrap_out_id = $this->request->post('redwrap_out_id',0);//发红包ID
        if(empty($redwrap_out_id)){
            return $this->withError('没有发出红包');
        }
        $redwrap_out_array = DsRedwrapOut::query()->where('redwrap_out_id',$redwrap_out_id)->first();
        if(empty($redwrap_out_array)){
            return $this->withError('没有发出该红包');
        }
        $redwrap_out_array = $redwrap_out_array->toArray();
        if($redwrap_out_array['status'] == '2'){
            return $this->withError('你的速度太慢了，已经被领了');
        }
        if($redwrap_out_array['status'] == '3'){
            return $this->withError('已经过期了，无法领取');
        }
        if($redwrap_out_array['time_end'] < time()){
            return $this->withError('已经过期了，无法领取');
        }
        if(empty($redwrap_out_array['money']) || $redwrap_out_array['money'] <= 0)
        {
            return $this->withError('没有可领取的红包金额');
        }

        //校验是否领取过该红包
        $res_lingqu = DsRedwrapGet::query()->where('user_id',$user_id)->where('redwrap_out_id',$redwrap_out_id)->value('redwrap_get_id');
        if(!empty($res_lingqu)){
            return $this->withError('已经领取过了');
        }

        $redwrap_res = [
            'redwrap_out_id' => null,
            'redwrap_get_id' => null,
        ];

        switch ($redwrap_out_array['scene'])
        {
            case '1'://单聊领取
                //是否校验是否朋友 暂无接口

                //将发出红包先置为已领取
                $redwrap_out_over = DsRedwrapOut::query()->where('redwrap_out_id',$redwrap_out_id)->update([
                   'status' => 2,
                   'time_over' => time(),
                ]);
                if(empty($redwrap_out_over)){
                    return $this->withError('当前领取人数角较多，请稍后再试');
                }
                $data = [
                    'user_id' =>   $user_id,
                    'redwrap_out_id' =>   $redwrap_out_id,
                    'user_id_out' =>   $redwrap_out_array['user_id'],
                    'money' =>   $redwrap_out_array['money'],
                    'class' =>   $redwrap_out_array['class'],
                    'style' =>   $redwrap_out_array['style'],
                    'time' =>   time(),
                ];
                $res_lingqu_ok = DsRedwrapGet::query()->insertGetId($data);
                if(empty($res_lingqu_ok)){
                    //还原红包发出状态
                    $redwrap_out_over = DsRedwrapOut::query()->where('redwrap_out_id',$redwrap_out_id)->update([
                        'status' => 0,
                        'time_over' => 0,
                    ]);
                    if(empty($redwrap_out_over)){
                        DsErrorLog::add_log('红包-领取-还原状态失败',json_encode($redwrap_out_array),'红包-领取-还原状态失败');
                    }
                    return $this->withError('当前领取人数较多，请稍后再试');
                }
                //开始获得红包
                switch ($redwrap_out_array['class']){
                    case '1':
                        $get_yue = DsUserTongzheng::add_tongzheng($user_id,$redwrap_out_array['money'],'获得红包【'.$redwrap_out_array['redwrap_out_id'].'】');
                        if(empty($get_yue)){
                            DsErrorLog::add_log('红包-通证-领取失败',json_encode($data),$user_id);
                            return $this->withError('当前领取人数较多，请稍后再试！');
                        }
                        break;
                    case '2':
                        $get_yue = DsUserPuman::add_puman($user_id,$redwrap_out_array['money'],'获得红包【'.$redwrap_out_array['redwrap_out_id'].'】');
                        if(empty($get_yue)){
                            DsErrorLog::add_log('红包-扑满-领取失败',json_encode($data),$user_id);
                            return $this->withError('当前领取人数较多，请稍后再试！！');
                        }
                        break;
                    default:
                        return $this->withError('没有该类型！');
                        break;
                }
                $redwrap_res['redwrap_out_id'] =  $redwrap_out_array['redwrap_out_id'];//查看详情
                $redwrap_res['redwrap_get_id'] =  $res_lingqu_ok;//领取id
                break;
            case '2':
                return $this->withError('群聊红包开发测试中,请耐心等待');
                break;
            default:
                return $this->withError('没有该类型');
                break;
        }
        return $this->withResponse('领取成功',$redwrap_res);
    }


    /**
     * 获取红包详情
     * @RequestMapping(path="get_redwrap_data",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get_redwrap_data(){
        $user_id  = Context::get('user_id');
        $userInfo     = Context::get('userInfo');
        $redwrap_out_id     = $this->request->post('redwrap_out_id',0);

        if(empty($redwrap_out_id)){
            return $this->withError('请选择红包');
        }
        $res = DsRedwrapOut::query()->where('redwrap_out_id',$redwrap_out_id)->first();
        if(empty($res)){
            return $this->withError('请选择红包');
        }
        $res = $res->toArray();
        if(empty($res)){
            return $this->withError('请选择红包!');
        }

        $bao_data = DsRedwrapDatum::query()->where('redwrap_data_type',$res['class'])->first();
        if(empty($bao_data)){
            return $this->withError('红包正在配置中');
        }
        $bao_data = $bao_data->toArray();
        if(empty($bao_data['redwrap_data_name'])){
            return $this->withError('红包正在配置中！');
        }

        $data['money'] = $res['money'];
        $data['class'] = $res['class'];
        $data['class_name'] = $bao_data['redwrap_data_name'];
        $data['time'] = $this->replaceTime($res['time']);
        $data['status'] = $res['status'];
        $data['status2'] = $res['status2'];

        $data['user_data']['avatar'] = '';
        $data['user_data']['nickname'] = '';
        if(!empty($res['user_id'])){
            $userInfo1 = DsUser::query()->where('user_id',$res['user_id'])->select('avatar','nickname')->first();
            if(!empty($userInfo1)){
                $userInfo1 = $userInfo1->toArray();
                if(!empty($userInfo1)){
                    $data['user_data']['avatar'] = $userInfo1['avatar'];
                    $data['user_data']['nickname'] = $userInfo1['nickname'];
                }
            }
        }

        $data['user_data2']['avatar'] = '';
        $data['user_data2']['nickname'] = '';
        if(!empty($res['redwrap_user_id'])){
            $userInfo2 = DsUser::query()->where('user_id',$res['redwrap_user_id'])->select('avatar','nickname')->first();
            if(!empty($userInfo2)){
                $userInfo2 = $userInfo2->toArray();
                $data['user_data2']['avatar'] = $userInfo2['avatar'];
                $data['user_data2']['nickname'] = $userInfo2['nickname'];
            }
        }

        if(!empty($res['time_over'])){
            $data['time_over'] = $this->replaceTime($res['time_over']);
        }else{
            $data['time_over'] = '';
        }

        return $this->withResponse('获取成功',$data);
    }

    /**
     * 获取红包领取列表
     * @RequestMapping(path="get_redwrap_list",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get_redwrap_list(){
        $user_id  = Context::get('user_id');
        $userInfo     = Context::get('userInfo');
        $redwrap_out_id     = $this->request->post('redwrap_out_id',0);
        $page     = $this->request->post('page',1);

        if(empty($redwrap_out_id)){
            return $this->withError('请选择红包！');
        }

        if($page <= 0)
        {
            $page = 1;
        }

        $data['more']           = '0';
        $data['data']         = [];
        $res = DsRedwrapGet::query()->where('redwrap_out_id',$redwrap_out_id)
            ->orderBy('redwrap_get_id')
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
                $datas[$k]['nickname'] = '无';
                $datas[$k]['avatar'] = '';
                $user = DsUser::query()->where('user_id',$v['user_id'])->select('nickname','avatar')->first();
                if(!empty($user)){
                    $user = $user->toArray();
                    if(!empty($user)){
                        $datas[$k]['nickname'] = $user['nickname'];
                        $datas[$k]['avatar'] = $user['avatar'];
                    }
                }
                $datas[$k]['time']  = $this->replaceTime($v['time']);
                $datas[$k]['money'] = $v['money'];
            }
            $data['data']           = $datas;
        }

        return $this->withResponse('获取成功',$data);
    }




    /**
     * 发红包 定制
     * @RequestMapping(path="redwrap_out2",methods="post")
     * @RateLimit(create=1, consume=1, capacity=1, key={ChatController::class, "_key"})
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function redwrap_out2(){
        //检测版本
        $this->_check_version('1.1.3');

        $user_id   = Context::get('user_id');
        $userInfo   = Context::get('userInfo','');

        $redwrap_switch = $this->redis0->get('redwrap_switch');
        if($redwrap_switch != 1){
            return $this->withError('暂未开放发红包功能');
        }

        $scene = $this->request->post('scene',1);//1单聊发红包 2群聊发红包
        $money = $this->request->post('money',0);//红包金额
        if(empty($scene))
        {
            return $this->withError('请选择发红包的类型单/群');
        }
        if(empty($money))
        {
            return $this->withError('请输入红包的金额');
        }
        if(!is_numeric($money))
        {
            return $this->withError('红包的金额只能是数字');
        }

        $this->check_often($user_id);
        $this->start_often($user_id);

        $this->_check_auth();

        //判断是否是会员和活跃值150
        $this->check_vip_and_huoyue150($user_id);

        //校验发红包是否频繁
        $user_id_number = $this->redis2->get('redwrap_user_out_number_'.$user_id);
        if(!empty($user_id_number)){
            if($user_id_number > 300){
                return $this->withError('当天发红包次数频繁超300次，请联系客服！');
            }
            $this->redis2->incr('redwrap_user_out_number_'.$user_id);
        }else{
            $this->redis2->set('redwrap_user_out_number_'.$user_id,1,$this->get_end_t_time());
        }

        //检查支付密码对不对
        $pay_password = $this->request->post('pay_password','');
        $this->_check_pay_password($pay_password);

        $redwrap_res = [];
        $class = $this->request->post('redwrap_data_type',0);//1通证红包 2扑满红包
        if(empty($class)){
            return $this->withError('请选择红包类型');
        }
        $class_data = DsRedwrapDatum::query()->where('redwrap_data_type',$class)->orderByDesc('redwrap_data_id')->first();
        if(empty($class_data)){
            return $this->withError('没有该红包类型');
        }else{
            $class_data = $class_data->toArray();
        }
        if(empty($class_data['redwrap_data_img'])){
            return $this->withError('红包正在配置');
        }

        //校验是否能发出红包 上一次
//        $user_out_top_data = DsRedwrapOut::query()->where('user_id',$user_id)->orderByDesc('redwrap_out_id')->select('status')->first();
//        if(!empty($user_out_top_data)){
//            $user_out_top_data = $user_out_top_data->toArray();
//            if(in_array($user_out_top_data['status'],[0,1,4])){
//                return $this->withError('上一个红包正在处理中，请完成后或联系客服再操作');
//            }
//        }


        switch ($scene)
        {
            case '1'://单聊红包
                //校验领取人
                $redwrap_user_id = $this->request->post('redwrap_user_id',0);
                if(empty($redwrap_user_id)){
                    return $this->withError('领取人不能空');
                }
                $is_user = DsUser::query()->where('user_id',$redwrap_user_id)->value('user_id');
                if(empty($is_user)){
                    return $this->withError('领取人不存在');
                }
                if($redwrap_user_id == $user_id){
                    return $this->withError('自己不能给自己发');
                }

                //$comon_nickname = DsUser::query()->where('user_id',$redwrap_user_id)->value('nickname');
            //if(empty($comon_nickname)){
                    //$comon_nickname = '';
                //}

                //先扣除对应的余额
                switch ($class){
                    case '1':
                        if($userInfo['tongzheng'] < $money){
                            return $this->withError('你的余额不足');
                        }
                        if(strlen(substr($money, strrpos($money, '.')+1)) > 4)
                        {
                            return $this->withError('红包的金额最多4位小数');
                        }
                        if($money < 1)
                        {
                            return $this->withError('红包的金额不能小于1');
                        }
                        if($money > 10000){
                            return $this->withError('红包的数量最大只能是10000');
                        }
                        $kou_yue = DsUserTongzheng::del_tongzheng($user_id,$money,'红包-发送给['.$redwrap_user_id.']');
                        if(empty($kou_yue)){
                            return $this->withError('当前发红包的人数较多，请稍后再试');
                        }
                        break;
                    case '2':
                        if($userInfo['puman'] < $money){
                            return $this->withError('你的余额不足');
                        }
                        if(strlen(substr($money, strrpos($money, '.')+1)) > 2)
                        {
                            return $this->withError('红包的金额最多2位小数');
                        }
                        if($money > 50000){
                            return $this->withError('红包的数量最大只能是50000');
                        }
                        if($money < 0.01)
                        {
                            return $this->withError('红包的金额不能小于0.01');
                        }
                        $kou_yue = DsUserPuman::del_puman($user_id,$money,'红包-发送给['.$redwrap_user_id.']');
                        if(empty($kou_yue)){
                            return $this->withError('当前发红包的人数较多，请稍后再试');
                        }
                        break;
                    default:
                        return $this->withError('没开通该类型');
                        break;
                }
                $time = time();
                $data = [
                    'user_id' => $user_id,
                    'money' => $money,
                    'type' => 0,//红包类型 0普通红包 1专属红包 2拼手气红包
                    'class' => $class,//红包类型 id 1通证红包 2扑满红包
                    'scene' => $scene,//红包场景 1单聊 2群聊
                    'status' => 1,//状态： 0未领取，1领取中/展示中 ，2已领完
                    'num' => 1,//红包个数
                    'style' => $class_data['redwrap_data_img'],
                    'time' => $time,
                    'redwrap_user_id' => $redwrap_user_id,
                    'time_end' => $time + 86400,
                ];
                $res_bao_insert = DsRedwrapOut::query()->insertGetId($data);
                if(empty($res_bao_insert)){
                    //退回对应的余额
                    switch ($class){
                        case '1':
                            $tui_yue = DsUserTongzheng::add_tongzheng($user_id,$money,'退回红包-发送给['.$redwrap_user_id.']');
                            if(empty($tui_yue)){
                                DsErrorLog::add_log('红包-通证-退回失败',json_encode($data),$user_id);
                            }
                            break;
                        case '2':
                            $tui_yue = DsUserPuman::add_puman($user_id,$money,'退回红包-发送给['.$redwrap_user_id.']');
                            if(empty($tui_yue)){
                                DsErrorLog::add_log('红包-扑满-退回失败',json_encode($data),$user_id);
                            }
                            break;
                        default:
                            DsErrorLog::add_log('红包-没有该退回类型',json_encode($data),$user_id);
                            break;
                    }
                    return $this->withError('当前发红包人数较多，请稍后再试！');
                }
                $redwrap_res['redwrap_out_id'] = $res_bao_insert;
                break;
            case '2'://群聊红包
                return $this->withError('红包开发测试中,请耐心等待');
                break;
            default:
                return $this->withError('没有该类型');
                break;
        }

        return $this->withResponse('下发成功',$redwrap_res);
    }


    /**
     * 交付红包 定制
     * @RequestMapping(path="redwrap_get2",methods="post")
     * @RateLimit(create=1, consume=1, capacity=1, key={ChatController::class, "_key"})
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function redwrap_get2(){
        $user_id   = Context::get('user_id');
        $userInfo   = Context::get('userInfo','');

        $redwrap_switch = $this->redis0->get('redwrap_switch');
        if($redwrap_switch != 1){
            return $this->withError('暂未开放红包功能');
        }

        //校验领红包是否频繁
        $user_id_number = $this->redis2->get('redwrap_user_get_number_'.$user_id);
        if(!empty($user_id_number)){
            if($user_id_number > 800){
                return $this->withError('当天交付红包次数频繁800次，请联系客服！');
            }
            $this->redis2->incr('redwrap_user_get_number_'.$user_id);
        }else{
            $this->redis2->set('redwrap_user_get_number_'.$user_id,1,$this->get_end_t_time());
        }

        $this->check_often($user_id);
        $this->start_often($user_id);


        $this->_check_auth();

        $redwrap_out_id = $this->request->post('redwrap_out_id',0);//发红包ID
        if(empty($redwrap_out_id)){
            return $this->withError('没有发出红包');
        }
        $redwrap_out_array = DsRedwrapOut::query()->where('user_id',$user_id)->where('redwrap_out_id',$redwrap_out_id)->first();
        if(empty($redwrap_out_array)){
            return $this->withError('没有发出该红包');
        }
        $redwrap_out_array = $redwrap_out_array->toArray();
        if($redwrap_out_array['status'] == '2'){
            return $this->withError('该红包已经交付过了，请不要重复操作');
        }
        if($redwrap_out_array['status'] == '3'){
            return $this->withError('已经过期了，无法交付，请联系客服');
        }
        if($redwrap_out_array['time_end'] < time()){
            //return $this->withError('已经过期了，无法交付，请联系客服');
        }
        if(empty($redwrap_out_array['money']) || $redwrap_out_array['money'] <= 0)
        {
            return $this->withError('没有可交付的红包金额');
        }
        if($redwrap_out_array['status'] == '4'){
            return $this->withError('申述中，无法交付，详情请联系客服');
        }
        if($redwrap_out_array['status'] == '5'){
            return $this->withError('已经被处理交付，详情请联系客服');
        }
        if($redwrap_out_array['status'] == '6'){
            return $this->withError('已经被处理退回，详情请联系客服');
        }

        //校验是否领取过该红包 对方领取人
        $res_lingqu = DsRedwrapGet::query()->where('user_id',$redwrap_out_array['redwrap_user_id'])->where('redwrap_out_id',$redwrap_out_id)->value('redwrap_get_id');
        if(!empty($res_lingqu)){
            return $this->withError('已经交付过了');
        }

        $redwrap_res = [
            'redwrap_out_id' => null,
            'redwrap_get_id' => null,
        ];

        switch ($redwrap_out_array['scene'])
        {
            case '1'://单聊领取
                //是否校验是否朋友 不需要

                //将发出红包先置为已领取
                $redwrap_out_over = DsRedwrapOut::query()->where('redwrap_out_id',$redwrap_out_id)->update([
                    'status' => 2,
                    'time_over' => time(),
                ]);
                if(empty($redwrap_out_over)){
                    return $this->withError('当前领取人数角较多，请稍后再试');
                }
                $data = [
                    'user_id' =>   $redwrap_out_array['redwrap_user_id'],
                    'redwrap_out_id' =>   $redwrap_out_array['redwrap_out_id'],
                    'user_id_out' =>   $redwrap_out_array['user_id'],
                    'money' =>   $redwrap_out_array['money'],
                    'class' =>   $redwrap_out_array['class'],
                    'style' =>   $redwrap_out_array['style'],
                    'time' =>   time(),
                ];
                $res_lingqu_ok = DsRedwrapGet::query()->insertGetId($data);
                if(empty($res_lingqu_ok)){
                    //还原红包发出状态
                    $redwrap_out_over = DsRedwrapOut::query()->where('redwrap_out_id',$redwrap_out_id)->update([
                        'status' => 1,
                        'time_over' => 0,
                    ]);
                    if(empty($redwrap_out_over)){
                        DsErrorLog::add_log('红包-交付-还原状态失败',json_encode($redwrap_out_array),'红包-交付-还原状态失败');
                    }
                    return $this->withError('当前交付人数较多，请稍后再试');
                }else{

                    //$comon_nickname = DsUser::query()->where('user_id',$redwrap_out_array['user_id'])->value('nickname');
                    //if(empty($comon_nickname)){
                        // $comon_nickname = '';
                        //}
                    //开始获得红包
                    switch ($redwrap_out_array['class']){
                        case '1':
                            $get_yue = DsUserTongzheng::add_tongzheng($redwrap_out_array['redwrap_user_id'],$redwrap_out_array['money'],'红包-收到用户['.$redwrap_out_array['user_id'].']');
                            if(empty($get_yue)){
                                DsErrorLog::add_log('红包-通证-交付失败',json_encode($data),$redwrap_out_array['user_id']);
                                return $this->withError('当前交付人数较多，请稍后再试！');
                            }
                            break;
                        case '2':
                            $get_yue = DsUserPuman::add_puman($redwrap_out_array['redwrap_user_id'],$redwrap_out_array['money'],'红包-收到用户['.$redwrap_out_array['user_id'].']');
                            if(empty($get_yue)){
                                DsErrorLog::add_log('红包-扑满-交付失败',json_encode($data),$redwrap_out_array['user_id']);
                                return $this->withError('当前交付人数较多，请稍后再试！！');
                            }
                            break;
                        default:
                            return $this->withError('没有该类型！');
                            break;
                    }
                    $redwrap_res['redwrap_out_id'] =  $redwrap_out_array['redwrap_out_id'];//查看详情
                    $redwrap_res['redwrap_get_id'] =  $res_lingqu_ok;//领取交付id
                }
                break;
            case '2':
                return $this->withError('群聊红包开发测试中,请耐心等待');
                break;
            default:
                return $this->withError('没有该类型');
                break;
        }
        return $this->withResponse('交付成功',$redwrap_res);
    }


    /**
     *
     * 查询其他用户信息
     * @RequestMapping(path="get_to_user_info",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get_to_user_info(){
        $userInfo     = Context::get('userInfo');
        $user_id   = Context::get('user_id');
        $user_to_id = $this->request->post('user_to_id',0);
        if(empty($user_to_id) || $user_to_id < 0){
            return $this->withError('没有该用户信息');
        }

        $res = DsUser::query()->where('user_id',$user_to_id)->first();
        if (!empty($res)){
            $res = $res->toArray();
        }else{
            return $this->withError('没有该用户信息!');
        }

        $res2['user_id'] = $res['user_id'];
        $res2['nickname'] = $res['nickname'];
        $res2['avatar'] = $res['avatar'];
        $res2['auth'] = $res['auth'];
        $res2['username'] = '';
        $res2['is_vip'] = $res['role_id'];
        $res2['group'] = $res['group'];

        return $this->withResponse('成功',$res2);
    }


    /**
     *
     * 申述
     * @RequestMapping(path="redwrap_shenshu",methods="post")
     * @RateLimit(create=1, consume=1, capacity=1, key={ChatController::class, "_key"})
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function redwrap_shenshu(){
        $userInfo     = Context::get('userInfo');
        $user_id   = Context::get('user_id');
        $redwrap_out_id = $this->request->post('redwrap_out_id',0);//发红包ID
        if(empty($redwrap_out_id)){
            return $this->withError('没有该红包信息');
        }

        $hongbao_zhongcai = $this->redis0->get('hongbao_zhongcai');
        if($hongbao_zhongcai != 1){
            return $this->withError('暂未开放！');
        }

        $redwrap_out_array = DsRedwrapOut::query()->where('redwrap_out_id',$redwrap_out_id)->first();
        if(empty($redwrap_out_array)){
            return $this->withError('没有发出该红包');
        }
        $redwrap_out_array = $redwrap_out_array->toArray();

        $arrok = [$redwrap_out_array['user_id']];
        if(!in_array($user_id,$arrok)){
            return $this->withError('当前只能发红包者申述');
        }

        if($redwrap_out_array['status'] == '2'){
            return $this->withError('该红包已经交付过了');
        }
        if($redwrap_out_array['status'] == '3'){
            //return $this->withError('已经过期了，无法交付，请联系客服');
        }
        if($redwrap_out_array['time_end'] < time()){
            //return $this->withError('已经过期了，无法交付，请联系客服');
        }
        if($redwrap_out_array['status'] == '4'){
            return $this->withError('已经在申述中');
        }
        if($redwrap_out_array['status'] == '5'){
            return $this->withError('已经处理下发');
        }
        if($redwrap_out_array['status'] == '6'){
            return $this->withError('已经处理驳回');
        }

        $re = DsRedwrapOut::query()->where('redwrap_out_id',$redwrap_out_id)->update(['status'=>4]);
        if(empty($re)){
            return $this->withError('申述失败');
        }

        return $this->withSuccess('申述成功');
    }

    /**
     *
     * 获取用户token
     * @RequestMapping(path="get_user_hx_tokenupdate",methods="post,get")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get_user_hx_tokenupdate(){
        $user_id = $this->request->post('user_id',0);
        if(empty($user_id)){
            return $this->withError('没有该信息');
        }

        $user_id_number = $this->redis2->get('user_token_number_'.$user_id);
        if(!empty($user_id_number)){
            if($user_id_number > 100){
                return $this->withError('获取次数过多，请等待');
            }
            $this->redis2->incr('user_token_number_'.$user_id);
        }else{
            $this->redis2->set('user_token_number_'.$user_id,1,$this->get_end_t_time());
        }

        //异步
//        $info = [
//            'type'        => '44',
//            'user_id'        => $user_id,
//        ];
//        $this->yibu($info);

//        //同步
//        $chathxtoken = make(ChatIm::class);
//        $data['huanxin_token'] = $chathxtoken->get_user_token_do($user_id);
//        return $this->withResponse('ok',$data);

        //同步
        $chathxtoken = make(ChatIm::class);
        $data['huanxin_token'] = $chathxtoken->UserToken($user_id);
        return $this->withResponse('ok',$data);

        //return $this->withSuccess('OK');
    }

    /**
     * 发送者申请取消操作
     * @RequestMapping(path="redwrap_fasong_quxiao_edit",methods="post")
     * @RateLimit(create=1, consume=1, capacity=1, key={ChatController::class, "_key"})
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function redwrap_fasong_quxiao_edit(){
        $userInfo     = Context::get('userInfo');
        $user_id   = Context::get('user_id');
        $redwrap_out_id = $this->request->post('redwrap_out_id',0);//发红包ID

        if(empty($redwrap_out_id)){
            return $this->withError('没有该红包信息');
        }

        $redwrap_out_array = DsRedwrapOut::query()->where('user_id',$user_id)->where('redwrap_out_id',$redwrap_out_id)->first();
        if(empty($redwrap_out_array)){
            return $this->withError('没有发出该红包');
        }
        $redwrap_out_array = $redwrap_out_array->toArray();

        if($redwrap_out_array['status'] == '2'){
            return $this->withError('该红包已经交付过了');
        }
        if($redwrap_out_array['status'] == '3'){
            //return $this->withError('已经过期了，无法交付，请联系客服');
        }
        if($redwrap_out_array['time_end'] < time()){
            //return $this->withError('已经过期了，无法交付，请联系客服');
        }
        if($redwrap_out_array['status'] == '4'){
            return $this->withError('已经在申述中');
        }
        if($redwrap_out_array['status'] == '5'){
            return $this->withError('已经处理下发');
        }
        if($redwrap_out_array['status'] == '6'){
            return $this->withError('已经处理驳回');
        }
        if($redwrap_out_array['status2'] == '1'){
            return $this->withError('已经申请取消中，请不要重复操作');
        }
        if($redwrap_out_array['status2'] == '2'){
            return $this->withError('已经允许取消,请前去取消');
        }
        if($redwrap_out_array['status2'] == '3'){
            return $this->withError('已经不同意取消,无法重复操作');
        }

        $res = DsRedwrapOut::query()->where('user_id',$redwrap_out_array['user_id'])->where('redwrap_out_id',$redwrap_out_id)->update([
            'status2' => 1,
        ]);
        if(!empty($res)){
            return $this->withSuccess('操作成功');
        }else{
            return $this->withSuccess('当前操作人数较多，请稍后再试');
        }
    }

    /**
     * 接收者取消或通过授权操作
     * @RequestMapping(path="redwrap_quxiao_edit",methods="post")
     * @RateLimit(create=1, consume=1, capacity=1, key={ChatController::class, "_key"})
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function redwrap_quxiao_edit(){
        $userInfo     = Context::get('userInfo');
        $user_id   = Context::get('user_id');
        $redwrap_out_id = $this->request->post('redwrap_out_id',0);//发红包ID
        $status2 = $this->request->post('status2',0);//类型 2同意取消 3不同意取消
        if(empty($redwrap_out_id)){
            return $this->withError('没有该红包信息');
        }

        if(empty($status2)){
            return $this->withError('状态不能为空');
        }
        if(!in_array($status2,[2,3])){
            return $this->withError('取消状态不正确');
        }

        $redwrap_out_array = DsRedwrapOut::query()->where('redwrap_user_id',$user_id)->where('redwrap_out_id',$redwrap_out_id)->first();
        if(empty($redwrap_out_array)){
            return $this->withError('没有收到该红包');
        }
        $redwrap_out_array = $redwrap_out_array->toArray();

        if($redwrap_out_array['status'] == '2'){
            return $this->withError('该红包已经交付过了');
        }
        if($redwrap_out_array['status'] == '3'){
            //return $this->withError('已经过期了，无法交付，请联系客服');
        }
        if($redwrap_out_array['time_end'] < time()){
            //return $this->withError('已经过期了，无法交付，请联系客服');
        }
        if($redwrap_out_array['status'] == '4'){
            return $this->withError('已经在申述中');
        }
        if($redwrap_out_array['status'] == '5'){
            return $this->withError('已经处理下发');
        }
        if($redwrap_out_array['status'] == '6'){
            return $this->withError('已经处理退回');
        }
        if($redwrap_out_array['status2'] == '0'){
            return $this->withError('发送者还未处理,无法操作');
        }
        if($redwrap_out_array['status2'] == '2'){
            return $this->withError('已经允许取消,无法重复操作');
        }
        if($redwrap_out_array['status2'] == '3'){
            return $this->withError('已经不同意取消,无法重复操作');
        }

        if ($status2 == '2'){//同意取消 并返还
            $res = DsRedwrapOut::query()->where('redwrap_out_id',$redwrap_out_array['redwrap_out_id'])->where('redwrap_user_id',$user_id)->update([
                'status' => 6,
                'status2' => intval($status2),
            ]);
            if(empty($res)){
                return $this->withError('当前操作人数较多，请稍后再试');
            }
            //退钱
            switch ($redwrap_out_array['class']){
                case '1':
                    $tui_yue = DsUserTongzheng::add_tongzheng($redwrap_out_array['user_id'],$redwrap_out_array['money'],'退回红包-发送给['.$redwrap_out_array['redwrap_user_id'].']');
                    if(empty($tui_yue)){
                        DsErrorLog::add_log('红包-通证-退回失败',json_encode($redwrap_out_array),$redwrap_out_array['user_id']);
                    }
                    break;
                case '2':
                    $tui_yue = DsUserPuman::add_puman($redwrap_out_array['user_id'],$redwrap_out_array['money'],'退回红包-发送给['.$redwrap_out_array['redwrap_user_id'].']');
                    if(empty($tui_yue)){
                        DsErrorLog::add_log('红包-扑满-退回失败',json_encode($redwrap_out_array),$redwrap_out_array['user_id']);
                    }
                    break;
                default:
                    DsErrorLog::add_log('红包-没有该退回类型',json_encode($redwrap_out_array),$redwrap_out_array['user_id']);
                    return $this->withSuccess('没有该取消类型');
                    break;
            }
        }else{//不同意
            $res = DsRedwrapOut::query()->where('redwrap_out_id',$redwrap_out_array['redwrap_out_id'])->where('redwrap_user_id',$user_id)->update([
                'status2' => intval($status2),
            ]);
        }


        if(!empty($res)){
            return $this->withSuccess('操作成功');
        }else{
            return $this->withSuccess('当前操作人数较多，请稍后再试');
        }
    }


    /**
     * 红包-取消
     * @RequestMapping(path="redwrap_quiao",methods="post,get")
     * @RateLimit(create=1, consume=1, capacity=1, key={ChatController::class, "_key"})
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function redwrap_quiao(){
        return $this->withError('更新');
        $userInfo     = Context::get('userInfo');
        $user_id   = Context::get('user_id');
        $redwrap_out_id = $this->request->post('redwrap_out_id',0);//发红包ID
        if(empty($redwrap_out_id)){
            return $this->withError('没有该红包信息');
        }

        $redwrap_out_array = DsRedwrapOut::query()->where('user_id',$user_id)->where('redwrap_out_id',$redwrap_out_id)->first();
        if(empty($redwrap_out_array)){
            return $this->withError('没有发出该红包,无法取消');
        }
        $redwrap_out_array = $redwrap_out_array->toArray();

        $arrok = [$redwrap_out_array['user_id']];
        if(!in_array($user_id,$arrok)){
            return $this->withError('当前只能发红包者操作');
        }

        if($redwrap_out_array['status'] == '2'){
            return $this->withError('该红包已经交付过了,无法取消');
        }
        if($redwrap_out_array['status'] == '3'){
            //return $this->withError('已经过期了，无法交付，请联系客服');
        }
        if($redwrap_out_array['time_end'] < time()){
            //return $this->withError('已经过期了，无法交付，请联系客服');
        }
        if($redwrap_out_array['status'] == '4'){
            return $this->withError('已经在申述中,无法取消');
        }
        if($redwrap_out_array['status'] == '5'){
            return $this->withError('已经处理下发,无法取消');
        }
        if($redwrap_out_array['status'] == '6'){
            return $this->withError('已经处理驳回,无法取消');
        }
        if($redwrap_out_array['status2'] == '0'){
            return $this->withError('接收者还未处理,无法取消');
        }
        if($redwrap_out_array['status2'] == '1'){
            return $this->withError('已经在申请取消中,无法取消');
        }
        if($redwrap_out_array['status2'] == '3'){
            return $this->withError('接收方不同意取消,无法取消,请前去申述');
        }

        //开始取消 先改状态
        $re = DsRedwrapOut::query()->where('redwrap_out_id',$redwrap_out_id)->where('status2',2)->where('user_id',$user_id)->update(['status'=>6]);
        if(empty($re)){
            return $this->withError('取消人数过多,请稍后再试');
        }

        //退钱
        switch ($redwrap_out_array['class']){
            case '1':
                $tui_yue = DsUserTongzheng::add_tongzheng($redwrap_out_array['user_id'],$redwrap_out_array['money'],'退回红包-发送给['.$redwrap_out_array['redwrap_user_id'].']');
                if(empty($tui_yue)){
                    DsErrorLog::add_log('红包-通证-退回失败',json_encode($redwrap_out_array),$redwrap_out_array['user_id']);
                }
                break;
            case '2':
                $tui_yue = DsUserPuman::add_puman($redwrap_out_array['user_id'],$redwrap_out_array['money'],'退回红包-发送给['.$redwrap_out_array['redwrap_user_id'].']');
                if(empty($tui_yue)){
                    DsErrorLog::add_log('红包-扑满-退回失败',json_encode($redwrap_out_array),$redwrap_out_array['user_id']);
                }
                break;
            default:
                DsErrorLog::add_log('红包-没有该退回类型',json_encode($redwrap_out_array),$redwrap_out_array['user_id']);
                return $this->withSuccess('没有该取消类型');
                break;
        }

        return $this->withSuccess('取消成功');
    }


    /**
     * 创建群聊初始化
     * @RequestMapping(path="qun_add_csh",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function qun_add_csh(){
        $userInfo     = Context::get('userInfo');
        $user_id   = Context::get('user_id');

        $data = [];

        $res = $this->_is_vip($user_id);
        if(!empty($res)){
            $data['is_500'] = 1;
        }else{
            $data['is_500'] = 0;
        }
        $res_3000 = DsChatRoomRank::query()->where('user_id',$user_id)->where('type',1)->value('chat_qun_room_id');
        if(!empty($res_3000)){
            $data['is_3000'] = 1;
        }else{
            $data['is_3000'] = 0;
        }

        return $this->withResponse('获取成功',$data);
    }

    /**
     *
     * 支付群聊费用
     * @RequestMapping(path="qun_add",methods="post")
     * @RateLimit(create=1, consume=1, capacity=1, key={ChatController::class, "_key"})
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function qun_add(){
        $userInfo     = Context::get('userInfo');
        $user_id   = Context::get('user_id');
        $type = $this->request->post('type',0);//类型 1是3000
        $data = [];

        if(empty($type)){
            return $this->withSuccess('请选择创群类型');
        }

        $this->check_often($user_id);
        $this->start_often($user_id);

        $this->_check_auth();

        $res = DsChatRoomRank::query()->where('user_id',$user_id)->where('type',$type)->value('chat_qun_room_id');
        if(!empty($res)){
            return $this->withSuccess('你已经支付，请勿重复支付');
        }

        //先扣钱
        switch ($type){
            case '1':
                $money = 10;
                if($userInfo['tongzheng'] < $money){
                    return $this->withSuccess('通证不足，还差'.round($money-$userInfo['tongzheng'],4));
                }
                $re = DsUserTongzheng::del_tongzheng($user_id,$money,'创建群聊-3000-扣除');
                if(!empty($re)){
                    $inser = [
                        'user_id' => $user_id,
                        'type' => $type,
                        'time' => time(),
                        'money' => $money,
                    ];
                    $inser_roo = DsChatRoomRank::query()->insertGetId($inser);
                    if(empty($inser_roo)){
                        $tui = DsUserTongzheng::add_tongzheng($user_id,$money,'创建群聊-3000-退回');
                        if(empty($tui)){
                            DsErrorLog::add_log('创建群聊-3000-退回-失败',json_encode($inser),'创建群聊-3000-退回-失败');
                        }
                        return $this->withError('当前人数较多，请稍后再试!');
                    }else{
                        return $this->withSuccess('支付成功');
                    }
                }else{
                    return $this->withError('当前人数较多，请稍后再试');
                }
                break;
            default:
                return $this->withError('没有该类型');
                break;
        }
    }

    /**
     * 获取红包明细
     * @RequestMapping(path="get_user_redwrap_logs",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get_user_redwrap_logs(){
        $user_id  = Context::get('user_id');
        $userInfo     = Context::get('userInfo');
        $page     = $this->request->post('page',1);
        $type     = $this->request->post('type',0);//1他人向我展示的 2我向他人展示的 3他人向我交付的 4我向他人交付的

        if($page <= 0)
        {
            $page = 1;
        }

        $data['more']           = '0';
        $data['data']         = [];

        switch ($type){
                    case '1':
                        $res = DsRedwrapOut::query()->where('redwrap_user_id',$user_id)
                            ->where('status',1)
                            ->where('class',1)
                            ->orderByDesc('redwrap_out_id')
                            ->forPage($page,10)->get()->toArray();
                        break;
                    case '2':
                        $res = DsRedwrapOut::query()->where('user_id',$user_id)
                            ->where('status',1)
                            ->where('class',1)
                            ->orderByDesc('redwrap_out_id')
                            ->forPage($page,10)->get()->toArray();
                        break;
                    case '3':
                        $res = DsRedwrapOut::query()->where('redwrap_user_id',$user_id)
                            ->where('status',1)
                            ->where('class',2)
                            ->orderByDesc('redwrap_out_id')
                            ->forPage($page,10)->get()->toArray();
                        break;
                    case '4':
                        $res = DsRedwrapOut::query()->where('user_id',$user_id)
                            ->where('status',1)
                            ->where('class',2)
                            ->orderByDesc('redwrap_out_id')
                            ->forPage($page,10)->get()->toArray();
                        break;
                    case '5':
                        $res = DsRedwrapOut::query()->where('redwrap_user_id',$user_id)
                            ->where('status',2)
                            ->where('class',1)
                            ->orderByDesc('redwrap_out_id')
                            ->forPage($page,10)->get()->toArray();
                        break;
                    case '6':
                        $res = DsRedwrapOut::query()->where('user_id',$user_id)
                            ->where('status',2)
                            ->where('class',1)
                            ->orderByDesc('redwrap_out_id')
                            ->forPage($page,10)->get()->toArray();
                        break;
                    case '7':
                        $res = DsRedwrapOut::query()->where('redwrap_user_id',$user_id)
                            ->where('status',2)
                            ->where('class',2)
                            ->orderByDesc('redwrap_out_id')
                            ->forPage($page,10)->get()->toArray();
                        break;
                    case '8':
                        $res = DsRedwrapOut::query()->where('user_id',$user_id)
                            ->where('status',2)
                            ->where('class',2)
                            ->orderByDesc('redwrap_out_id')
                            ->forPage($page,10)->get()->toArray();
                        break;
                    default:
                        $res = DsRedwrapOut::query()->where('user_id',$user_id)
                            ->orWhere('redwrap_user_id',$user_id)
                            ->orderByDesc('redwrap_out_id')
                            ->forPage($page,10)->get()->toArray();
                        break;
                }
//
//            if($class == '2'){
//                switch ($type){
//                    case '1':
//                        $res = DsRedwrapOut::query()->where('redwrap_user_id',$user_id)
//                            ->where('status',1)
//                            ->where('class',2)
//
//                            ->orderByDesc('redwrap_out_id')
//                            ->forPage($page,10)->get()->toArray();
//                        break;
//                    case '2':
//                        $res = DsRedwrapOut::query()->where('user_id',$user_id)
//                            ->where('status',1)
//                            ->where('class',2)
//
//                            ->orderByDesc('redwrap_out_id')
//                            ->forPage($page,10)->get()->toArray();
//                        break;
//                    case '3':
//                        $res = DsRedwrapOut::query()->where('redwrap_user_id',$user_id)
//                            ->where('status',2)
//                            ->where('class',2)
//
//                            ->orderByDesc('redwrap_out_id')
//                            ->forPage($page,10)->get()->toArray();
//                        break;
//                    case '4':
//                        $res = DsRedwrapOut::query()->where('user_id',$user_id)
//                            ->where('status',2)
//                            ->where('class',2)
//
//                            ->orderByDesc('redwrap_out_id')
//                            ->forPage($page,10)->get()->toArray();
//                        break;
//                    default:
//                        $res = DsRedwrapOut::query()->where('user_id',$user_id)
//                            ->orWhere('redwrap_user_id',$user_id)
//                            ->orderByDesc('redwrap_out_id')
//                            ->forPage($page,10)->get()->toArray();
//                        break;
//                }



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

                if($v['user_id'] == $user_id){
                    $datas[$k]['is_out_type'] = 1;//发出
                    $datas[$k]['avatar'] =  '';
                    $datas[$k]['nickname'] = '';
                    $user = DsUser::query()->where('user_id',$v['redwrap_user_id'])->select('avatar','nickname')->first();
                    if(!empty($user)){
                        $user = $user->toArray();
                        $datas[$k]['avatar'] =  $user['avatar'];
                        $datas[$k]['nickname'] = $user['nickname'];
                    }
                }else{
                    $datas[$k]['is_out_type'] = 2;//接收
                    $datas[$k]['avatar'] =  '';
                    $datas[$k]['nickname'] = '';
                    $user = DsUser::query()->where('user_id',$v['user_id'])->select('avatar','nickname')->first();
                    if(!empty($user)){
                        $user = $user->toArray();
                        $datas[$k]['avatar'] =  $user['avatar'];
                        $datas[$k]['nickname'] = $user['nickname'];
                    }
                }

                $datas[$k]['class'] = $v['class'];
                $datas[$k]['status'] = $v['status'];
                $datas[$k]['status2'] = $v['status2'];
                $datas[$k]['user_id'] = $v['user_id'];
                $datas[$k]['redwrap_user_id']  = $v['redwrap_user_id'];
                $datas[$k]['money']  = $v['money'];
                $datas[$k]['time']  = $this->replaceTime($v['time']);
                $datas[$k]['time2']  = date('Y年m月d日',$v['time']);
                $datas[$k]['redwrap_out_id'] = $v['redwrap_out_id'];

                $datas[$k]['is_time2'] = 1;//判断显示日期
                if($k > 0){
                    if($datas[$k]['time2'] == $datas[$k-1]['time2']){
                        $datas[$k]['is_time2'] = 0;
                    }
                }
            }
            $data['data']           = $datas;
        }

        return $this->withResponse('获取成功',$data);
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
