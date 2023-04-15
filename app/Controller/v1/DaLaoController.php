<?php declare(strict_types=1);

namespace App\Controller\v1;


use App\Controller\async\GongXian;
use App\Controller\async\HuoYue;
use App\Controller\async\OrderSn;
use App\Controller\XiaoController;
use App\Job\Register;
use App\Model\DsCash;
use App\Model\DsCity;
use App\Model\DsCode;
use App\Model\DsErrorLog;
use App\Model\DsJiaoyiPm;
use App\Model\DsJiaoyiTz;
use App\Model\DsNotice;
use App\Model\DsPmSh;
use App\Model\DsTaskPack;
use App\Model\DsTzH;
use App\Model\DsUser;
use App\Model\DsUserDatum;
use App\Model\DsUserGroup;
use App\Model\DsUserGroupFh;
use App\Model\DsUserLine;
use App\Model\DsUserPuman;
use App\Model\DsUserTaskPack;
use App\Model\DsUserTongzheng;
use App\Model\DsUserYingpiao;
use App\Service\Pay\Alipay\Pay;
use Hyperf\AsyncQueue\Driver\DriverFactory;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Annotation\Middleware;
use App\Middleware\UpdateMiddleware;
use Hyperf\Utils\ApplicationContext;
use App\Middleware\AsyncMiddleware;
use Swoole\Exception;


/**
 * 大佬接口 自己使用
 * Class CommonController
 * @Middleware(UpdateMiddleware::class)
 * @package App\Controller\v1
 * @Controller(prefix="system")
 */
class DaLaoController extends XiaoController
{

    /**
     * 刷人注册
     * @RequestMapping(path="shua_registration",methods="post")
     * @Middleware(AsyncMiddleware::class)
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Swoole\Exception
     */
    public function shua_registration()
    {
        $mobile     = $this->request->post('mobile','');
        $parent     = $this->request->post('parent',0);  //邀请码
        $line       = $this->request->post('line',0);  //排线码
        $time       = time();
        //验证是否是有效手机号
        if(!$this->isMobile($mobile))
        {
            return $this->withError('请填写有效手机号');
        }

        $password = '666666';
        // 查找用户信息
        $user_id = DsUser::query()->where('mobile',$mobile)->value('user_id');
        if($user_id)
        {
            return $this->withError('此手机号已注册');
        }
        //判断邀请码是否正确
        if(empty($parent))
        {
            return $this->withError('请填写邀请码');
        }
        if($line != 0)
        {
            $line = 1;
        }
        if($this->isMobile($parent))
        {
            $parent_info = DsUser::query()->where('mobile',$parent)->value('user_id');
        }else{
            $parent_info = DsUser::query()->where('user_id',$parent)->value('user_id');
        }
        if(!$parent_info)
        {
            return $this->withError('邀请码不存在 请重新填写');
        }
        //检测上级信息
        $parent_id = $parent_info;
        $pid = $parent_id;
        $num = 1;
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
        // 注册  用户信息入库
        $userDatas = [
            'username'      => $mobile,
            'nickname'      => '今后满座-'.$this->replace_mobile_end($mobile),
            'password'      => password_hash($password,PASSWORD_DEFAULT),
            'mobile'        => $mobile,             //手机
            'pid'           => $pid,                //上级id
            'reg_time'      => $time,               //注册时间
            'avatar'        => 'https://jinhoumanzuo.oss-cn-hangzhou.aliyuncs.com/logo.png', //默认头像
            'pay_password'  => password_hash($password,PASSWORD_DEFAULT),
            'auth'          => 0,
            'last_do_time'  => $time,
        ];
        $user_id2 = DsUser::query()->insertGetId($userDatas);
        if($user_id2)
        {
            if($line == 1)
            {
                $line = [
                    'user_id'   => $parent_id,
                    'son_id'    => $user_id2,
                    'num'       => $num,
                    'time'      => $time,
                ];
                DsUserLine::query()->insert($line);
            }
            //为当前用户绑定上级id
            $this->redis5->set($user_id2.'_pid' ,strval($pid));
            //异步增加上级信息
            $info = [
                'user_id'       => $user_id2,
                'pid'           => $pid,
                'type'          => 1
            ];
            $job = ApplicationContext::getContainer()->get(DriverFactory::class);
            $job->get('register')->push(new Register($info));

            return $this->withResponse('注册成功',[
                'mobile'    => $mobile,
                'user_id'   => $user_id2,
            ]);
        }else{
            return $this->withError('内容填写有误，请重新填写内容');
        }
    }

    /**
     * 实名人
     * @RequestMapping(path="auth",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function auth()
    {
        $user_id = $this->request->post('user_id',0);
        //查询是否存在
        $info = DsUser::query()->where('user_id',$user_id)->select('mobile','auth')->first();
        if(empty($info))
        {
            return $this->withError('没有该用户');
        }
        $info = $info->toArray();
//        if($info['auth'] == 1)
//        {
//            return $this->withError('已被实名！');
//        }
        $res = DsUser::query()->where('user_id',$user_id)->update(['auth' => 1,'auth_name' => '实名','auth_num' => '511111111111123456' ]);
        if($res)
        {
            //异步实名
            $infov = [
                'type'          => '1',
                'user_id'       => $user_id,
            ];
            $this->yibu($infov);
            return $this->withSuccess('操作成功');
        }else{
            return $this->withError('操作过快！');
        }

    }

    /**
     * type     //1矿机 2影票 3通证 4扑满
     * user_id   //用户id  多个已逗号隔开
     * num   //数量
     * desc   //备注
     * @RequestMapping(path="add_gold_integral_dian",methods="post")
     */
    public function add_gold_integral_dian()
    {
        $type     = $this->request->post('type','');
        $user_id  = $this->request->post('user_id','');
        $num      = $this->request->post('num','');
        $desc   = $this->request->post('desc','');
        if(!empty($type) && !empty($user_id) && !empty($num))
        {
            $uids = explode(',',strval($user_id));
            switch ($type)
            {
                case 1:
                    //查询矿机信息
                    $info = DsTaskPack::query()->where('task_pack_id',$desc)->first();
                    if(!empty($info))
                    {
                        $time = time();
                        $bao = $info->toArray();
                        $data = [
                            'task_pack_id' => $desc,
                            'name'          =>$bao['name'],
                            'need'          =>$bao['need'],
                            'get'           =>$bao['get'],
                            'shengyu'       =>$bao['get'],
                            'day'           =>$bao['day'],
                            'status'        =>1,
                            'do_day'        =>0,
                            'time'          =>$time,
                            'end_time'      =>$time+($bao['all_day']*86400),
                            'all_get'       => 0,
                            'img'           => $bao['img'],
                            'one_get'       => round($bao['get']/$bao['day'],$this->xiaoshudian),
                            'huoyuezhi'     => $bao['huoyuezhi'],
                            'gongxianzhi'   =>$bao['gongxianzhi'],
                            'yuanyin'       => '后台增加'.$bao['name'],
                        ];
                        if($num > 1)
                        {
                            for ($i=0;$i<$num;$i++)
                            {
                                foreach ($uids as $v)
                                {
                                    //判断用户今日是否已领取
                                    $day = date('Y-m-d');
                                    $baovvv = $this->redis4->get($v.'_'.$day.'_bao');
                                    if($baovvv == 2)
                                    {
                                        $data['end_time'] = $time+(($bao['day']+1)*86400);
                                    }
                                    $data['user_id'] = $v;
                                    DsUserTaskPack::query()->insert($data);
                                    //更新用户的时间
                                    DsUserDatum::query()->where('user_id',$v)->update(['last_do_time' => $time]);
                                    //写入异步任务
                                    $infos = [
                                        'type'          => '2',
                                        'user_id'       => $v,
                                        'need'          => $bao['need'],
                                        'name'          => '增加'.$bao['name'],
                                        'name2'         => '用户['.$v.']增加'.$bao['name'],
                                        'huoyuezhi'     => $bao['huoyuezhi'],
                                        'gongxianzhi'   => $bao['gongxianzhi'],
                                        'task_pack_id'  => $desc,
                                    ];
                                    $this->yibu($infos);
                                }
                            }
                        }else{
                            foreach ($uids as $v)
                            {
                                $data['user_id'] = $v;
                                DsUserTaskPack::query()->insert($data);
                                //更新用户的时间
                                DsUserDatum::query()->where('user_id',$v)->update(['last_do_time' => $time]);
                                //写入异步任务
                                $infos = [
                                    'type'          => '2',
                                    'user_id'       => $v,
                                    'need'          => $bao['need'],
                                    'name'          => '增加'.$bao['name'],
                                    'name2'         => '用户['.$v.']增加'.$bao['name'],
                                    'huoyuezhi'     => $bao['huoyuezhi'],
                                    'gongxianzhi'   => $bao['gongxianzhi'],
                                    'task_pack_id'  => $desc,
                                ];
                                $this->yibu($infos);
                            }
                        }
                    }
                    break;
                case 2:
                    foreach ($uids as $v)
                    {
                        DsUserYingpiao::add_yingpiao($v,$num,$desc);
                    }
                    break;
                case 3:
                    foreach ($uids as $v)
                    {
                        DsUserTongzheng::add_tongzheng($v,$num,$desc);
                    }
                    break;
                case 4:
                    foreach ($uids as $v)
                    {
                        DsUserPuman::add_puman($v,$num,$desc);
                    }
                    break;
            }
        }
    }

