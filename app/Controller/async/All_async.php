<?php declare(strict_types=1);

namespace App\Controller\async;


use App\Controller\ChatIm;
use App\Job\Async;
use App\Job\QrOk;
use App\Job\Register;
use App\Model\DsCinema;
use App\Model\DsCinemaPaiqi;
use App\Model\DsCity;
use App\Model\DsErrorLog;
use App\Model\DsOrder;
use App\Model\DsPmCb;
use App\Model\DsTaskPack;
use App\Model\DsUser;
use App\Model\DsUserDatum;
use App\Model\DsUserGroup;
use App\Model\DsUserGroupLog;
use App\Model\DsUserHuoyuedu;
use App\Model\DsUserLine;
use App\Model\DsUserPuman;
use App\Model\DsUserRenlianLog;
use App\Model\DsUserTaskPack;
use App\Model\DsUserTongzheng;
use App\Model\DsUserYingpiao;
use App\Service\Pay\Alipay\Pay;
use Hyperf\AsyncQueue\Driver\DriverFactory;
use Hyperf\DbConnection\Db;
use Hyperf\Guzzle\ClientFactory;
use Hyperf\Redis\RedisFactory;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Context;
use Swlib\SaberGM;
use Swoole\Exception;
use function AlibabaCloud\Client\json;

/**
 * 所有的异步方法
 * Class All_async
 * @package App\Controller\async
 */
class All_async
{
    protected $redis0;
    protected $redis5;
    protected $redis6;
    protected $redis4;
    protected $redis3;
    protected $redis7;
    protected $redis12;
    protected $user_id;
    protected $suanli;
    protected $http;
    protected $xiaoshudian;
    public function __construct()
    {
        $this->redis0 = ApplicationContext::getContainer()->get(RedisFactory::class)->get('db0');
        $this->redis3 = ApplicationContext::getContainer()->get(RedisFactory::class)->get('db3');
        $this->redis4 = ApplicationContext::getContainer()->get(RedisFactory::class)->get('db4');
        $this->redis5 = ApplicationContext::getContainer()->get(RedisFactory::class)->get('db5');
        $this->redis6 = ApplicationContext::getContainer()->get(RedisFactory::class)->get('db6');
        $this->redis7 = ApplicationContext::getContainer()->get(RedisFactory::class)->get('db7');
        $this->redis12 = ApplicationContext::getContainer()->get(RedisFactory::class)->get('db12');
        $this->http   = ApplicationContext::getContainer()->get(ClientFactory::class)->create();
        $this->xiaoshudian = 4;
    }

