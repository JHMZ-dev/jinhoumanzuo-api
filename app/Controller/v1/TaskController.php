<?php declare(strict_types=1);

namespace App\Controller\v1;


use App\Controller\async\YDYanZhen;
use App\Controller\XiaoController;
use App\Model\DsAdError;
use App\Model\DsErrorLog;
use App\Model\DsTaskPack;
use App\Model\DsUser;
use App\Model\DsUserDatum;
use App\Model\DsUserTaskPack;
use App\Model\DsUserYingpiao;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Annotation\Middleware;
use App\Middleware\UserMiddleware;
use Hyperf\HttpServer\Annotation\Middlewares;
use App\Middleware\SignatureMiddleware;
use Hyperf\Utils\Context;
use Hyperf\RateLimit\Annotation\RateLimit;
use Swoole\Exception;

/**
 * 任务接口
 * Class TaskController
 * @Middlewares({
 *     @Middleware(SignatureMiddleware::class),
 *     @Middleware(UserMiddleware::class)
 * })
 * @package App\Controller\v1
 * @Controller(prefix="v1/task")
 */
class TaskController extends XiaoController
{

    /**
     * 获取首页
     * @RequestMapping(path="get_index",methods="post")
     */
    public function get_index()
    {
        $user_id  = Context::get('user_id');
        $userInfo     = Context::get('userInfo');
        $day = date('Y-m-d');

        $this->update_vip_yaohao_num();
        $data['love']   = $userInfo['love']<0?0:$userInfo['love'];
            //影票
        $data['jinri']   = strval($this->redis3->get($day.$user_id.'_get_yingpiao')?round($this->redis3->get($day.$user_id.'_get_yingpiao'),$this->xiaoshudian):0);
        $data['zuori']   = strval($this->redis3->get($this->get_yesterday().$user_id.'_get_yingpiao')?round($this->redis3->get($this->get_yesterday().$user_id.'_get_yingpiao'),$this->xiaoshudian):0);
        $data['all']     = strval($this->redis3->get($user_id.'_get_yingpiao')?round($this->redis3->get($user_id.'_get_yingpiao'),$this->xiaoshudian):0);
        $data['shengyu'] = strval(DsUserTaskPack::query()->where('user_id',$user_id)->where('status',1)->sum('shengyu'));
        $data['is_vip']  = 0;
        $data['vip_num'] = '';
        $data['vip_is_yao'] = 0;
        //获取自己是否是会员
        $vip = $this->_is_vip($user_id);
        if($vip)
        {
            $data['is_vip'] = 1;
            //判断今日是否摇号
            $yao = $this->redis3->exists($day.'_vip_num_'.$user_id);
            if($yao)
            {
                $data['vip_num'] = $this->redis3->get($day.'_vip_num_'.$user_id);
                $data['vip_is_yao'] = 1;
            }
        }
        //判断任务是否完成
        $name = $user_id.'_'.$day.'_app_renwu';
        $namesss = $name.'_status';
        $name1 = $name.'_ci';
        $bao = $this->redis4->get($namesss);
        $data['today_renwu_ci'] = $this->redis4->get($name1)?$this->redis4->get($name1):0;
        $data['today_renwu_ci_res'] = $this->redis0->get('task_day')?$this->redis0->get('task_day'):6;
        $data['today_lingqu'] = 0;
        if($bao == 2)
        {
            $data['today_lingqu'] = 1;
            $data['today_renwu_ci'] = $data['today_renwu_ci_res'];
        }

        //获取任务包
        $data['liuliang']   = [];
        $info = DsTaskPack::query()->where('status',1)->orderBy('paixu')
            ->select('task_pack_id','name','need','get','day','num','img','huoyuezhi','gongxianzhi')
            ->get()->toArray();
        if(!empty($info))
        {
            //处理数据
            foreach ($info as $k =>$v)
            {
                $info[$k]['name'] = $v['need'].$v['name'];
                //查找当前持有数
                $info[$k]['my_num'] = 0;
                $count = DsUserTaskPack::query()->where(['user_id'=>$user_id,'task_pack_id' =>$v['task_pack_id'],'status'=> 1 ])->count();
                if($count > 0)
                {
                    $info[$k]['my_num'] = $count;
                }
                $info[$k]['one_get'] = round($v['get']/$v['day'],$this->xiaoshudian);
            }
            $data['liuliang'] = $info;
        }
        $data['auth_liuliang']   = [];
        //判断自己是否领取
        $_auth_bao = $this->redis5->get($user_id.'_auth_bao');
        if($_auth_bao != 2)
        {
            $data['auth_liuliang'][0]   = DsTaskPack::query()
                ->where('task_pack_id',7)
                ->select('task_pack_id','name','need','get','day','num','img','huoyuezhi','gongxianzhi')
                ->first()->toArray();
            $data['auth_liuliang'][0]['one_get'] = round($data['auth_liuliang'][0]['get']/$data['auth_liuliang'][0]['day'],$this->xiaoshudian);
            $data['auth_liuliang'][0]['my_num'] = 0;
            $data['auth_liuliang'][0]['name'] = '免费领取';
        }
        $data['liuliang_status']  = 0;
        $count = DsUserTaskPack::query()->where('user_id',$user_id)->where('status',1)->count();
        if($count > 0)
        {
            $data['liuliang_status']  = 1;
        }
        //获取会员今日幸运号码列表
        $data['vip_xinyun_num']  = $this->redis0->sMembers($day.'_vip_num');
        return $this->withResponse('获取成功',$data);
    }