    /**
     * type     //1减少矿机 2影票 3通证 4扑满
     * user_id   //用户id  多个已逗号隔开
     * num   //数量
     * desc   //备注
     * @RequestMapping(path="del_gold_integral_dian",methods="post")
     */
    public function del_gold_integral_dian()
    {
        $type     = $this->request->post('type','');
        $user_id  = $this->request->post('user_id','');
        $num      = $this->request->post('num','');
        $desc     = $this->request->post('desc','');
        if(!empty($type) && !empty($user_id) && !empty($num))
        {
            $uids = explode(',',$user_id);
            switch ($type)
            {
                case 1:
                    foreach ($uids as $v)
                    {
                        $count = DsUserTaskPack::query()->where('status',1)->where(['user_id' =>$v,'task_pack_id'=>$desc ])->count();
                        if($count > 0)
                        {
                            //有我才减少
                            if($count >= $num)
                            {
                                $nn = $num;
                            }else{
                                $nn = $count;
                            }
                            if($nn > 0)
                            {
                                DsUserTaskPack::query()->where(['user_id' =>$v,'task_pack_id'=>$desc ])->orderBy('user_task_pack_id')->limit($nn)->delete();
                                $need = DsTaskPack::query()->where('task_pack_id',$desc)->value('need');
                                $huoyuezhi = DsTaskPack::query()->where('task_pack_id',$desc)->value('huoyuezhi');
                                $gongxianzhi = DsTaskPack::query()->where('task_pack_id',$desc)->value('gongxianzhi');
                                //减少活跃值
                                $infos = [
                                    'type'          => '3',
                                    'user_id'       => $v,
                                    'need'          => $need*$nn,
                                    'name'          => '减少任务包',
                                    'name2'         => '用户['.$v.']减少任务包',
                                    'huoyuezhi'     => $huoyuezhi*$nn,
                                    'gongxianzhi'   => $gongxianzhi*$nn,
                                    'task_pack_id'  => $desc,
                                ];
                                $this->yibu($infos);
                            }
                        }
                    }
                    break;
                case 2:
                    foreach ($uids as $v)
                    {
                        DsUserYingpiao::del_yingpiao($v,$num,$desc);
                    }
                    break;
                case 3:
                    foreach ($uids as $v)
                    {
                        DsUserTongzheng::del_tongzheng($v,$num,$desc);
                    }
                    break;
                case 4:
                    foreach ($uids as $v)
                    {
                        DsUserPuman::del_puman($v,$num,$desc);
                    }
                    break;
            }
        }
    }

    /**
     * 导入注册
     * @RequestMapping(path="import_regist",methods="post")
     * @Middleware(AsyncMiddleware::class)
     */
    public function import_regist()
    {
        $id  = $this->request->post('id',0);  //导入注册表id
        //异步增加上级信息
        $info = [
            'daoru_id'      => $id,
            'type'          => 2
        ];
        $job = ApplicationContext::getContainer()->get(DriverFactory::class);
        $job->get('register')->push(new Register($info));
    }

    /**
     * 顶级
     * @RequestMapping(path="ding_ji",methods="post")
     * @Middleware(AsyncMiddleware::class)
     */
    public function ding_ji()
    {
        $user_id = $this->request->post('user_id',0);
        $pid = $this->request->post('pid',0);
        $mobile = $this->request->post('mobile',0);
        if(!empty($user_id) && !empty($mobile))
        {
            //异步增加上级信息
            $info = [
                'user_id'       => $user_id,
                'pid'           => $pid,
                'mobile'        => $mobile,
                'password'      => '123456',
                'pay_password'  => '123456',
                'type'          => 1
            ];

            $job = ApplicationContext::getContainer()->get(DriverFactory::class);
            $job->get('register')->push(new Register($info));
        }
        return $this->withSuccess('ok');
    }

    /**
     * 开通会员
     * @RequestMapping(path="open_vip",methods="post")
     * @Middleware(AsyncMiddleware::class)
     */
    public function open_vip()
    {
        $user_id = $this->request->post('user_id',0);
        //判断是否有会员
        if($this->_is_vip($user_id))
        {
            DsUser::query()->where('user_id',$user_id)->update(['role_id' => 0,'vip_end_time' => 0]);
        }else{
            Dsuser::pay_vip($user_id, 31, 1);
        }
        return $this->withSuccess('ok');
    }