    /**
     * 处理
     * @param $info
     * @throws \Exception
     */
    public function execdo($info)
    {
        switch ($info['type'])
        {
            case '1':
                #用户实名
                $this->user_id = strval($info['user_id']);

                //判断用户是否实名 以免重复操作
                $sdasd = $this->redis5->get($this->user_id.'_auth');
                if($sdasd == 1)
                {
                    //不重复操作
                    return;
                }
                //修改状态
                $this->redis5->set($this->user_id.'_auth' ,1);
                //实名人数加1
                $this->redis0->incr('real_ren');
                $day = date('Y-m-d');
                $this->redis0->incr($day.'real_ren');
                $pid = $this->redis5->get($this->user_id.'_pid');
                if($pid > 0)
                {
                    //为上级增加直推实名人数
                    $this->redis5->incr($pid.'_zhi_user_real');
                    //增加
                    DsUserDatum::add_log($pid, 1, 4);
                    $ppid = $this->redis5->get($pid.'_pid');
                    if($ppid > 0)
                    {
                        //为上上级增加间推实名人数
                        $this->redis5->incr($ppid.'_jian_user_real');
                    }
                    //实名后为所有上级团队实名
                    $this->shiming($this->user_id);
                }
                break;
            case '2':
                #租用矿机 增加某个矿机的活跃值
                $user_id  = $info['user_id'];
                $name   = $info['name'];
                $name2   = $info['name2'];
                $huoyuezhi  = (int)$info['huoyuezhi'];
                $gongxianzhi  = (int)$info['gongxianzhi'];
                if($huoyuezhi > 0)
                {
                    //增加个人和团队活跃度
                    $sl = make(HuoYue::class);
                    $sl->add_huoyue($user_id,$huoyuezhi,$name,$name2);
                }
                if($gongxianzhi > 0)
                {
                    //增加个人和团队贡献值
                    $gx = make(GongXian::class);
                    $gx->add_gongxianzhi($user_id,$gongxianzhi,$name);
                }
                break;
            case '3':
                #减少某个矿机的活跃值
                $user_id  = $info['user_id'];
                $name   = $info['name'];
                $name2   = $info['name2'];
                $huoyuezhi  = (int)$info['huoyuezhi'];
                $gongxianzhi  = (int)$info['gongxianzhi'];
                if($gongxianzhi > 0)
                {
                    //减少个人和团队贡献值
                    $gx = make(GongXian::class);
                    $gx->del_gongxianzhi($user_id,$gongxianzhi,$name);
                }
                //减少个人活跃值
                if($huoyuezhi > 0)
                {
                    //查询用户是否有冻结的活跃度
                    $huoyue_dj = $this->redis4->get($user_id.'_huoyue_dj');
                    if(!$huoyue_dj)
                    {
                        $sl = make(HuoYue::class);
                        //减少团队活跃
                        $sl->huoyue_del($user_id,$huoyuezhi,$name,$name2);
                    }else{
                        //减少冻结的活跃值
                        $this->redis4->decrBy($user_id.'_huoyue_dj',(int)$huoyuezhi);
                        //增加日志
                        DsUserHuoyuedu::add_log($user_id,2,$huoyuezhi,$name);
                        $sl = make(HuoYue::class);
                        //减少团队活跃
                        $sl->huoyue_del_2($user_id,$huoyuezhi,$name,$name2);
                    }
                }
                break;
            case '4':
                #领取收益
                $user_id = $info['user_id'];

                $day = $info['date'];
                $all_bi = $info['all_bi'];
                //增加今日有效活跃用户
                $this->redis0->sAdd($day.'youxiao_user',$user_id);
                //增加今日
                $this->redis3->incrByFloat($day.$user_id.'_get_yingpiao',floatval($all_bi));
                $this->redis3->expire($day.$user_id.'_get_yingpiao',259200);
                //增加总
                $this->redis3->incrByFloat($user_id.'_get_yingpiao',floatval($all_bi));

                //增加直推4%
                $pid = $this->redis5->get($user_id.'_pid');
                if($pid > 0)
                {
                    $bi1 = round($all_bi*0.04,4);
                    $arr1 = DsUserYingpiao::add_yingpiao($pid,$bi1,'['.$user_id.']直推收益');
                    if(!$arr1)
                    {
                        //写入错误日志
                        DsErrorLog::add_log('返佣','增加影票失败',json_encode(['user_id' =>$pid,'num' => $bi1,'cont' => '['.$user_id.']直推收益' ]));
                    }
                    //增加今日
                    $this->redis3->incrByFloat($day.$pid.'_get_yingpiao',floatval($bi1));
                    $this->redis3->expire($day.$pid.'_get_yingpiao',259200);
                    //增加总
                    $this->redis3->incrByFloat($pid.'_get_yingpiao',floatval($bi1));
                    //增加间推1%
                    $ppid = $this->redis5->get($pid.'_pid');
                    if($ppid > 0)
                    {
                        $bi2 = round($all_bi*0.01,4);
                        $arr2 = DsUserYingpiao::add_yingpiao($ppid,$bi2,'['.$user_id.']间推收益');
                        if(!$arr2)
                        {
                            //写入错误日志
                            DsErrorLog::add_log('返佣','增加影票失败',json_encode(['user_id' =>$ppid,'num' => $bi2,'cont' => '['.$user_id.']间推收益' ]));
                        }
                        //增加今日
                        $this->redis3->incrByFloat($day.$ppid.'_get_yingpiao',floatval($bi2));
                        $this->redis3->expire($day.$ppid.'_get_yingpiao',259200);
                        //增加总
                        $this->redis3->incrByFloat($ppid.'_get_yingpiao',floatval($bi2));
                    }
                }
                //解除冻结
                $infovvv = [
                    'type'          => '12',
                    'user_id'       => $user_id,
                ];
                $this->execdo($infovvv);
                break;
            case '5':
                #升级达人
                $user_id  = $info['user_id'];
                $group  = $info['group'];
                $id  = $info['id'];
                $name  = $info['name'];
                //判断是否已领取过
                $da = $this->redis4->get($user_id.'_daren_'.$group);
                if($da != 2)
                {
                    //增加领取
                    $this->redis4->set($user_id.'_daren_'.$group,2);
                    //赠送矿机
                    $this->add_fulibao($user_id,$id,'升级'.$name.'赠送','用户['.$user_id.']升级'.$name.'赠送');
                    if($group == 3)
                    {
                        //送3次
                        $this->add_fulibao($user_id,$id,'升级'.$name.'赠送','用户['.$user_id.']升级'.$name.'赠送');
                        if($group == 3)
                        {
                            $this->add_fulibao($user_id,$id,'升级'.$name.'赠送','用户['.$user_id.']升级'.$name.'赠送');
                        }
                    }
                }
                //增加无限代上级指定等级的人数
                $tt = make(Team::class);
                $tt->add_team_group($user_id,$group);
                //减少
                if($group -1 > 0)
                {
                    $tt->del_team_group($user_id,$group-1);
                }
                break;
            case '6':
                #自动检测用户是否能升等级
                $user_id = $info['user_id'];
                $check_shengji = $this->redis3->get($user_id.'check_shengji');
                if($check_shengji != 2)
                {
                    $this->redis3->set($user_id.'check_shengji',2,150);
                    $this->check_update($user_id);
                }
                break;
            case '7':
                #自动检测出用户是否会降低等级
                $user_id = $info['user_id'];
                $check_diaoji = $this->redis3->get($user_id.'check_diaoji');
                if($check_diaoji != 2)
                {
                    $this->redis3->set($user_id.'check_diaoji',2,150);
                    $this->check_diaoji($user_id);
                }
                break;
            case '8':
                #降级达人
                $user_id = $info['user_id'];
                $group = $info['group'];
                //减少无限代上级指定等级的人数
                $tt = make(Team::class);
                $tt->del_team_group($user_id,$group);
                //增加指定等级人数
                if($group-1 > 0)
                {
                    $tt->add_team_group($user_id,$group-1);
                }
                break;
            case '9':
                //实名认证处理
                $card = $info['shenfenzheng_num'];
                $user_id = $info['user_id'];
                //判断身份证信息是否重复
                $chongfu = DsUser::query()
                    ->where('auth_num',$card)
                    ->where('auth',1)
                    ->value('auth_num');
                if(!$chongfu)
                {
                    //判断id是否重复
                    $CertifyId = $info['CertifyId'];
                    $sd = $this->redis3->sIsMember('auth_all_CertifyId',$CertifyId);
                    if(!$sd)
                    {
                        $auth_num = DsUser::query()->where('user_id',$user_id)->value('auth_num');
                        if(empty($auth_num))
                        {
                            //记录错误日志
                            DsErrorLog::add_log('实名认证没有用户信息','实名认证没有用户信息',json_encode(['CertifyId' =>$info['CertifyId'],'user_id' =>$user_id  ]));
                            return;
                        }else{
                            //认证成功
                            $re = DsUser::query()->where('user_id',$user_id)->update(['auth' => 1]);
                            if($re)
                            {
                                $this->redis3->sAdd('auth_all_CertifyId',$CertifyId);
                                $this->redis3->del($user_id.'_CertifyId');
                                //异步实名
                                $infovvv = [
                                    'type'          => '1',
                                    'user_id'       => $user_id,
                                ];
                                $this->execdo($infovvv);
                            }
                        }
                    }else{
                        DsUser::query()->where('user_id',$user_id)->update(['auth' => 2,'auth_error' => '此人脸信息已被实名！请重新扫描人脸认证！']);
                        return;
                    }
                }else{
                    DsUser::query()->where('user_id',$user_id)->update(['auth' => 2,'auth_error' => '此身份证信息已被实名！请更换身份信息！']);
                    return;
                }
                break;
            case '10':
                //刷人机器人
                $robot_id = $info['robot_id'];
                $status = $this->redis7->get($robot_id.'_status');
                if($status == 1)
                {
                    //开始 判断刷的人数是否等于总人数
                    $_shua_num = $this->redis7->get($info['robot_id'].'_shua_num');
                    $_num = $this->redis7->get($info['robot_id'].'_num');
                    if($_shua_num >= $_num)
                    {
                        //修改状态为停止
                        $this->redis7->set($robot_id.'_status',0);
                        DsRobot::query()->where('robot_id',$robot_id)->update(['status' => 0]);
                    }
                    //开始刷号
                    $mobile = $this->redis7->get($robot_id.'_mobile');
                    //随机生成手机号
                    $sheng = '144' . mt_rand(11111111, 99999999);
                    $line = $this->redis7->get($robot_id.'_line');
                    if($line)
                    {
                        $res = $this->jiqi_zhuce($sheng,$mobile,1);
                    }else{
                        $res = $this->jiqi_zhuce($sheng,$mobile,0);
                    }
                    if(!empty($res['user_id']))
                    {
                        //增加刷人次数
                        $this->redis7->incr($robot_id.'_shua_num');
                        DsRobot::query()->where('robot_id',$robot_id)->increment('shua_num');
                        //判断是否要实名
                        $real = $this->redis7->get($robot_id.'_auth');
                        if($real)
                        {
                            $resv = DsUser::query()->where('user_id',$res['user_id'])->update(['auth' => 1]);
                            if($resv)
                            {
                                $this->redis7->incr($robot_id.'_auth_num');
                                DsRobot::query()->where('robot_id',$robot_id)->increment('auth_num');
                                //异步实名
                                $infoj = [
                                    'type'          => '1',
                                    'user_id'       => $res['user_id'],
                                ];
                                $this->yibu2($infoj);
                            }
                        }
                    }
                    //无线重复
                    $infos = [
                        'type'          => '10',
                        'robot_id'      => $robot_id,
                    ];
//                    $_min_num = $this->redis7->get($robot_id.'_min_num');
                    //计算多少秒一次
//                    $ti = 60/$_min_num;
//                    $ti2 = 40/$_min_num;
//                    if($ti <=0)
//                    {
//                        $ti = 1;
//                    }
//                    if($ti2 <=0)
//                    {
//                        $ti2 = 1;
//                    }
//                    $tt = mt_rand((int)$ti2,(int)$ti);
//                    $this->yibu2($infos,mt_rand(1,3));
                    $this->yibu2($infos);
                }
                break;
            case '11':
                $user_id = $info['user_id'];
                #冻结用户的所有贡献值和活跃度
                $gongxianzhi = $this->redis5->get($user_id.'_gongxianzhi');
                $day_diao_hyd_gxz = $this->redis0->get('day_diao_hyd_gxz');
                $cont = $day_diao_hyd_gxz.'天内未做任务冻结';
                if($gongxianzhi > 0)
                {
                    $this->redis4->set($user_id.'_gongxianzhi_dj' ,(int)$gongxianzhi);
                    //减少个人和团队贡献值
                    $gx = make(GongXian::class);
                    $gx->del_gongxianzhi($user_id,$gongxianzhi,$cont);
                }
                //查询用户活跃度
                $huoyue = DsUserTaskPack::query()->where('user_id',$user_id)->where('status',1)->sum('huoyuezhi');
//                $huoyue = $this->redis5->get($user_id.'_huoyue');
                if($huoyue > 0)
                {
                    $this->redis4->set($user_id.'_huoyue_dj' ,(int)$huoyue);
                    //减少团队活跃值
                    $sl = make(HuoYue::class);
                    $sl->huoyue_del($user_id,$huoyue,$cont,'用户['.$user_id.']'.$cont);
                }
                break;
            case '12':
                $user_id = $info['user_id'];
                $rr = DsUserDatum::query()->where('user_id',$user_id)->select('data_id','dj')->first();
                if(!empty($rr))
                {
                    $rr = $rr->toArray();
                    if($rr['dj'] == 1)
                    {
                        $rr2 = DsUserDatum::query()->where('user_id',$user_id)->update(['dj' => 0]);
                        if($rr2)
                        {
                            #解冻用户的所有贡献值和活跃度
                            $cont = '完成任务解除冻结';
                            $gongxianzhi = $this->redis4->get($user_id.'_gongxianzhi_dj');
                            if($gongxianzhi > 0)
                            {
                                $this->redis4->del($user_id.'_gongxianzhi_dj');
                                //增加个人和团队贡献值
                                $gx = make(GongXian::class);
                                $gx->add_gongxianzhi($user_id,$gongxianzhi,$cont);
                            }
                            $huoyue = $this->redis4->get($user_id.'_huoyue_dj');
                            if($huoyue > 0)
                            {
                                $this->redis4->del($user_id.'_huoyue_dj');
                                //增加个人和团队活跃度
                                $sl = make(HuoYue::class);
                                $sl->add_huoyue($user_id,$huoyue,$cont,'用户['.$user_id.']'.$cont);
                            }
                        }
                    }
                }
                break;
            case '13':
                break;
            case '14':
                #通证交易成功
                $jiaoyi_info = $info['jiaoyi_info'];
                var_dump($jiaoyi_info['user_id']);
                var_dump($jiaoyi_info['to_id']);
                $user_id = $jiaoyi_info['user_id'];
                $to_id = $jiaoyi_info['to_id'];
                //为买家增加扑满
                $rr1 = DsUserPuman::add_puman($jiaoyi_info['user_id'],$jiaoyi_info['pm_num'],'交易完成-用户 ['.$to_id.'] '.$jiaoyi_info['tz_num'].'个通证置换');
                if(!$rr1)
                {
                    DsErrorLog::add_log('交易完成','增加扑满失败',json_encode(['user_id' =>$jiaoyi_info['user_id'],'num'=>$jiaoyi_info['pm_num'],'cont'=> '交易完成-用户 ['.$to_id.'] '.$jiaoyi_info['tz_num'].'个通证置换' ]));
                }
                //为卖家增加通证
                $rr2 = DsUserTongzheng::add_tongzheng($jiaoyi_info['to_id'],$jiaoyi_info['tz_num'],'交易完成-用户 ['.$user_id.'] '.$jiaoyi_info['pm_num'].'个扑满置换');
                if(!$rr2)
                {
                    DsErrorLog::add_log('交易完成','增加通证失败',json_encode(['user_id' =>$jiaoyi_info['to_id'],'num'=>$jiaoyi_info['tz_num'],'cont'=> '交易完成-用户 ['.$user_id.'] '.$jiaoyi_info['pm_num'].'个扑满置换' ]));
                }
                //减少通证上架总额
                $this->redis0->decrBy('tz_shangjia',(int)$jiaoyi_info['tz_num']);
                $day = date('Y-m-d');
                //增加今日交易总笔数
                $this->redis0->incr($day.'_jiaoyi_ok_num');
                //增加今日通证交易数量
                $this->redis0->incrBy($day.'_tz_jiaoyi_ok',(int)$jiaoyi_info['tz_num']);
                $this->redis0->incrBy($day.'_pm_jiaoyi_ok',(int)$jiaoyi_info['pm_num']);
//                //交易中心今日指导比例公式：   今日完成置换的通证总量➗今日完成置换的扑满总量
//                $tz = $this->redis0->get($day.'_tz_jiaoyi_ok');
//                $pm = $this->redis0->get($day.'_pm_jiaoyi_ok');
//                $zhidaojia = round($tz/$pm,2);
//                $this->redis0->set($day.'_zhidaojia',strval($zhidaojia));
                break;
            case '15':
                #扑满交易成功
                $jiaoyi_info = $info['jiaoyi_info'];
                //增加扑满
                $rr2 = DsUserPuman::add_puman($jiaoyi_info['to_id'],$jiaoyi_info['pm_num'],'交易完成-用户 ['.$jiaoyi_info['user_id'].'] '.$jiaoyi_info['tz_num'].'个通证置换');
                if(!$rr2)
                {
                    DsErrorLog::add_log('交易完成','增加扑满失败',json_encode(['user_id' =>$jiaoyi_info['to_id'],'num'=>$jiaoyi_info['pm_num'],'cont'=> '交易完成-用户 ['.$jiaoyi_info['user_id'].'] '.$jiaoyi_info['tz_num'].'个通证置换']));
                }
                //增加通证
                $rr2 = DsUserTongzheng::add_tongzheng($jiaoyi_info['user_id'],$jiaoyi_info['tz_num'],'交易完成-用户 ['.$jiaoyi_info['to_id'].'] '.$jiaoyi_info['pm_num'].'个扑满置换');
                if(!$rr2)
                {
                    DsErrorLog::add_log('交易完成','增加通证失败',json_encode(['user_id' =>$jiaoyi_info['user_id'],'num'=>$jiaoyi_info['tz_num'],'cont'=> '交易完成-用户 ['.$jiaoyi_info['to_id'].'] '.$jiaoyi_info['pm_num'].'个扑满置换' ]));
                }
                //减少扑满上架总额
                $this->redis0->decrBy('pm_shangjia',(int)$jiaoyi_info['pm_num']);
                $day = date('Y-m-d');
                //增加今日交易总笔数
                $this->redis0->incr($day.'_jiaoyi_ok_num');
                //增加今日扑满交易数量
                $this->redis0->incrBy($day.'_tz_jiaoyi_ok',(int)$jiaoyi_info['tz_num']);
                $this->redis0->incrBy($day.'_pm_jiaoyi_ok',(int)$jiaoyi_info['pm_num']);
//                //交易中心今日指导比例公式：   今日完成置换的通证总量➗今日完成置换的扑满总量
//                $tz = $this->redis0->get($day.'_tz_jiaoyi_ok');
//                $pm = $this->redis0->get($day.'_pm_jiaoyi_ok');
//                $zhidaojia = round($tz/$pm,2);
//                $this->redis0->set($day.'_zhidaojia',strval($zhidaojia));
                break;
            case '16':
                //扑满储备兑换
                $user_id  = $info['user_id'];
                $puman      = $info['puman'];
                $rr1 = DsUserPuman::add_puman($user_id,$puman,'储备兑换');
                if(!$rr1)
                {
                    DsErrorLog::add_log('储备兑换','增加扑满失败',json_encode(['user_id' =>$user_id,'num'=>$puman,'cont'=> '储备兑换' ]));
                }
                break;
            case '17':
                //领取实名认证的包
                $user_id = $info['user_id'];
                $_auth_bao = $this->redis5->get($user_id.'_auth_bao');
                if($_auth_bao == 2)
                {
                    //不重复操作
                    return;
                }
                //修改状态
                $this->redis5->set($user_id.'_auth_bao' ,2);
                //赠送实名认证的包
                $this->add_fulibao($user_id,7,'手动领取体验流量','用户['.$user_id.']手动领取体验流量');
                break;
            case '18':
                //15分钟取消订单
                $id = $info['id'];
                $resss = DsPmCb::query()->where('pm_cb_id',$id)
                    ->select('user_id','status')
                    ->first()->toArray();
                if($resss['status'] == 0)
                {
                    $update = [
                        'status'    => 3,
                        'use_time'  => time(),
                    ];
                    DsPmCb::query()->where('pm_cb_id',$id)->update($update);
                }
                break;
            case '20':
                break;
            case '21':
                break;
            case '29':
                //划转
                $num        = $info['num'];
                $shouxu     = $info['shouxu'];

                $day = date('Y-m-d');
                //增加今日兑换数量
                $this->redis0->incrByFloat($day.'_yp_to_tz_num',floatval($num));
                //增加今日兑换手续费
                $this->redis0->incrByFloat($day.'_yp_to_tz_shouxu',floatval($shouxu));
                //增加总兑换数量
                $this->redis0->incrByFloat('_yp_to_tz_num',floatval($num));
                //增加总兑换手续费
                $this->redis0->incrByFloat('_yp_to_tz_shouxu',floatval($shouxu));
                break;
            case '35':
               //人脸处理
                $this->user_renzheng($info);
                break;
            case '36':
                //充话费
                $cms = make(Huafeido::class);
                $cms->huafei_add($info['mobile'],$info['order_id'],$info['user_id'],$info['money']);
                break;
            case '37':
                //直充
                $cms = make(Huafeido::class);
                $cms->zhichong_add($info['mobile'],$info['order_id'],$info['user_id'],$info['money']);
                break;
            case '38':
                //直充 订单查询
                //$cms = make(Huafeido::class);
                //$cms->order_select($info['order_id']);
                break;
            case '39':
                //购买电影票 异步下单
                $cms = make(Laqudianyingdo::class);
                $cms->order_add($info['cinema_order_id']);
                break;
            case '40':
                //异步更新电影排期
                $cms = make(Laqudianyingdo::class);
                $cms->get_dy_paiqi_do($info['list']);
                break;
            case '41':
                //生成直推排线码
                //$cms = make(Reg::class);
                //$cms->ma_add();
                break;
            case '42':
                //绑定直推排线码
                $user_id = $info['user_id'];

                $_create_code = $this->redis3->get($user_id.'_create_code');
                if($_create_code != 2)
                {
                    $this->redis3->set($user_id.'_create_code',2,5);
                    $cms = make(Reg::class);
                    $cms->ma_user_edit($user_id);
                }
                break;
            case '43':
                //直充-品味
                $cms = make(Huafeido::class);
                $cms->zhichong_add_pinwei($info['order_id']);
                break;
            case '44':
                //刷新单个用户token
                $cms = make(ChatIm::class);
                $cms->get_user_token_do($info['user_id']);
                break;
            default:
                //写入错误日志
                break;
        }
    }