    /**
     * 领取实名流量
     * @RequestMapping(path="draw_auth",methods="post")
     * @RateLimit(create=1, consume=1, capacity=1, key={TaskController::class, "_key"})
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Swoole\Exception
     */
    public function draw_auth()
    {
        //判断是否能兑换
        $rent_status = $this->redis0->get('rent_status');
        if($rent_status != 1)
        {
            return $this->withError('该功能维护,请耐心等待通知！');
        }

        $user_id  = Context::get('user_id');

        $this->_check_auth();

        //判断是否请求过于频繁
        $this->check_often($user_id);
        //增加请求频繁
        $this->start_often($user_id);

        $task_pack_id     = $this->request->post('task_pack_id',0);
        if($task_pack_id != 7)
        {
            return $this->withError('此流量不存在');
        }
        //判断用户是否已领取
        $_auth_bao = $this->redis5->get($user_id.'_auth_bao');
        if($_auth_bao == 2)
        {
            return $this->withError('您已领取！');
        }
        $info = [
            'type'          => '17',
            'user_id'       => $user_id,
        ];
        $this->yibu($info);
        return $this->withSuccess('领取成功,请前往我的流量查看！');

    }

    /**
     * 兑换流量
     * @RequestMapping(path="rent",methods="post")
     * @RateLimit(create=1, consume=1, capacity=1, key={TaskController::class, "_key"})
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Swoole\Exception
     */
    public function rent()
    {
        //检测版本
        $this->_check_version('1.1.3');

        //判断是否能兑换
        $rent_status = $this->redis0->get('rent_status');
        if($rent_status != 1)
        {
            return $this->withError('该功能维护,请耐心等待通知！');
        }
        $user_id  = Context::get('user_id');
        $userInfo     = Context::get('userInfo');

        $this->_check_auth();

        $task_pack_id     = $this->request->post('task_pack_id',0);
        //查找是否存在
        $bao = DsTaskPack::query()->where('task_pack_id',$task_pack_id)->first();
        if(empty($bao))
        {
            return $this->withError('此流量不存在');
        }
        //检查支付密码对不对
        $pay_password = $this->request->post('pay_password','');
        $this->_check_pay_password($pay_password);

        $bao = $bao->toArray();
        if($bao['status'] != 1)
        {
            return $this->withError('未开启购买');
        }
        //判断是否是实名矿机
        if($task_pack_id == 7)
        {
            return $this->withError('体验流量不可兑换！');
        }
        if($task_pack_id > 7)
        {
            return $this->withError('星级流量不可兑换！');
        }
        if($bao['num'] > 0)
        {
            //判断最多持有数量
            $count = DsUserTaskPack::query()->where(['user_id'=>$user_id,'task_pack_id' =>$task_pack_id,'status'=>1 ])->count();
            if($count >= $bao['num'])
            {
                return $this->withError('最多持有数已上限,无法继续兑换');
            }
        }
        //判断是否请求过于频繁
        $this->check_often($user_id);
        //增加请求频繁
        $this->start_often($user_id);

        //判断数量是否足够
        if($userInfo['yingpiao'] < $bao['need'])
        {
            return $this->withError('影票数量不足');
        }
        //租用
        $res = DsUserYingpiao::del_yingpiao($user_id,$bao['need'],'兑换'.$bao['name']);
        if(!$res)
        {
            return $this->withError('当前人数较多,请稍后再试');
        }
        $time = time();
        $data = [
            'task_pack_id' => $task_pack_id,
            'user_id'   =>$user_id,
            'name'      =>$bao['name'],
            'need'      =>$bao['need'],
            'get'       =>$bao['get'],
            'shengyu'   =>$bao['get'],
            'day'       =>$bao['day'],
            'all_get'   =>0,
            'status'    =>1,
            'do_day'    =>0,
            'time'      =>$time,
            'end_time'  =>$time+($bao['all_day']*86400),
            'img'       =>$bao['img'],
            'one_get'   =>round($bao['get']/$bao['day'],$this->xiaoshudian),
            'huoyuezhi' =>$bao['huoyuezhi'],
            'gongxianzhi'=>$bao['gongxianzhi'],
            'yuanyin'   =>'兑换'.$bao['name'],
        ];
        try {
            $res2 = DsUserTaskPack::query()->insert($data);
            if($res2)
            {
//            //更新用户的时间
//            DsUserDatum::query()->where('user_id',$user_id)->update(['last_do_time' => $time]);

                //写入异步任务
                $info = [
                    'type'          => '2',
                    'user_id'       => $user_id,
                    'huoyuezhi'     => $bao['huoyuezhi'],
                    'gongxianzhi'   => $bao['gongxianzhi'],
                    'need'          => $bao['need'],
                    'name'          => '兑换'.$bao['name'],
                    'name2'         => '用户['.$user_id.']兑换'.$bao['name'],
                    'task_pack_id'  => $task_pack_id,
                ];
                $this->yibu($info);

                return $this->withSuccess('成功兑换 '.$bao['name']);
            }else{
                //退回
                DsUserYingpiao::add_yingpiao($user_id,$bao['need'],'退回兑换'.$bao['name']);
                return $this->withError('当前人数较多,请稍后再试');
            }
        }catch (\Exception $exception)
        {
            //退回
            DsUserYingpiao::add_yingpiao($user_id,$bao['need'],'退回兑换'.$bao['name']);
            return $this->withError('当前人数较多,请稍后再试');
        }
    }