    /**
     * 达人升级
     * @RequestMapping(path="xingji",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function xingji()
    {
        $user_id       = $this->request->post('user_id');
        if($user_id > 0)
        {
            $group = DsUser::query()->where('user_id',$user_id)->value('group');
            $info = DsUserGroup::query()->where('group',$group+1)->first();
            if(!empty($info))
            {
                $res2 = DsUser::query()->where('user_id',$user_id)->update(['group' => $info['group'] ]);
                if($res2)
                {
                    //设置缓存
                    $this->redis5->set($user_id.'_team_group',$info['group']);
                    //添加到不掉
                    $set_shouma = $this->redis0->get('set_shouma');
                    if(!empty($set_shouma))
                    {
                        $set_shouma = json_decode($set_shouma, true);
                        array_push($set_shouma,$user_id);
                        $this->redis0->set('set_shouma',json_encode($set_shouma));
                    }else{
                        $this->redis0->set('set_shouma',json_encode([$user_id]));
                    }
                    //升级成功 异步增送矿机
                    $infos = [
                        'type'          => '5',
                        'user_id'       => $user_id,
                        'id'            => $info['group_jifen'],
                        'name'          => $info['group_name'],
                        'group'         => $info['group'],
                    ];
                    $this->yibu($infos);
                    return $this->withSuccess('升级成功');
                }
            }
        }
    }

    /**
     * 降级
     * @RequestMapping(path="xingjidi",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function xingjidi()
    {
        $user_id       = $this->request->post('user_id');
        if($user_id > 0)
        {
            $group = DsUser::query()->where('user_id',$user_id)->value('group');
            $info = DsUserGroup::query()->where('group',$group-1)->first();
            if(!empty($info))
            {
                $res2 = DsUser::query()->where('user_id',$user_id)->update(['group' => $info['group'] ]);
                if($res2)
                {
                    //设置缓存
                    $this->redis5->set($user_id.'_team_group',$info['group']);
                    //添加到不掉
                    $set_shouma = $this->redis0->get('set_shouma');
                    if(!empty($set_shouma))
                    {
                        $set_shouma = json_decode($set_shouma, true);
                        array_push($set_shouma,$user_id);
                        $this->redis0->set('set_shouma',json_encode($set_shouma));
                    }else{
                        $this->redis0->set('set_shouma',json_encode([$user_id]));
                    }
                    return $this->withSuccess('升级成功');
                }
            }
        }
    }

    /**
     * 刷号机器人
     * @RequestMapping(path="robot",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Swoole\Exception
     */
    public function robot()
    {
        $robot_id       = $this->request->post('robot_id');
        if($robot_id > 0)
        {
            $rev = DsRobot::query()->where('robot_id',$robot_id)->first();
            if(!empty($rev))
            {
                $info = $rev->toArray();
                $this->redis7->set($info['robot_id'].'_status',1);
                $this->redis7->set($info['robot_id'].'_mobile',$info['mobile']);
                $this->redis7->set($info['robot_id'].'_auth',$info['auth']);
                $this->redis7->set($info['robot_id'].'_line',$info['line']);
                $this->redis7->set($info['robot_id'].'_min_num',$info['min_num']);
                $this->redis7->set($info['robot_id'].'_num',$info['num']);
                $this->redis7->set($info['robot_id'].'_shua_num',$info['shua_num']);
                $infos = [
                    'type'          => '10',
                    'robot_id'      => $info['robot_id'],
                ];
                $this->yibu($infos);
            }
        }
    }

    /**
     * 强制处理订单
     * @RequestMapping(path="order",methods="post")
     */
    public function order()
    {
        $jiaoyi_id = $this->request->post('id');
        $type = $this->request->post('type');
        if($type == 1)
        {
            //通证
            $jiaoyi_info = DsJiaoyiTz::query()->where('jiaoyi_tz_id',$jiaoyi_id)->select('user_id','type','tz_num')->first();
            if(!empty($jiaoyi_info))
            {
                $jiaoyi_info = $jiaoyi_info->toArray();
                if($jiaoyi_info['type'] == 1)
                {
                    $update = [
                        'type'      => 3,
                        'off_time'  => time(),
                    ];
                    //改变交易状态
                    $res2 = DsJiaoyiTz::query()->where('jiaoyi_tz_id',$jiaoyi_id)->update($update);
                    if($res2)
                    {
                        //减少通证上架总额
                        $this->redis0->decrBy('tz_shangjia',(int)$jiaoyi_info['tz_num']);
                        //退回通证
                        $tui = DsUserTongzheng::add_tongzheng($jiaoyi_info['user_id'],$jiaoyi_info['tz_num'],'后台取消');
                        if(!$tui)
                        {
                            DsErrorLog::add_log('取消上架','增加通证失败',json_encode(['user_id' =>$jiaoyi_info['user_id'],'num' =>$jiaoyi_info['tz_num'],'cont' => '后台取消'  ]));
                        }
                    }
                }
            }
        }else{
            //扑满
            $jiaoyi_info = DsJiaoyiPm::query()->where('jiaoyi_pm_id',$jiaoyi_id)->select('user_id','type','pm_num')->first();
            if(!empty($jiaoyi_info))
            {
                $jiaoyi_info = $jiaoyi_info->toArray();
                if($jiaoyi_info['type'] == 1)
                {
                    $update = [
                        'type'      => 3,
                        'off_time'  => time(),
                    ];
                    //改变交易状态
                    $res2 = DsJiaoyiPm::query()->where('jiaoyi_pm_id',$jiaoyi_id)->update($update);
                    if($res2)
                    {
                        //减少扑满上架总额
                        $this->redis0->decrBy('pm_shangjia',(int)$jiaoyi_info['pm_num']);
                        //退回
                        $num = $jiaoyi_info['pm_num'];
                        $erro223 = DsUserPuman::add_puman($jiaoyi_info['user_id'],$num,'后台取消');
                        if(!$erro223)
                        {
                            DsErrorLog::add_log('取消上架','增加扑满失败',json_encode(['user_id' =>$jiaoyi_info['user_id'],'num'=>$num,'cont'=> '后台取消' ]));
                        }
                    }
                }
            }
        }
    }