    /**
     * 储蓄罐释放
     * @param $user_id
     * @param $price
     */
    protected function chuxuguan_shifang($user_id)
    {
        $chuxuguan = DsUser::query()->where('user_id',$user_id)->value('chuxuguan');
        if($chuxuguan > 0)
        {
            $inf = DsUserChuxuguanInfo::query()->where('user_id',$user_id)->first();
            if(empty($inf))
            {
                $this->chuxuguan_set($user_id);
                DsErrorLog::add_log('储蓄罐信息没有','释放储蓄罐失败',json_encode(['user_id' => $user_id ]));
                return;
            }
            $inf =$inf->toArray();
            if($inf['doday'] == 0)
            {
                //释放百分比
                $shifang = round($chuxuguan/$inf['resday'],$this->xiaoshudian);
                //设置
                DsUserChuxuguanInfo::query()->where('user_id',$user_id)->update(['one_get' =>$shifang ]);
                $inf['one_get'] = $shifang;
            }
            if($inf['doday']+1 == $inf['resday'])
            {
                //释放所有
                $shifang = $chuxuguan;
            }elseif($inf['doday']+1 < $inf['resday'])
            {
                $shifang = $inf['one_get'];
            }
            if($shifang > 0)
            {
                $reg = DsUserChuxuguan::del_chuxuguan($user_id,$shifang,'释放储蓄罐');
                if(!$reg)
                {
                    DsErrorLog::add_log('减少储蓄罐失败','释放储蓄罐失败',json_encode(['user_id' => $user_id ]));
                    return;
                }
                //增加释放天数
                DsUserChuxuguanInfo::query()->where('user_id',$user_id)->increment('doday',1);
                //增加数分
                $rr = DsUserShufen::add_shufen($user_id,$shifang,'储存罐释放');
                if(!$rr)
                {
                    DsErrorLog::add_log('储存罐释放','增加数分失败',json_encode(['user_id' => $user_id,'num' =>$shifang,'cont' =>'储存罐释放' ]));
                }
            }else{
                DsErrorLog::add_log('储存罐释放','释放数量不对',json_encode(['user_id' => $user_id,'num' =>$shifang ]));
            }
        }
    }