    /**
     * 签到
     * @RequestMapping(path="sign_do",methods="post")
     * @RateLimit(create=1, consume=1, capacity=1, key={TaskController::class, "_key"})
     * @return \Psr\Http\Message\ResponseInterface
     * @throws Exception
     */
    public function sign_do()
    {
        $user_id       = Context::get('user_id');
        $userInfo     = Context::get('userInfo');
        //判断是否能领取收益
        $receive_reward_status = $this->redis0->get('receive_reward_status');
        if($receive_reward_status != 1)
        {
            return $this->withError('该功能维护,请耐心等待通知！');
        }

        $this->_check_auth();

        //判断用户今日是否已领取
        $day = date('Y-m-d');
        $name = $user_id.'_'.$day.'_app_renwu';

        $task_day = (int)$this->redis0->get('task_day');
        //判断广告是否完成
        $name1 = $name.'_ci';
        $cont = $this->redis4->get($name1);
        if($cont < $task_day)
        {
            return $this->withError('请先完成任务再来领取！');
        }

        $res = $this->lingqu();
        if($res > 0)
        {
            return $this->withSuccess('成功领取'.$res.'个影票');
        }else{
            return $this->withError('操作繁忙，请稍后再试');
        }
    }

    /**
     * vip摇号
     * @RequestMapping(path="vip_waggle",methods="post")
     * @RateLimit(create=1, consume=1, capacity=1, key={TaskController::class, "_key"})
     * @return \Psr\Http\Message\ResponseInterface
     * @throws Exception
     */
    public function vip_waggle()
    {
        $user_id       = Context::get('user_id');
        $userInfo     = Context::get('userInfo');

        $this->_check_auth();
        //判断是否是会员
        if(!$this->_is_vip($user_id))
        {
            return $this->withError('请先开通会员！');
        }
        //判断是否有心
        if($userInfo['love'] <= 0)
        {
            return $this->withError('尊敬的用户，您没有爱心了，请充满爱心后再来吧');
        }
        $day = date('Y-m-d');
        //判断今日是否摇号
        $yao = $this->redis3->exists($day.'_vip_num_'.$user_id);
        if($yao)
        {
            return $this->withError('今日已摇号！');
        }
        $vip_num = $this->redis0->sRandMember('vip_num');
        $this->redis3->set($day.'_vip_num_'.$user_id,strval($vip_num));
        //判断是否抽中幸运数字
        $data['vip_num'] = $vip_num;
        $data['zhong'] = 0;
        if($this->redis0->sIsMember($day.'_vip_num',$vip_num))
        {
            $data['zhong'] = 1;
        }
        return $this->withResponse('ok',$data);
    }