    /**
     * 影票分红
     * @RequestMapping(path="fenhong",methods="post")
     */
    public function fenhong()
    {
        $day = date('Y-m-d',strtotime("-1 day"));
        $fenhong_status = $this->redis0->get($day.'fenhong_status');
        if($fenhong_status != 2)
        {
            $this->redis0->set($day.'fenhong_status',2,87000);
            //查询昨天所有交易手续费
            $num = (int)$this->redis0->get($day.'_yp_to_tz_shouxu');
            $res = DsUserGroup::query()->where('group','>',0)->select('group_name','group','group_fenhong')->get()->toArray();
            if(!empty($res))
            {
                if($num > 0)
                {
                    foreach ($res as $v)
                    {
                        //查找不同等级下的人数
                        $all_uid = DsUser::query()->where('group',$v['group'])->pluck('user_id')->toArray();
                        if(!empty($all_uid))
                        {
                            $all_uid2 = [];
                            foreach ($all_uid as $vr)
                            {
                                //判断满足条件
                                switch ($v['group'])
                                {
                                    case 1:
                                        //判断用户是否满足
                                        $count = DsUserTaskPack::query()->where('user_id',$vr)->where('status',1)
                                            ->where('task_pack_id',1)->count();
                                        if($count >=2 )
                                        {
                                            $all_uid2[] = $vr;
                                        }
                                        break;
                                    case 2:
                                        //判断用户是否满足
                                        $count = DsUserTaskPack::query()->where('user_id',$vr)->where('status',1)
                                            ->where('task_pack_id',2)->count();
                                        if($count >=2 )
                                        {
                                            $all_uid2[] = $vr;
                                        }
                                        break;
                                    case 3:
                                        //判断用户是否满足
                                        $count = DsUserTaskPack::query()->where('user_id',$vr)->where('status',1)
                                            ->where('task_pack_id',3)->count();
                                        if($count >=2 )
                                        {
                                            $all_uid2[] = $vr;
                                        }
                                        break;
                                    case 4:
                                        //判断用户是否满足
                                        $count = DsUserTaskPack::query()->where('user_id',$vr)->where('status',1)
                                            ->where('task_pack_id',4)->count();
                                        if($count >=2 )
                                        {
                                            $all_uid2[] = $vr;
                                        }
                                        break;
                                    case 5:
                                        //判断用户是否满足
                                        $count = DsUserTaskPack::query()->where('user_id',$vr)->where('status',1)
                                            ->where('task_pack_id',5)->count();
                                        if($count >=1 )
                                        {
                                            $all_uid2[] = $vr;
                                        }
                                        break;
                                    default:
                                        $count = 0;
                                        break;
                                }
                            }
                            if(!empty($all_uid2))
                            {
                                $ren = count($all_uid2);
                                $nu = round($num*$v['group_fenhong']/$ren,$this->xiaoshudian);
                                //写入日志
                                $day = date('Y-m-d',strtotime("-1 day"));
                                $data = [
                                    'group' => $v['group'],
                                    'num'   => $nu,
                                    'day'   => $day,
                                ];
                                DsUserGroupFh::query()->insert($data);
                                //判断一下
                                $this->chuli_fenhong($all_uid2,$nu,$v['group_name'].'分红奖励');
                            }
                        }
                    }
                }
            }
            //排行榜分红
            $paihangbang_yingpiao = $this->redis0->get('paihangbang_yingpiao_fenhong');
            if($paihangbang_yingpiao)
            {
                if($num > 0)
                {
                    $nu = round($num*0.05/10,$this->xiaoshudian);
                    $uarr = [];
                    $paihangbang_yingpiao = json_decode($paihangbang_yingpiao,true);
                    foreach ($paihangbang_yingpiao as $v)
                    {
                        $uarr[] = $v['user_id'];
                    }
                    $this->chuli_fenhong($uarr,$nu,'影票排行榜分红奖励');
                }
            }
            //空投奖励
            $kongtou_ren = $this->redis0->get('kongtou_ren');
            $kongtou_bilie = $this->redis0->get('kongtou_bilie');
            if($kongtou_ren > 0 && $kongtou_bilie > 0)
            {
                if($num > 0)
                {
                    //随机找到满足
                    $arrrr = DsUserDatum::query()
                        ->leftJoin('ds_user','ds_user.user_id','=','ds_user_data.user_id')
                        ->where('ds_user.auth',1)
                        ->where('ds_user_data.zhi_auth_ren','>',2)
                        ->inRandomOrder()->limit($kongtou_ren)->pluck('ds_user_data.user_id')->toArray();
                    if(!empty($arrrr))
                    {
                        $f = round($num*$kongtou_bilie/$kongtou_ren,4);
                        $this->chuli_fenhong($arrrr,$f,'空投奖励');
                        //写入公告
                        $note = $day.'影票空投奖励用户尾号列表：[';
                        $mobiles = DsUser::query()->whereIn('user_id',$arrrr)->pluck('mobile')->toArray();
                        foreach ($mobiles as $vv)
                        {
                            $mo = substr(strval($vv),-4,4);
                            $note.= $mo.',';
                        }
                        $note.= ']';
                        $nodata = [
                            'cont'   => $note,
                            'time'   => time(),
                            'title'  => $day.'影票空投奖励',
                        ];
                        DsNotice::query()->insert($nodata);
                    }
                }
            }

        }
    }

    protected function chuli_fenhong2($user_id,$num,$group,$cont)
    {
        $day = date('Y-m-d');
        foreach ($user_id as $v)
        {
            switch ($group)
            {
                case 1:
                    //判断用户是否满足
                    $count = DsUserTaskPack::query()->where('user_id',$v)->where('status',1)
                        ->wherein('task_pack_id',1)->count();
                break;
                case 2:
                    //判断用户是否满足
                    $count = DsUserTaskPack::query()->where('user_id',$v)->where('status',1)
                        ->wherein('task_pack_id',2)->count();
                    break;
                case 3:
                    //判断用户是否满足
                    $count = DsUserTaskPack::query()->where('user_id',$v)->where('status',1)
                        ->wherein('task_pack_id',3)->count();
                    break;
                case 4:
                    //判断用户是否满足
                    $count = DsUserTaskPack::query()->where('user_id',$v)->where('status',1)
                        ->wherein('task_pack_id',4)->count();
                    break;
                case 5:
                    //判断用户是否满足
                    $count = DsUserTaskPack::query()->where('user_id',$v)->where('status',1)
                        ->wherein('task_pack_id',5)->count();
                    break;
                default:
                    $count = 0;
                    break;
            }
            if($group == 5)
            {
                if($count >= 1)
                {
                    $res = DsUserYingpiao::add_yingpiao($v,$num,$cont);
                    if(!$res)
                    {
                        DsErrorLog::add_log('分红','增加影票失败',json_encode(['user_id' =>$v,'num'=>$num,'cont'=> $cont ]));
                    }
                    //增加今日
                    $this->redis3->incrByFloat($day.$v.'_get_yingpiao',floatval($num));
                    $this->redis3->expire($day.$v.'_get_yingpiao',259200);
                    //增加总
                    $this->redis3->incrByFloat($v.'_get_yingpiao',floatval($num));
                }
            }else{
                if($count >= 2)
                {
                    $res = DsUserYingpiao::add_yingpiao($v,$num,$cont);
                    if(!$res)
                    {
                        DsErrorLog::add_log('分红','增加影票失败',json_encode(['user_id' =>$v,'num'=>$num,'cont'=> $cont ]));
                    }
                    //增加今日
                    $this->redis3->incrByFloat($day.$v.'_get_yingpiao',floatval($num));
                    $this->redis3->expire($day.$v.'_get_yingpiao',259200);
                    //增加总
                    $this->redis3->incrByFloat($v.'_get_yingpiao',floatval($num));
                }
            }
        }
    }

    protected function chuli_fenhong($user_id,$num,$cont)
    {
        $day = date('Y-m-d');
        foreach ($user_id as $v)
        {
            $res = DsUserYingpiao::add_yingpiao($v,$num,$cont);
            if(!$res)
            {
                DsErrorLog::add_log('分红','增加影票失败',json_encode(['user_id' =>$v,'num'=>$num,'cont'=> $cont ]));
            }
            //增加今日
            $this->redis3->incrByFloat($day.$v.'_get_yingpiao',floatval($num));
            $this->redis3->expire($day.$v.'_get_yingpiao',259200);
            //增加总
            $this->redis3->incrByFloat($v.'_get_yingpiao',floatval($num));
        }
    }

    /**
     * 所有退回
     * @RequestMapping(path="tuihui_all",methods="post")
     */
    public function tuihui_all()
    {
        $type = $this->request->post('type',0); //1通证回收退回 2扑满赎回退回
        $id = $this->request->post('id',0);
        if(!$id)
        {
            return $this->withError('id错误');
        }
        switch ($type)
        {
            case 1:
                #通证回收退回
                $res = DsTzH::query()->where('hs_id',$id)->first();
                if(empty($res))
                {
                    return $this->withError('找不到该数据！');
                }
                $res = $res->toArray();
                $rr = DsUserTongzheng::add_tongzheng($res['user_id'],$res['tongzheng'],'退回-通证回收');
                if(!$rr)
                {
                    DsErrorLog::add_log('通证回收','增加通证失败',json_encode(['user_id' =>$res['user_id'],'num'=>$res['tongzheng'],'cont'=> '退回-通证回收' ]));
                }
                break;
            case 2:
                #扑满赎回退回
                $res = DsPmSh::query()->where('sh_id',$id)->first();
                if(empty($res))
                {
                    return $this->withError('找不到该数据！');
                }
                $res = $res->toArray();
                $rr = DsUserPuman::add_puman($res['user_id'],$res['puman'],'退回-扑满赎回和'.$res['shouxu'].'个手续费');
                if(!$rr)
                {
                    DsErrorLog::add_log('扑满赎回','增加扑满失败',json_encode(['user_id' =>$res['user_id'],'num'=>$res['puman'],'cont'=> '退回-扑满赎回和'.$res['shouxu'].'个手续费' ]));
                }
                break;
            default:
                return $this->withError('类型错误');
                break;
        }
        return $this->withSuccess('操作成功');
    }

