<?php

declare(strict_types=1);

namespace App\Controller\v2;

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
 * Class ChatController
 * @Middlewares({
 *     @Middleware(SignatureMiddleware::class),
 *     @Middleware(UserMiddleware::class)
 * })
 * @package App\Controller\v2
 * @Controller(prefix="v2/chat")
 */
class ChatController  extends XiaoController
{

    /**
     * 发红包 定制
     * @RequestMapping(path="redwrap_out2",methods="post")
     * @RateLimit(create=1, consume=1, capacity=1, key={ChatController::class, "_key"})
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function redwrap_out2(){
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

        //校验验证码
        $code = $this->request->post('code','');
        if(empty($code))
        {
            return $this->withError('验证码不能为空');
        }
        $this->_checkCode($userInfo['mobile'],'redwrap_out',$code,time(),$user_id);

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
     * 返回用户id
     * @return string
     */
    public static function _key(): string
    {
        $user_id    = Context::get('user_id');
        return strval($user_id);
    }

}