    /**
     * 消费罐释放
     * @param $user_id
     * @param $price
     */
    protected function xiaofeiguan_shifang($user_id)
    {
        $xiaofeiguan = DsUser::query()->where('user_id',$user_id)->value('xiaofeiguan');
        if($xiaofeiguan > 0)
        {
            $inf = DsUserXiaofeiguanInfo::query()->where('user_id',$user_id)->first();
            if(empty($inf))
            {
                $xfguan = make(XiaoFeiGuan::class);
                $xfguan->xiaofeiguan_peizhi($user_id);
                DsErrorLog::add_log('消费罐信息没有','释放消费罐失败',json_encode(['user_id' => $user_id ]));
                return;
            }
            $inf =$inf->toArray();
            if($inf['doday'] == 0)
            {
                //释放百分比
                $shifang = round($xiaofeiguan/$inf['resday'],$this->xiaoshudian);
                //设置
                DsUserXiaofeiguanInfo::query()->where('user_id',$user_id)->update(['one_get' =>$shifang ]);
                $inf['one_get'] = $shifang;
            }
            if($inf['doday']+1 == $inf['resday'])
            {
                //释放所有
                $shifang = $xiaofeiguan;
            }elseif($inf['doday']+1 < $inf['resday'])
            {
                $shifang = $inf['one_get'];
            }
            if($shifang > 0)
            {
                $reg = DsUserXiaofeiguan::del_xiaofeiguan($user_id,$shifang,'释放消费罐');
                if(!$reg)
                {
                    DsErrorLog::add_log('减少消费罐失败','释放消费罐失败',json_encode(['user_id' => $user_id ]));
                    return;
                }
                //增加释放天数
                DsUserXiaofeiguanInfo::query()->where('user_id',$user_id)->increment('doday',1);
                //增加数分
                $rr = DsUserShufen::add_shufen($user_id,$shifang,'消费罐释放');
                if(!$rr)
                {
                    DsErrorLog::add_log('消费罐释放','增加数分失败',json_encode(['user_id' => $user_id,'num' =>$shifang,'cont' =>'消费罐释放' ]));
                }
            }else{
                DsErrorLog::add_log('消费罐释放','释放数量不对',json_encode(['user_id' => $user_id,'num' =>$shifang ]));
            }
        }
    }