    /**
     * 支付宝提现
     * @RequestMapping(path="ali_tixian",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function ali_tixian()
    {
        $id = $this->request->post('id',0);
        $admin_id = $this->request->post('admin_id',1);
        $ali_tixian = $this->redis0->get('ali_tixian_'.$id);
        if($ali_tixian != 2)
        {
            $this->redis0->set('ali_tixian_'.$id,2,15);

            $info = DsTzH::query()->where('hs_id',$id)
                ->select('user_id','ali_name','ali_account','tongzheng','type')
                ->first();
            if(!$info)
            {
                return $this->withError('订单不存在！');
            }
            $info = $info->toArray();
            if($info['type'] == 0)
            {
                $time = time();
                //进行支付宝提现
                $od = make(OrderSn::class);
                $orderSn = $od->createOrderSN();
                $money = strval(round($info['tongzheng'] * $this->redis0->get('tz_to_rmb'), 2));
                $payee_real_name = $info['ali_name'];
                $payee_account = $info['ali_account'];
                try {
                    //生成支付宝app订单
                    $data = [
                        'trans_amount' => $money,              //总金额
                        'out_biz_no' => $orderSn,            //商户生成订单号
                        'remark' => "通证回收",            //转账备注
                        'product_code' => 'TRANS_ACCOUNT_NO_PWD',
                        'payee_info' => [
                            'identity' => $payee_account,
                            'identity_type' => 'ALIPAY_LOGON_ID',
                            'name' => $payee_real_name,
                        ],
                        'biz_scene' => 'DIRECT_TRANSFER',
                        'order_title' => '通证回收',
                    ];
                    $pay = make(Pay::class);
                    $pay->UniTransfer($data);
                    DsTzH::query()->where('hs_id',$id)->update(['type' => 1 ]);
                    $cash_data = [
                        'user_id'   => $info['user_id'],
                        'cash_money'   => $money,
                        'cash_money_2'   => $money,
                        'cash_type'   => 1,
                        'payee_account'   => $payee_account,
                        'payee_real_name'   => $payee_real_name,
                        'admin_id'   => $admin_id,
                        'cash_status'   => 2,
                        'order_number'   => $orderSn,
                        'cash_time'   => $time,
                        'cash_chuli_time'   => $time,
                        'cash_bie'  => 1,
                        'cach_day'  => $this->get_day(),
                    ];
                    DsCash::query()->insert($cash_data);
                } catch (\Exception $e) {
                    DsTzH::query()->where('hs_id',$id)->update(['type' => 2 ]);
                    $cash_data = [
                        'user_id'   => $info['user_id'],
                        'cash_money'   => $money,
                        'cash_money_2'   => $money,
                        'cash_type'   => 1,
                        'payee_account'   => $payee_account,
                        'payee_real_name'   => $payee_real_name,
                        'admin_id'   => $admin_id,
                        'cash_status'   => 3,
                        'order_number'   => $orderSn,
                        'cash_time'   => $time,
                        'cash_chuli_time'   => $time,
                        'cash_bie'  => 1,
                        'result_msg'    => $e->getMessage(),
                        'cach_day'  => $this->get_day(),
                    ];

                    DsCash::query()->insert($cash_data);
                    if($cash_data['cash_bie'] == 1)
                    {
                        $rr = DsUserTongzheng::add_tongzheng($info['user_id'],$info['tongzheng'],'退回-通证回收');
                        if(!$rr)
                        {
                            DsErrorLog::add_log('通证回收','增加通证失败',json_encode(['user_id' =>$info['user_id'],'num'=>$info['tongzheng'],'cont'=> '退回-通证回收' ]));
                        }
                    }
                }
            }
        }else{
            return $this->withError('正在处理中！');
        }
    }

    /**
     * 团队禁止登录/开启登录
     * @RequestMapping(path="team_login",methods="post")
     */
    public function team_login()
    {
        $user_id = $this->request->post('user_id',0);
        if(!$user_id)
        {
            return $this->withError('找不到用户');
        }
        //查询用户状态
        $_team_login_status = $this->redis5->get($user_id.'_team_login_status');
        $_team_user = $this->redis6->sMembers($user_id.'_team_user');
        if(!empty($_team_user))
        {
            if($_team_login_status == 2)
            {
                $this->redis5->del($user_id.'_team_login_status');
                //开启
                DsUser::query()->whereIn('user_id',$_team_user)->update(['login_status' =>0 ]);
            }else{
                $this->redis5->set($user_id.'_team_login_status',2);
                //禁止
                DsUser::query()->whereIn('user_id',$_team_user)->update(['login_status' =>1 ]);
            }
        }
        return $this->withSuccess('操作成功');
    }

    /**
     * 直推禁止登录/开启登录
     * @RequestMapping(path="zhi_login",methods="post")
     */
    public function zhi_login()
    {
        $user_id = $this->request->post('user_id',0);
        if(!$user_id)
        {
            return $this->withError('找不到用户');
        }
        //查询用户状态
        $_zhi_login_status = $this->redis5->get($user_id.'_zhi_login_status');
        $_team_user = DsUser::query()->where('pid',$user_id)->pluck('user_id')->toArray();
        if(!empty($_team_user))
        {
            if($_zhi_login_status == 2)
            {
                $this->redis5->del($user_id.'_zhi_login_status');
                //开启
                DsUser::query()->whereIn('user_id',$_team_user)->update(['login_status' =>0 ]);
            }else{
                $this->redis5->set($user_id.'_zhi_login_status',2);
                //禁止
                DsUser::query()->whereIn('user_id',$_team_user)->update(['login_status' =>1 ]);
            }
        }
        return $this->withSuccess('操作成功');
    }
    /**
     * 间推禁止登录/开启登录
     * @RequestMapping(path="jian_login",methods="post")
     */
    public function jian_login()
    {
        $user_id = $this->request->post('user_id',0);
        if(!$user_id)
        {
            return $this->withError('找不到用户');
        }
        $_team_user = DsUser::query()->where('pid',$user_id)->pluck('user_id')->toArray();
        if(!empty($_team_user))
        {
            $us = [];
            foreach ($_team_user as $v)
            {
                $usr = DsUser::query()->where('pid',$v)->pluck('user_id')->toArray();
                if(!empty($usr))
                {
                    foreach ($usr as $v2)
                    {
                        array_push($us,$v2);
                    }
                }
            }
            if(!empty($us))
            {
                //查询用户状态
                $_jian_login_status = $this->redis5->get($user_id.'_jian_login_status');
                if($_jian_login_status == 2)
                {
                    $this->redis5->del($user_id.'_jian_login_status');
                    //开启
                    DsUser::query()->whereIn('user_id',$us)->update(['login_status' =>0 ]);
                }else{
                    $this->redis5->set($user_id.'_jian_login_status',2);
                    //禁止
                    DsUser::query()->whereIn('user_id',$us)->update(['login_status' =>1 ]);
                }
            }
        }
        return $this->withSuccess('操作成功');
    }
    /**
     * 今日禁止登录/开启登录
     * @RequestMapping(path="jinri_login",methods="post")
     */
    public function jinri_login()
    {
        $user_id = $this->request->post('user_id',0);
        if(!$user_id)
        {
            return $this->withError('找不到用户');
        }
        $bt = $this->request->post('bt','');
        if(!$bt)
        {
            return $this->withError('找不到时间');
        }
        $begintime1 = strtotime($bt);//开始时间
        $end_time1 = strtotime(date('Y-m-d 23:59:59', $begintime1));//结束时间

        $_team_user = DsUser::query()
            ->where('pid',$user_id)
            ->where('reg_time','>=',$begintime1)
            ->where('reg_time','<=',$end_time1)
            ->pluck('user_id')->toArray();
        if(!empty($_team_user))
        {
            //查询用户状态
            $_zhi_login_status = $this->redis5->get($user_id . $bt.'_jinri_login_status');

            if($_zhi_login_status == 2)
            {
                $this->redis5->del($user_id . $bt.'_jinri_login_status');
                //开启
                DsUser::query()->whereIn('user_id',$_team_user)->update(['login_status' =>0 ]);
            }else{
                $this->redis5->set($user_id . $bt.'_jinri_login_status',2);
                //禁止
                DsUser::query()->whereIn('user_id',$_team_user)->update(['login_status' =>1 ]);
            }
        }
        return $this->withSuccess('操作成功');
    }