    /**
     * vip抽中幸运数字签到
     * @RequestMapping(path="vip_sign_do",methods="post")
     * @RateLimit(create=1, consume=1, capacity=1, key={TaskController::class, "_key"})
     * @return \Psr\Http\Message\ResponseInterface
     * @throws Exception
     */
    public function vip_sign_do()
    {
        $user_id       = Context::get('user_id');
        $userInfo     = Context::get('userInfo');

        //判断是否能领取收益
        $receive_reward_status = $this->redis0->get('receive_reward_status');
        if($receive_reward_status != 1)
        {
            return $this->withError('该功能维护,请耐心等待通知！');
        }

        $this->_check_auth();
        //判断是否是会员
        if(!$this->_is_vip($user_id))
        {
            return $this->withError('请先开通会员！');
        }
        $day = date('Y-m-d');

        //判断今日是否摇号
        $yao = $this->redis3->exists($day.'_vip_num_'.$user_id);
        if(!$yao)
        {
            return $this->withError('请先摇号！');
        }
        $vip_num = $this->redis3->get($day.'_vip_num_'.$user_id);
        //判断是否抽中幸运数字
        if(!$this->redis0->sIsMember($day.'_vip_num',$vip_num))
        {
            return $this->withError('您未摇中今日幸运号码！请看广告领取！');
        }

        $res = $this->lingqu();
        if($res > 0)
        {
            return $this->withSuccess('成功领取'.$res.'个影票');
        }else{
            return $this->withError('操作繁忙，请稍后再试');
        }
    }

    /**
     *
     * @return int|mixed
     * @throws Exception
     */
    protected function lingqu()
    {
        $user_id       = Context::get('user_id');
        $userInfo     = Context::get('userInfo');

        //判断用户今日是否已领取
        $day = date('Y-m-d');
        //判断用户今日是否已领取
        $name = $user_id.'_'.$day.'_app_renwu';
        $namesss = $name.'_status';
        $bao = $this->redis4->get($namesss);
        if($bao == 2)
        {
            throw new Exception('今日已领取,请明日再来！', 10001);
        }
        //判断是否有心
        if($userInfo['love'] <= 0)
        {
            throw new Exception('尊敬的用户，您没有爱心了，请充满爱心后再来吧', 10001);
        }
        //查询是否存在
        $info = DsUserTaskPack::query()->where('user_id',$user_id)->where('status',1)->select('user_task_pack_id','task_pack_id','one_get','day','do_day','huoyuezhi','name','get','shengyu','gongxianzhi')->get()->toArray();
        if(empty($info))
        {
            throw new Exception('您没有进行中的流量！请先兑换！', 10001);
        }
        $all_bi = 0;
        foreach ($info as $v)
        {
            //判断时间是否已满
            if($v['do_day']+1 < $v['day'])
            {
                $all_bi += $v['one_get'];
                //天数加1
                DsUserTaskPack::query()->where('user_task_pack_id',$v['user_task_pack_id'])->increment('do_day');
                DsUserTaskPack::query()->where('user_task_pack_id',$v['user_task_pack_id'])->increment('all_get',$v['one_get']);
                DsUserTaskPack::query()->where('user_task_pack_id',$v['user_task_pack_id'])->decrement('shengyu',$v['one_get']);
            }elseif ($v['do_day']+1 == $v['day'])
            {
                $all_bi += $v['shengyu'];
                //修改状态为已完成
                DsUserTaskPack::query()->where('user_task_pack_id',$v['user_task_pack_id'])->update(['status' => 2,'ok_time' => time(),'do_day'=>$v['day'],'shengyu' => 0,'all_get' =>$v['get']  ]);
                //减少算力
                $infos33 = [
                    'type'          => '3',
                    'user_id'       => $user_id,
                    'huoyuezhi'     => $v['huoyuezhi'],
                    'gongxianzhi'   => $v['gongxianzhi'],
                    'name'          => $v['name'].'周期已完成',
                    'name2'         => '用户['.$user_id.']'.$v['name'].'周期已完成',
                    'task_pack_id'  => $v['task_pack_id'],
                ];
                $this->yibu($infos33,10);
            }else{
                DsUserTaskPack::query()->where('user_task_pack_id',$v['user_task_pack_id'])->update(['status' => 2,'ok_time' => time(),'do_day'=>$v['day'],'shengyu' => 0,'all_get' =>$v['get']  ]);
            }
        }
        if($all_bi <= 0)
        {
            throw new Exception('您的账号有误，无法继续签到，请联系客服处理！', 10001);
        }
        $this->redis4->set($namesss,2,86405);
        //更新用户做任务的时间
        DsUserDatum::query()->where('user_id',$user_id)->update(['last_do_time' => time() ]);
        DsUser::query()->where('user_id',$user_id)->decrement('love');
        $reg = DsUserYingpiao::add_yingpiao($user_id,$all_bi,'签到释放');
        if(!$reg)
        {
            //写入错误日志
            DsErrorLog::add_log('签到','增加影票失败',json_encode(['user_id' =>$user_id,'num' => $all_bi,'cont' => '签到释放' ]));
        }
        //增加异步任务
        $infos44 = [
            'type'          => '4',
            'user_id'       => $user_id,
            'all_bi'        => $all_bi,
            'date'          => date('Y-m-d'),
        ];
        $this->yibu($infos44);

        return $all_bi;
    }