    /**
     * @param $num
     * @return int
     */
    protected function getLen($num)
    {
        $num = strval($num);
        $pos=strrpos($num,'.');
        $ext=substr($num,$pos+1);
        $len=strlen($ext);
        return$len;
    }
    /**
     * 储蓄罐后续设置
     * @param $user_id
     * @param $price
     */
    protected function chuxuguan_set($user_id)
    {
        $chuxuguan = DsUser::query()->where('user_id',$user_id)->value('chuxuguan');
        if($chuxuguan > 0)
        {
            $day = $this->get_group_day($user_id);
            $time = time();
            $user_chuxuguan_info_id = DsUserChuxuguanInfo::query()->where('user_id',$user_id)->value('user_chuxuguan_info_id');
            if($user_chuxuguan_info_id)
            {
                DsUserChuxuguanInfo::query()->where('user_chuxuguan_info_id',$user_chuxuguan_info_id)
                    ->update([
                        'doday'     => 0,
                        'resday'    => $day,
                        'one_get'   => 0,
                        'edit_time' => $time
                    ]);
            }else{
                $data = [
                    'user_id'   => $user_id,
                    'doday'     => 0,
                    'resday'    => $day,
                    'one_get'   => 0,
                    'edit_time' => $time
                ];
                DsUserChuxuguanInfo::query()->insert($data);
            }
        }
    }