    /**
     * huoyuezhi
     * @RequestMapping(path="huoyuezhi_add",methods="get,post")
     */
    public function huoyuezhi_add()
    {
        $huoyuezhi = $this->request->post('huoyuezhi',0);
        $user_id = $this->request->post('user_id',0);
        if($huoyuezhi > 0 && $user_id)
        {
            $name = '数据调试';
            //增加个人和团队活跃度
            $sl = make(HuoYue::class);
            $sl->add_huoyue($user_id,$huoyuezhi,$name,$name);
            return $this->withSuccess('ok');
        }else{
            return $this->withError('没参数！');
        }
    }

    /**
     * huoyuezhi_del
     * @RequestMapping(path="huoyuezhi_del",methods="get,post")
     */
    public function huoyuezhi_del()
    {
        $huoyuezhi = $this->request->post('huoyuezhi',0);
        $user_id = $this->request->post('user_id',0);
        if($huoyuezhi > 0 && $user_id)
        {
            $name = '数据调试';
            //增加个人和团队活跃度
            $sl = make(HuoYue::class);
            $sl->huoyue_del($user_id,$huoyuezhi,$name,$name);
            return $this->withSuccess('ok');
        }else{
            return $this->withError('没参数！');
        }
    }

    /**
     * gongxianzhi_add
     * @RequestMapping(path="gongxianzhi_add",methods="get,post")
     */
    public function gongxianzhi_add()
    {
        $gongxianzhi = $this->request->post('gongxianzhi',0);
        $user_id = $this->request->post('user_id',0);
        if($gongxianzhi > 0 && $user_id)
        {
            $name = '数据调试';
            //增加个人和团队贡献值
            $gx = make(GongXian::class);
            $gx->add_gongxianzhi($user_id,$gongxianzhi,$name);
            return $this->withSuccess('ok');
        }else{
            return $this->withError('没参数！');
        }
    }