    /**
     * 获取我的流量
     * @RequestMapping(path="get_pack",methods="post")
     */
    public function get_pack()
    {
        $user_id      = Context::get('user_id');
        $page = $this->request->post('page',1);
        if($page <= 0)
        {
            $page = 1;
        }
        $data['more']           = '0';
        $data['data']           = [];
        $type = $this->request->post('type',1); //1进行中 2失效
        switch ($type)
        {
            case 1:
                $info = DsUserTaskPack::query()
                    ->where('status',1)
                    ->where('user_id',$user_id)->forPage($page,10)
                    ->select('all_get','day','do_day','get','img','time','end_time')
                    ->get()->toArray();
                break;
            case 2:
                $info = DsUserTaskPack::query()
                    ->where('status',2)
                    ->where('user_id',$user_id)->forPage($page,10)
                    ->select('all_get','day','do_day','get','img','time','end_time')
                    ->get()->toArray();
                break;
        }
        if(!empty($info))
        {
            //查找是否还有下页数据
            if(count($info) >= 10)
            {
                $data['more'] = '1';
            }
            foreach ($info as $k => $v)
            {
                $info[$k]['baifenbi']  = round(100-((($v['get']-$v['all_get'])/$v['get'])*100),0);
                $info[$k]['shengyu'] = $v['day']-$v['do_day'];
                $info[$k]['time']  = $this->replaceTime($v['time']);
                $info[$k]['end_time']  = date("Y-m-d", (int)$v['end_time']);;
                unset($info[$k]['day']);
                unset($info[$k]['do_day']);
                unset($info[$k]['get']);
            }
            $data['data'] = $info;
        }
        return $this->withResponse('获取成功',$data);
    }

    /**
     * 增加广告错误日志
     * @RequestMapping(path="add_ad_error",methods="post")
     */
    public function add_ad_error()
    {
        $user_id      = Context::get('user_id');
        $code = $this->request->post('code','');
        $msg = $this->request->post('msg','');

        DsAdError::add_log($user_id,$code,$msg);

        return $this->withSuccess('ok');
    }

    /**
     * 看广告之前验证环境
     * @RequestMapping(path="check_huanjing",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     * @throws Exception
     */
    public function check_huanjing()
    {
        $userInfo     = Context::get('userInfo');
        //先验证token
        $wy_token   = $this->request->post('wy_token','');
        $wy_token_type   = $this->request->post('wy_token_type',1);
        $yd = make(YDYanZhen::class);
        $data = [
            'user_id'   => $userInfo['user_id'],
            'mobile'    => $userInfo['mobile'],
            'ip'        => Context::get('ip')?Context::get('ip'):'127.0.0.1',
            'business_id'   => $wy_token_type == 1?4:5,
            'reg_ip'   => $userInfo['ip']?$userInfo['ip']:'',
        ];
        $yd->huanjing_verify($wy_token,$data);
        return $this->withSuccess('环境正常');
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