    /**
     *
     * @param $user_id
     * @return false|mixed|string
     */
    protected function get_group_day($user_id)
    {
        $group = $this->redis5->get($user_id.'_team_group');
        switch ($group)
        {
            case 0:
                return $this->redis0->get('level_0_shifang_day');
                break;
            case 1:
                return $this->redis0->get('level_1_shifang_day');
                break;
            case 2:
                return $this->redis0->get('level_2_shifang_day');
                break;
            case 3:
                return $this->redis0->get('level_3_shifang_day');
                break;
            case 4:
                return $this->redis0->get('level_4_shifang_day');
                break;
            case 5:
                return $this->redis0->get('level_5_shifang_day');
                break;
        }
    }

    //升级是否满足
    protected function check_update($user_id)
    {
        $group = DsUser::query()->where('user_id',$user_id)->value('group');
        if($group < 5)
        {
            $new_group = $group+1;
            $manzu_res = $this->check_manzu($user_id,$new_group);
            if($manzu_res['res'])
            {
                $reg = DsUserGroup::query()->where('group',$new_group)->select('group_name','group_jifen')->first()->toArray();
                //自动升级
                $res2 = DsUser::query()->where('user_id',$user_id)->update(['group' => $new_group ]);
                if($res2)
                {
                    //设置缓存
                    $this->redis5->set($user_id.'_team_group',$new_group);

                    DsUserGroupLog::add_log($user_id,1,$new_group,$manzu_res['msg']);

                    $infoj = [
                        'type'          => '5',
                        'user_id'       => $user_id,
                        'group'         => $new_group,
                        'id'            => $reg['group_jifen'],
                        'name'          => $reg['group_name'],
                    ];
                    $this->execdo($infoj);
                }
            }
        }
    }

    //是否会掉星级
    protected function check_diaoji($user_id)
    {
        //判断是否是首码
        $set_shouma = $this->redis0->get('set_shouma');
        if(!empty($set_shouma))
        {
            $set_shouma = json_decode($set_shouma,true);
            if(!in_array($user_id,$set_shouma))
            {
                //检测
                $this->check_diaoji_2($user_id);
            }
        }else{
            //检测
            $this->check_diaoji_2($user_id);
        }
    }
    protected function check_diaoji_2($user_id)
    {
        $group = DsUser::query()->where('user_id',$user_id)->value('group');
        if($group > 0)
        {
            $manzu_res = $this->check_manzu($user_id,$group);
            if(!$manzu_res['res'])
            {
                $new_group = $group-1;
                //自动掉级
                $rrr = DsUser::query()->where('user_id',$user_id)->update(['group' =>$new_group]);
                if($rrr)
                {
                    //设置缓存
                    $this->redis5->set($user_id.'_team_group',$new_group);

                    DsUserGroupLog::add_log($user_id,2,$new_group,$manzu_res['msg']);

                    $infoj = [
                        'type'          => '8',
                        'user_id'       => $user_id,
                        'group'         => $group,
                    ];
                    $this->execdo($infoj);
                }
            }
        }
    }
    /**
     * @param $user_id
     * @param $group
     * @return array
     */
    protected function check_manzu($user_id,$group)
    {
//        var_dump('check_manzu:'.$user_id);
        $info = DsUserGroup::query()->where('group',$group)->first();
        if(!empty($info))
        {
            $info = $info->toArray();
            //判断人数
            if($info['group'] > 1)
            {
                $gg = $info['group']-1;
                $ge_ge = $this->redis5->get($user_id.'_group_'.$gg.'_num')?$this->redis5->get($user_id.'_group_'.$gg.'_num'):0;
                if($ge_ge < 2)
                {
                    $cha = 2 - $ge_ge;
                    return ['res' =>false, 'msg' => '差'.$cha.'个'.$gg.'星用户' ];
                }
            }
//            //判断是否有相应的包
//            $user_task_pack_id = DsUserTaskPack::query()
//                ->where('user_id',$user_id)->where('status',1)
//                ->where('task_pack_id',$info['group'])->value('user_task_pack_id');
//            if(!$user_task_pack_id)
//            {
//                return false;
//            }
            //查询团队贡献值
            $team_huoyue  = $this->redis5->get($user_id.'_team_gongxianzhi') ? $this->redis5->get($user_id.'_team_gongxianzhi')  : 0;

            //大小区贡献值
            $_daqu_gongxianzhi_user_id = $this->redis5->get($user_id.'_daqu_gongxianzhi_user_id')?$this->redis5->get($user_id.'_daqu_gongxianzhi_user_id'):0;
            if($_daqu_gongxianzhi_user_id > 0)
            {
                $_daqu_gongxianzhi_user_id_2 = $this->redis5->get($user_id.'_daqu_gongxianzhi_user_id_2')?$this->redis5->get($user_id.'_daqu_gongxianzhi_user_id_2'):0;
                if($_daqu_gongxianzhi_user_id_2 > 0)
                {
                    $daqu_huoyue = $this->redis5->get($_daqu_gongxianzhi_user_id.'_team_gongxianzhi')?$this->redis5->get($_daqu_gongxianzhi_user_id.'_team_gongxianzhi'):0;
                    $daqu_huoyue += $this->redis5->get($_daqu_gongxianzhi_user_id_2.'_team_gongxianzhi')?$this->redis5->get($_daqu_gongxianzhi_user_id_2.'_team_gongxianzhi'):0;
                    $ree = $this->redis5->get($_daqu_gongxianzhi_user_id.'_gongxianzhi')?$this->redis5->get($_daqu_gongxianzhi_user_id.'_gongxianzhi'):0;
                    $ree += $this->redis5->get($_daqu_gongxianzhi_user_id_2.'_gongxianzhi')?$this->redis5->get($_daqu_gongxianzhi_user_id_2.'_gongxianzhi'):0;
                }else{
                    $daqu_huoyue = $this->redis5->get($_daqu_gongxianzhi_user_id.'_team_gongxianzhi')?$this->redis5->get($_daqu_gongxianzhi_user_id.'_team_gongxianzhi'):0;
                    $ree = $this->redis5->get($_daqu_gongxianzhi_user_id.'_gongxianzhi')?$this->redis5->get($_daqu_gongxianzhi_user_id.'_gongxianzhi'):0;
                }
                $daqu_huoyue += (int)$ree;
                $xiaoqu_huoyue = (int)($team_huoyue - $daqu_huoyue);
            }else{
                $daqu_huoyue = 0;
                $xiaoqu_huoyue  =  0;
            }

            if($info['group_da_huoyue'] > 0)
            {
                if($daqu_huoyue < $info['group_da_huoyue'])
                {
                    $ge3 = $info['group_da_huoyue']-$daqu_huoyue;
                    return ['res' =>false, 'msg' => '1团差'.$ge3.'个贡献值' ];
                }
            }
            //小区贡献值
            if($info['group_xiao_huoyue'] > 0)
            {
                if($xiaoqu_huoyue < $info['group_xiao_huoyue'])
                {
                    $ge3 = $info['group_xiao_huoyue']-$xiaoqu_huoyue;
                    return ['res' =>false, 'msg' => '2团差'.$ge3.'个贡献值' ];
                }
            }
            return ['res' =>true, 'msg' => '条件都满足，可以升级' ];
        }else{
            return ['res' =>false, 'msg' => '找不到该等级:'.$group ];
        }
    }