    /**
     * gongxianzhi_del
     * @RequestMapping(path="gongxianzhi_del",methods="get,post")
     */
    public function gongxianzhi_del()
    {
        $gongxianzhi = $this->request->post('gongxianzhi',0);
        $user_id = $this->request->post('user_id',0);
        if($gongxianzhi > 0 && $user_id)
        {
            $name = '数据调试';
            //增加个人和团队贡献值
            $gx = make(GongXian::class);
            $gx->del_gongxianzhi($user_id,$gongxianzhi,$name);
            return $this->withSuccess('ok');
        }else{
            return $this->withError('没参数！');
        }
    }

//    /**
//     * zheyici
//     * @RequestMapping(path="zheyici",methods="get,post")
//     */
//    public function zheyici()
//    {
//        $users = [819921,819205,701923,764982,701924,838801,760,923984,701929,850382,752805,701934,834444,50196,792720,101360,799091,708151,907990,895738,876694,767489,903956,776416,871390,778543,795726,681392,896813,37071,9465,701930,700775,675037,23827,739082,46355,689226,39621,12658,12999,19907,37073,352,42116,16841,45967,42938,95318,701122,910554,800826,76137,44329,792583,940592,968791,824088,903185,670356,895753,786768,43725,714097,116326,678028,689829,797897,771987,699703,40012,34049,960743,706629,1122,661950,790628,901075,806654,687525,714803,822796,848796,785682,37473,673623,51555,691547,750671,44318,953022,661169,677455,696823,36404,733119,66089,54692,858118,873444,785255,798057,886047,866938,802240,759031,722473,722024,660017,151305,681660,57861,73666,139885,55278,148170,12258,59879,695009,937625,61797,702184,53862,52714,691488,688356,691646,693011,773797,803665,828688,55639,772933,760121,740771,694735,24135,715956,894421,873170,862163,816859,169634,779429,731424,685923,708006,137372,43281,49993,11585,53000,691639,977471,711330,898083,895703,26601,865874,71787,692838,387,660067,924975,918606,763376,680221,871613,867269,865097,859635,840677,837156,821967,809519,797309,796689,788301,778891,776113,765566,764302,138649,27856,672079,732455,723807,715625,714569,712669,711641,706678,13322,155445,679439,94517,808,41611,692185,692266,697557,839479,817442,805129,755313,696053,714451,708833,107199,680976,12610,74283,116032,893001,867868,840024,836735,817291,789073,768506,742229,716689,713242,36464,54055,25021,100970,693039,688841,687883,972923,783191,967961,72820,962387,819625,792352,958595,958583,954697,954005,953506,951551,951813,801280,675314,942177,940865,938009,937723,928221,927279,926949,926650,923446,921517,913454,912081,909401,901187,894783,893971,891037,889824,885751,884551,884411,884246,882653,879736,878254,876687,871920,869894,867925,867154,866375,864586,864420,864295,862953,861801,861412,861212,860834,853633,852618,679934,843946,842222,695874,813854,825650,824944,824539,823952,823574,22305,21551,815683,814921,814355,814314,813250,806827,806846,805989,709863,803224,803189,799070,796752,791466,790584,790481,790421,790687,790207,790090,789950,789944,790173,789560,782553,781414,771997,771980,771420,771321,771035,770785,770692,770644,770554,770381,770329,770017,770160,768147,768122,761612,754353,751421,749378,746583,746405,746152,746045,745593,745526,745410,745339,745150,745130,744885,744772,744611,744928,744017,742760,742849,741932,736735,736631,736587,735817,734467,733039,732871,732069,731742,731432,731471,731009,730393,730109,729681,729109,729073,728879,728812,726692,726138,726334,725171,724788,725313,724750,724295,723880,696113,723466,722865,722780,723171,722990,722287,722029,722012,721066,720809,721154,720669,720613,720657,720778,720405,720376,720203,720230,720019,720698,697740,720483,720416,719981,719893,719514,719895,719468,719411,719238,719125,719121,719044,718499,718692,718288,718260,718687,718678,717704,718543,717652,718255,718222,717932,718022,718012,717839,717754,717253,717165,717043,717479,717307,717245,716781,717179,716509,716369,717148,716984,716779,716763,716604,716271,716299,716097,716049,715274,715507,715097,715041,714792,714788,714613,714343,714951,714259,714250,714108,714837,714473,714084,714202,713871,713477,713207,713196,713097,713094,713456,712896,712802,713368,713323,712900,713005,712782,712653,38478,712359,712213,712289,712610,711889,711805,711707,711620,712226,712168,711585,711371,711360,711321,711327,710862,710622,710288,710542,710466,710302,709850,709660,710221,709390,709684,709841,709773,709667,709078,709212,709036,708214,708712,708630,708573,708532,708308,708266,708024,707881,707711,707704,707350,707248,707538,707730,707696,707431,707283,707102,706653,706080,706063,705721,705610,705597,706103,705855,705417,705382,705013,704837,696810,704780,704576,704650,704466,704453,686117,700099,685916,702845,663676,20128,29603,80483,36814,700696,694364,695013,694952,56539,721605,161991,892913,976332,976331,975981,859377,973330,807073,969318,968792,967378,816335,945708,893576,707884,88968,26065,68897,716488,905770,871175,867667,709223,850403,874894,838599,757318,674540,702075,851455,931452,756061,138658,94792,960538,810762,850082,959482,733785,862351,714266,955140,784388,861842,879075,141761,953516,688223,888592,948687,804965,879635,946246,945713,945445,945478,98177,769364,867858,942803,810293,938057,938588,170709,928278,927817,924961,924848,91498,921219,916590,916062,912861,911679,911095,907994,907983,906978,901121,894327,892784,888385,884867,885435,685061,876715,877206,874816,873841,874363,874011,870516,869476,869380,138729,864877,864162,857427,668646,856137,696541,852638,852103,850084,848797,844815,844191,843433,687162,841611,840884,91900,835220,834381,833063,832591,16868,822454,822269,821563,821538,820501,820201,819977,817946,817298,696100,816571,815726,814693,814364,814377,812295,811665,810016,808156,783168,807768,807613,805696,710096,803232,802395,802279,802172,801784,800632,800153,786489,799490,662684,796958,93938,794850,794801,121780,792136,791793,791401,790895,790788,789874,789846,784966,784925,783217,783492,783854,783911,781823,779855,776854,777319,777514,777427,776542,775049,775156,774159,773789,772476,772218,771116,769869,769599,768479,766618,762637,762492,762297,758548,755064,755572,753913,753465,753810,5964,128406,749936,749991,749532,747048,747931,747255,747299,746189,745448,678872,744995,744732,742743,660137,739485,740430,740122,737110,731222,730030,731041,730901,727635,660041,727420,726930,725493,725296,723030,723143,722436,722415,722237,721995,721265,721301,720636,720901,720077,719012,718832,696436,717890,718065,718163,718149,716594,715281,715116,714631,713361,713805,713608,713641,713551,712925,712558,712227,712496,23891,712249,711934,710713,710954,710674,710650,709124,709195,708269,708118,708705,708617,708469,707910,707961,707318,707081,707584,707552,707307,707357,707451,706553,707054,706357,705992,687478,705923,704736,704631,699943,703613,663310,702310,27115,146528,103756,11704,680333,89139,42120,101336,42431,7444,83257,57402,49287,39448,84216,701445,695138,694785,691546,693900,688482,692973,690016,45665,691091,62560,681257,689788,693559,941534,913778,790455,950844,950290,949908,754064,947831,947329,691352,945319,941614,103048,928938,928122,924239,894182,890082,874723,865946,852216,848293,829315,814525,793441,789118,785150,772478,756126,753513,696692,750698,751177,744516,743466,742015,731578,731228,730435,728519,723097,722553,722035,721625,718943,717995,715685,715629,716214,699615,715122,714774,714094,713888,713713,712698,712312,712264,710510,710893,709779,709555,709391,709269,708642,708458,708493,708150,707761,707953,708084,707569,707513,706854,706075,704533,685920,685895,93737,684358,662440,25692,12089,37481,695629,695153,1529];
//        $datas = [842,814,800,750,698,638,637,618,510,502,500,500,482,470,458,455,444,434,418,414,398,330,306,302,278,270,214,205,194,180,151,110,94,93,91,90,84,82,71,70,63,61,58,56,54,50,45,40,39,39,38,38,38,37,35,34,34,34,34,34,33,33,33,32,31,31,31,30,30,30,30,30,28,28,28,27,27,27,27,27,27,26,26,26,26,26,26,26,25,25,24,24,24,23,23,23,23,23,22,22,22,22,22,22,22,22,22,22,22,22,22,22,22,22,22,22,22,22,22,21,21,21,21,21,21,21,21,20,20,20,20,20,20,20,20,20,19,19,19,19,19,19,19,19,19,19,19,19,19,19,19,19,19,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,17,17,17,17,17,17,17,17,17,17,17,17,16,16,16,16,16,16,16,16,16,16,16,16,16,16,16,16,16,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11];
//        foreach ($users as $k => $v)
//        {
//            $huoyuezhi = DsUserTaskPack::query()->where('user_id',$v)->where('status',1)->sum('huoyuezhi');
//            //判断是否等于
//            if($huoyuezhi != $datas[$k])
//            {
//                var_dump('user:'.$v.' huoyue:'.$huoyuezhi.' data:'.$datas[$k]);
//            }
//        }
//        return $this->withSuccess('ok');
//    }
//    /**
//     * do_zheyici
//     * @RequestMapping(path="do_zheyici",methods="get,post")
//     */
//    public function do_zheyici()
//    {
//        $users = [819921,819205,701923,764982,701924,838801,760,923984,701929,850382,752805,701934,834444,50196,792720,101360,799091,708151,907990,895738,876694,767489,903956,776416,871390,778543,795726,681392,896813,37071,9465,701930,700775,675037,23827,739082,46355,689226,39621,12658,12999,19907,37073,352,42116,16841,45967,42938,95318,701122,910554,800826,76137,44329,792583,940592,968791,824088,903185,670356,895753,786768,43725,714097,116326,678028,689829,797897,771987,699703,40012,34049,960743,706629,1122,661950,790628,901075,806654,687525,714803,822796,848796,785682,37473,673623,51555,691547,750671,44318,953022,661169,677455,696823,36404,733119,66089,54692,858118,873444,785255,798057,886047,866938,802240,759031,722473,722024,660017,151305,681660,57861,73666,139885,55278,148170,12258,59879,695009,937625,61797,702184,53862,52714,691488,688356,691646,693011,773797,803665,828688,55639,772933,760121,740771,694735,24135,715956,894421,873170,862163,816859,169634,779429,731424,685923,708006,137372,43281,49993,11585,53000,691639,977471,711330,898083,895703,26601,865874,71787,692838,387,660067,924975,918606,763376,680221,871613,867269,865097,859635,840677,837156,821967,809519,797309,796689,788301,778891,776113,765566,764302,138649,27856,672079,732455,723807,715625,714569,712669,711641,706678,13322,155445,679439,94517,808,41611,692185,692266,697557,839479,817442,805129,755313,696053,714451,708833,107199,680976,12610,74283,116032,893001,867868,840024,836735,817291,789073,768506,742229,716689,713242,36464,54055,25021,100970,693039,688841,687883,972923,783191,967961,72820,962387,819625,792352,958595,958583,954697,954005,953506,951551,951813,801280,675314,942177,940865,938009,937723,928221,927279,926949,926650,923446,921517,913454,912081,909401,901187,894783,893971,891037,889824,885751,884551,884411,884246,882653,879736,878254,876687,871920,869894,867925,867154,866375,864586,864420,864295,862953,861801,861412,861212,860834,853633,852618,679934,843946,842222,695874,813854,825650,824944,824539,823952,823574,22305,21551,815683,814921,814355,814314,813250,806827,806846,805989,709863,803224,803189,799070,796752,791466,790584,790481,790421,790687,790207,790090,789950,789944,790173,789560,782553,781414,771997,771980,771420,771321,771035,770785,770692,770644,770554,770381,770329,770017,770160,768147,768122,761612,754353,751421,749378,746583,746405,746152,746045,745593,745526,745410,745339,745150,745130,744885,744772,744611,744928,744017,742760,742849,741932,736735,736631,736587,735817,734467,733039,732871,732069,731742,731432,731471,731009,730393,730109,729681,729109,729073,728879,728812,726692,726138,726334,725171,724788,725313,724750,724295,723880,696113,723466,722865,722780,723171,722990,722287,722029,722012,721066,720809,721154,720669,720613,720657,720778,720405,720376,720203,720230,720019,720698,697740,720483,720416,719981,719893,719514,719895,719468,719411,719238,719125,719121,719044,718499,718692,718288,718260,718687,718678,717704,718543,717652,718255,718222,717932,718022,718012,717839,717754,717253,717165,717043,717479,717307,717245,716781,717179,716509,716369,717148,716984,716779,716763,716604,716271,716299,716097,716049,715274,715507,715097,715041,714792,714788,714613,714343,714951,714259,714250,714108,714837,714473,714084,714202,713871,713477,713207,713196,713097,713094,713456,712896,712802,713368,713323,712900,713005,712782,712653,38478,712359,712213,712289,712610,711889,711805,711707,711620,712226,712168,711585,711371,711360,711321,711327,710862,710622,710288,710542,710466,710302,709850,709660,710221,709390,709684,709841,709773,709667,709078,709212,709036,708214,708712,708630,708573,708532,708308,708266,708024,707881,707711,707704,707350,707248,707538,707730,707696,707431,707283,707102,706653,706080,706063,705721,705610,705597,706103,705855,705417,705382,705013,704837,696810,704780,704576,704650,704466,704453,686117,700099,685916,702845,663676,20128,29603,80483,36814,700696,694364,695013,694952,56539,721605,161991,892913,976332,976331,975981,859377,973330,807073,969318,968792,967378,816335,945708,893576,707884,88968,26065,68897,716488,905770,871175,867667,709223,850403,874894,838599,757318,674540,702075,851455,931452,756061,138658,94792,960538,810762,850082,959482,733785,862351,714266,955140,784388,861842,879075,141761,953516,688223,888592,948687,804965,879635,946246,945713,945445,945478,98177,769364,867858,942803,810293,938057,938588,170709,928278,927817,924961,924848,91498,921219,916590,916062,912861,911679,911095,907994,907983,906978,901121,894327,892784,888385,884867,885435,685061,876715,877206,874816,873841,874363,874011,870516,869476,869380,138729,864877,864162,857427,668646,856137,696541,852638,852103,850084,848797,844815,844191,843433,687162,841611,840884,91900,835220,834381,833063,832591,16868,822454,822269,821563,821538,820501,820201,819977,817946,817298,696100,816571,815726,814693,814364,814377,812295,811665,810016,808156,783168,807768,807613,805696,710096,803232,802395,802279,802172,801784,800632,800153,786489,799490,662684,796958,93938,794850,794801,121780,792136,791793,791401,790895,790788,789874,789846,784966,784925,783217,783492,783854,783911,781823,779855,776854,777319,777514,777427,776542,775049,775156,774159,773789,772476,772218,771116,769869,769599,768479,766618,762637,762492,762297,758548,755064,755572,753913,753465,753810,5964,128406,749936,749991,749532,747048,747931,747255,747299,746189,745448,678872,744995,744732,742743,660137,739485,740430,740122,737110,731222,730030,731041,730901,727635,660041,727420,726930,725493,725296,723030,723143,722436,722415,722237,721995,721265,721301,720636,720901,720077,719012,718832,696436,717890,718065,718163,718149,716594,715281,715116,714631,713361,713805,713608,713641,713551,712925,712558,712227,712496,23891,712249,711934,710713,710954,710674,710650,709124,709195,708269,708118,708705,708617,708469,707910,707961,707318,707081,707584,707552,707307,707357,707451,706553,707054,706357,705992,687478,705923,704736,704631,699943,703613,663310,702310,27115,146528,103756,11704,680333,89139,42120,101336,42431,7444,83257,57402,49287,39448,84216,701445,695138,694785,691546,693900,688482,692973,690016,45665,691091,62560,681257,689788,693559,941534,913778,790455,950844,950290,949908,754064,947831,947329,691352,945319,941614,103048,928938,928122,924239,894182,890082,874723,865946,852216,848293,829315,814525,793441,789118,785150,772478,756126,753513,696692,750698,751177,744516,743466,742015,731578,731228,730435,728519,723097,722553,722035,721625,718943,717995,715685,715629,716214,699615,715122,714774,714094,713888,713713,712698,712312,712264,710510,710893,709779,709555,709391,709269,708642,708458,708493,708150,707761,707953,708084,707569,707513,706854,706075,704533,685920,685895,93737,684358,662440,25692,12089,37481,695629,695153,1529];
//        $datas = [842,814,800,750,698,638,637,618,510,502,500,500,482,470,458,455,444,434,418,414,398,330,306,302,278,270,214,205,194,180,151,110,94,93,91,90,84,82,71,70,63,61,58,56,54,50,45,40,39,39,38,38,38,37,35,34,34,34,34,34,33,33,33,32,31,31,31,30,30,30,30,30,28,28,28,27,27,27,27,27,27,26,26,26,26,26,26,26,25,25,24,24,24,23,23,23,23,23,22,22,22,22,22,22,22,22,22,22,22,22,22,22,22,22,22,22,22,22,22,21,21,21,21,21,21,21,21,20,20,20,20,20,20,20,20,20,19,19,19,19,19,19,19,19,19,19,19,19,19,19,19,19,19,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,18,17,17,17,17,17,17,17,17,17,17,17,17,16,16,16,16,16,16,16,16,16,16,16,16,16,16,16,16,16,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,15,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,14,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11,11];
//        //增加个人和团队活跃度
//        $sl = make(HuoYue::class);
//        foreach ($users as $k => $v)
//        {
//            $huoyuezhi = DsUserTaskPack::query()->where('user_id',$v)->where('status',1)->sum('huoyuezhi');
//            //判断是否等于
//            if($huoyuezhi != $datas[$k])
//            {
//                $this->redis4->set($v.'_huoyue_dj',(int)$huoyuezhi);
//                $duo = $datas[$k] - $huoyuezhi;
//                $name = '数据调试';
//                $sl->add_huoyue($v,$duo,$name,$name);
//            }
//        }
//        return $this->withSuccess('ok');
//    }
}