    /**
     *
     * @param $user_id
     * @param $group
     * @param $type
     * @return bool
     */
    protected function check_manzu_2($user_id,$group,$type)
    {
        $info = DsUserGroup::query()->where('group',$group)->first();
        if(!empty($info))
        {
            $info = $info->toArray();
            //判断直推实名用户数是否足够
            if($info['group_zhi_real'] > 0)
            {
                $zhi =  $this->redis5->get($user_id.'_zhi_huoyue')?$this->redis5->get($user_id.'_zhi_huoyue'):0;
                if($zhi < $info['group_zhi_real'])
                {
                    $ge = $info['group_zhi_real']-$zhi;
                    DsUserGroupLog::add_log($user_id,$type,$group,'直推活跃值还差'.$ge.'个');
                    return false;
                }
            }
//            //判断是否有相应的包
//            $user_task_pack_id = DsUserTaskPack::query()
//                ->where('user_id',$user_id)->where('status',1)
//                ->where('task_pack_id',$info['group'])->value('user_task_pack_id');
//            if(!$user_task_pack_id)
//            {
//                return false;
//            }
            //查询团队有效用户
            $team_huoyue  = $this->redis5->get($user_id.'_team_huoyue') ? $this->redis5->get($user_id.'_team_huoyue')  : 0;
            if($info['group_team_real'] > 0)
            {
                if($team_huoyue < $info['group_team_real'])
                {
                    $ge2 = $info['group_team_real']-$team_huoyue;
                    DsUserGroupLog::add_log($user_id,$type,$group,'团队总活跃值还差'.$ge2.'个');
                    return false;
                }
            }
            //大小区活跃人数
            $_daqu_huoyue_id = $this->redis5->get($user_id.'_daqu_huoyue_id');
            if($_daqu_huoyue_id > 0)
            {
                $daqu_huoyuezhi = $this->redis5->get($_daqu_huoyue_id.'_team_huoyue');
                $ree = $this->redis5->get($_daqu_huoyue_id.'_huoyue');
                if($ree >= 0)
                {
                    $daqu_huoyuezhi += $ree;
                }
                $xiaoqu_huoyue = $team_huoyue - $daqu_huoyuezhi;
            }else{
                $xiaoqu_huoyue  =  0;
            }
            //小区有效用户
            if($info['group_xiao_huoyue'] > 0)
            {
                if($xiaoqu_huoyue < $info['group_xiao_huoyue'])
                {
                    $ge3 = $info['group_xiao_huoyue']-$xiaoqu_huoyue;
                    DsUserGroupLog::add_log($user_id,$type,$group,'小区活跃值还差'.$ge3.'个');
                    return false;
                }
            }
            return true;
        }else{
            DsUserGroupLog::add_log($user_id,$type,$group,'找不到该等级的信息');
            return false;
        }
    }

    protected function is_json($string)
    {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }


    protected function isMobile($num)
    {
        if($num == NULL)
        {
            $num = '';
        }
        return preg_match("/^1[3456789]{1}\d{9}$/",$num);
    }

    /**
     * 机器注册
     * @param $mobile
     * @param $parent
     * @param $line
     * @return array|false
     */
    public function jiqi_zhuce($mobile,$parent,$line)
    {
        $time       = time();
        //验证是否是有效手机号
        if(!$this->isMobile($mobile))
        {
            return false;
        }
        $password = '1234sssad56adasdasdas';
        // 查找用户信息
        $uid = DsUser::query()->where('mobile',$mobile)->value('user_id');
        if($uid)
        {
            return false;
        }
        //判断邀请码是否正确
        if(empty($parent))
        {
            return false;
        }
        if($this->isMobile($parent))
        {
            $parent_info = DsUser::query()->where('mobile',$parent)->select('user_id','login_status')->first();
        }else{
            $parent_info = DsUser::query()->where('user_id',$parent)->select('user_id','login_status')->first();
        }
        if(empty($parent_info))
        {
            return false;
        }
        //检测上级信息
        $parent_info = $parent_info->toArray();
        $parent_id = $parent_info['user_id'];
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
            'nickname'      => 'mgt用户-'.$this->replace_mobile_end($mobile),
            'password'      => password_hash($password,PASSWORD_DEFAULT),
            'mobile'        => $mobile,             //手机
            'pid'           => $pid,                //上级id
            'reg_time'      => $time,               //注册时间
            'robot'         => 1,                   //机器人
            'avatar'        => 'https://jinhoumanzuo.oss-cn-hangzhou.aliyuncs.com/logo.png', //默认头像
            'pay_password'  => password_hash($password,PASSWORD_DEFAULT),
            'city_id'       => DsCity::query()->where('level',3)->inRandomOrder()->value('city_id'),
        ];
        try {
            $user_id2 = DsUser::query()->insertGetId($userDatas);
            if($user_id2)
            {
                if($line == 1)
                {
                    $lines = [
                        'user_id'   => $parent_id,
                        'son_id'    => $user_id2,
                        'num'       => $num,
                        'time'      => $time,
                    ];
                    DsUserLine::query()->insert($lines);
                }
                //为当前用户绑定上级id
                $this->redis5->set($user_id2.'_pid' ,strval($pid));
                //异步增加上级信息
                $info = [
                    'user_id'       => $user_id2,
                    'pid'           => $pid,
                    'mobile'        => $mobile,
                    'type'          => 1
                ];
                $job = ApplicationContext::getContainer()->get(DriverFactory::class);
                $job->get('register')->push(new Register($info));
                return [
                    'mobile'    => $mobile,
                    'user_id'   => $user_id2,
                ];
            }else{
                return false;
            }
        }catch (\Exception $exception)
        {
            return false;
        }
    }
    /**
     * @param $mobile
     * @return string|string[]
     */
    protected function replace_mobile_end($mobile)
    {
        return substr_replace(strval($mobile), '****',3, 4);
    }

    /**
     * 异步分离出来
     * @param $info
     * @param int $tt  0 默认 2延时执行
     */
    public function yibu2($info,$tt = 0)
    {
        if($tt > 0)
        {
            //读取配置是否允许执行
            $db = $this->redis0->get('async_db');
            $redis = ApplicationContext::getContainer()->get(\Redis::class);
            $redis->select((int)$db);
            $job = ApplicationContext::getContainer()->get(DriverFactory::class);
            $job->get('async')->push(new Async($info),(int)$tt);

        }else{
            //读取配置是否允许执行
            $db = $this->redis0->get('async_db');
            $redis = ApplicationContext::getContainer()->get(\Redis::class);
            $redis->select((int)$db);
            $job = ApplicationContext::getContainer()->get(DriverFactory::class);
            $job->get('async')->push(new Async($info));
        }
    }

    /**
     * 团队实名人数
     * @param $user_id
     */
    public function shiming($user_id)
    {
        $pid =  $this->redis5->get($user_id.'_pid');
        if($pid > 0)
        {
            //为上级增加实名用户数
            $this->redis6->sAdd($pid.'_team_user_real' ,$this->user_id);
            //计算上级的大区id是否变化
//            $this->daqu($pid,$user_id);
            //增加
            DsUserDatum::add_log($pid, 1, 3);
            $this->shiming($pid);
        }
    }
    /**
     * 计算上级的大区id是否变化
     * @param $user_id
     * @param $sid
     */
    protected function daqu($user_id,$sid)
    {
        //查询他的大区id是谁
        $_daqu_auth_id = $this->redis5->get($user_id.'_daqu_auth_id');
        if(!$_daqu_auth_id)
        {
            //设置大区id
            $this->redis5->set($user_id.'_daqu_auth_id',$sid);
        }else{
            //查找大区id团队人数
            $da_ren = $this->redis5->get($_daqu_auth_id.'_team_user_real')+1;
            //查找子id大区人数
            $zi_ren = $this->redis5->get($sid.'_team_user_real')+1;
            if($zi_ren > $da_ren)
            {
                //设置新大区id
                $this->redis5->set($user_id.'_daqu_auth_id',$sid);
            }
        }
    }


    /**
     *
     * 添加福利包
     * @param $user_id      //用户id
     * @param $task_pack_id //包id
     * @param $cont         //个人原因
     * @param $cont2        //团队原因
     * @throws \Exception
     */
    public function add_fulibao($user_id,$task_pack_id,$cont,$cont2)
    {
        //赠送活跃包
        $time = time();
        $bao = DsTaskPack::query()->where('task_pack_id',$task_pack_id)->first()->toArray();
        $data = [
            'task_pack_id'  => $task_pack_id,
            'user_id'       =>$user_id,
            'name'      =>$bao['name'],
            'need'      =>$bao['need'],
            'get'       =>$bao['get'],
            'shengyu'   =>$bao['get'],
            'day'       =>$bao['day'],
            'status'    =>1,
            'do_day'    =>0,
            'time'      =>$time,
            'end_time'  =>$time+($bao['all_day']*86400),
            'all_get'   =>0,
            'img'       =>$bao['img'],
            'one_get'   => round($bao['get']/$bao['day'],$this->xiaoshudian),
            'huoyuezhi' => $bao['huoyuezhi'],
            'gongxianzhi'=>$bao['gongxianzhi'],
            'yuanyin'   =>$cont,
        ];
        $res2 = DsUserTaskPack::query()->insert($data);
        if($res2)
        {
            //更新用户的时间
            DsUserDatum::query()->where('user_id',$user_id)->update(['last_do_time' => $time]);
            $info22 = [
                'type'          => '2',
                'user_id'       => $user_id,
                'need'          => $bao['need'],
                'name'          => $cont,
                'name2'         => $cont2,
                'huoyuezhi'     => $bao['huoyuezhi'],
                'gongxianzhi'   => $bao['gongxianzhi'],
                'task_pack_id'  => $task_pack_id,
            ];
            $this->yibu2($info22,mt_rand(5,10));
        }else{
            //写入错误日志
            DsErrorLog::add_log('添加福利包失败','',json_encode(['user_id' =>$user_id,'task_pack_id'=>$task_pack_id,'cont'=>$cont ]));
        }
    }

    protected function user_renzheng($info){
        //人脸 api 实名认证 公安一所实人认证，支持Android/IOS/H5/小程序/公众号/浏览器，多种活体(眨眼，摇头，点头，张嘴，远近，读数)
        //依赖权威公安库(公安一所)进行实人认证，支持多种活体类型及多平台(H5，IOS, Android)，全链路加密，接入极简。
        //https://market.aliyun.com/products/57126001/cmapi00046546.html?spm=5176.2020520132.101.4.4f837218TDiMWP#sku=yuncode4054600001
        $user_id = $info['user_id'];
        $r_u = DsUser::query()->where('user_id',$user_id)->update(['auth'=>1,'auth_name'=>$info['res2']['certName'],'auth_num'=>$info['res2']['certNo'],]);
        if(!$r_u){
            DsErrorLog::add_log('人脸记录通过,修改状态失败',$user_id);
        }
        $data = [
            'requestId' => $info['res2']['requestId'],
            'livingType' => $info['res2']['livingType'],
            'bestImg' => $info['res2']['bestImg'],
            'pass' => $info['res2']['pass'],
            'rxfs' => $info['res2']['rxfs'],
        ];
        $r = DsUserRenlianLog::query()->where('user_id',$user_id)->where('bizId',$info['res2']['bizId'])->update($data);
        if(!$r){
            DsErrorLog::add_log('人脸记录核验成功修改失败',$user_id);
        }
        $info = [
            'type' => '1',
            'user_id' => $user_id,
        ];
        $this->execdo($info);
    }

}