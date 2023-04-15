<?php declare(strict_types=1);

namespace App\Controller;


use App\Controller\async\All_async;
use App\Controller\async\Auth;
use App\Controller\async\Coin;
use App\Controller\async\GongXian;
use App\Controller\async\HuoYue;
use App\Controller\async\OrderSn;
use App\Controller\async\RandomGeneration;
use App\Controller\async\YDYanZhen;
use App\Job\Register;
use App\Model\DsCinema;
use App\Model\DsCinemaPaiqi;
use App\Model\DsCodeLog;
use App\Model\DsErrorLog;
use App\Model\DsJiaoyiPrice;
use App\Model\DsPmSh;
use App\Model\DsPrice;
use App\Model\DsTaskPack;
use App\Model\DsTzPrice;
use App\Model\DsUser;
use App\Model\DsUserDatum;
use App\Model\DsUserGongxianzhi;
use App\Model\DsUserHuoyuedu;
use App\Model\DsUserPhbYp;
use App\Model\DsUserPuman;
use App\Model\DsUserTaskPack;
use App\Model\DsUserTongzheng;
use App\Model\DsUserYingpiao;
use App\Model\DsViewlog;
use App\Model\SysUser;
use Hyperf\AsyncQueue\Driver\DriverFactory;
use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Di\Annotation\Inject;


/**
 * 更新缓存信息
 * @package App\Controller
 * @Controller(prefix="update")
 * Class UpdateController
 */
class UpdateController extends XiaoController
{
    protected $user_id;
    protected $uids = [];
    protected $mobiles = [];
    protected $page;
    protected $page2;
    protected $ji;
    protected $price;

    /**
     * 注册用户
     * @RequestMapping(path="is_reg",methods="get,post")
     */
    public function is_reg()
    {
//        $id = 0;
//        $num = $this->request->input('num',200);
//        $sd = DsUser::query()->where('async',0)
//            ->limit((int)$num)
//            ->orderBy('user_id')
//            ->select('user_id','pid')->get()->toArray();
//        if(!empty($sd))
//        {
//            foreach ($sd as $v)
//            {
//                $user_id = $v['user_id'];
//                $rrr = DsUser::query()->where('user_id',$user_id)->update(['async' => 1]);
//                if($rrr)
//                {
//                    $id += 1;
//                    //为当前用户绑定上级id
//                    $this->redis5->set($user_id.'_pid' ,strval($v['pid']));
//                    //异步增加上级信息
//                    $info = [
//                        'user_id'       => $user_id,
//                        'pid'           => $v['pid'],
//                        'type'          => 1
//                    ];
//                    $job = ApplicationContext::getContainer()->get(DriverFactory::class);
//                    $job->get('register')->push(new Register($info));
//                }else{
//                    DsUser::query()->where('user_id',$user_id)->update(['async' => 0]);
//                    var_dump($user_id.'修改失败');
//                    break;
//                }
//            }
//        }
//        return $this->withResponse('ok',['id' => $id]);


//        $arr = [
//            660001,
//            660002,
//            660003,
//            660004,
//            660005,
//        ];
//        $pid = 0;
//        foreach ($arr as $v)
//        {
//            $user_id = $v;
//            //为当前用户绑定上级id
//            $this->redis5->set($user_id.'_pid' ,strval($pid));
//            //异步增加上级信息
//            $info = [
//                'user_id'       => $user_id,
//                'pid'           => $pid,
//                'mobile'        => DsUser::query()->where('user_id',$user_id)->value('mobile'),
//                'type'          => 1
//            ];
//            $job = ApplicationContext::getContainer()->get(DriverFactory::class);
//            $job->get('register')->push(new Register($info));
//        }
    }

    /**
     * bak1
     * @RequestMapping(path="bak1",methods="get,post")
     */
    public function bak1()
    {
        var_dump('开始1');
        $tongzheng = DsUser::query()->where('tongzheng','>',0)->pluck('user_id')->toArray();
        foreach ($tongzheng as $v)
        {
            $user_tongzheng_id = DsUserTongzheng::query()->where('user_id',$v)->value('user_tongzheng_id');
            if(!$user_tongzheng_id)
            {
                $this->redis1->sAdd('tongzheng',$v);
            }
        }
        var_dump('开始2');
        $yingpiao = DsUser::query()->where('yingpiao','>',0)->pluck('user_id')->toArray();
        foreach ($yingpiao as $v2)
        {
            $user_yingpiao_id = DsUserYingpiao::query()->where('user_id',$v2)->value('user_yingpiao_id');
            if(!$user_yingpiao_id)
            {
                $this->redis1->sAdd('yingpiao',$v2);
            }
        }
        var_dump('开始3');
        $puman = DsUser::query()->where('puman','>',0)->pluck('user_id')->toArray();
        foreach ($puman as $v3)
        {
            $user_puman_id = DsUserPuman::query()->where('user_id',$v3)->value('user_puman_id');
            if(!$user_puman_id)
            {
                $this->redis1->sAdd('puman',$v3);
            }
        }

        return$this->withSuccess('ok');
    }

    /**
     * bak2
     * @RequestMapping(path="bak2",methods="get,post")
     */
    public function bak2()
    {

    }

//    /**
//     * huoyuezhi
//     * @RequestMapping(path="huoyuezhi_add",methods="get,post")
//     */
//    public function huoyuezhi_add()
//    {
//        $huoyuezhi = $this->request->post('huoyuezhi',0);
//        $user_id = $this->request->post('user_id',0);
//        if($huoyuezhi > 0 && $user_id)
//        {
//            $name = '数据调试';
//            //增加个人和团队活跃度
//            $sl = make(HuoYue::class);
//            $sl->add_huoyue($user_id,$huoyuezhi,$name,$name);
//            return $this->withSuccess('ok');
//        }else{
//            return $this->withError('没参数！');
//        }
//    }
//
//    /**
//     * huoyuezhi_del
//     * @RequestMapping(path="huoyuezhi_del",methods="get,post")
//     */
//    public function huoyuezhi_del()
//    {
//        $huoyuezhi = $this->request->post('huoyuezhi',0);
//        $user_id = $this->request->post('user_id',0);
//        if($huoyuezhi > 0 && $user_id)
//        {
//            $name = '数据调试';
//            //增加个人和团队活跃度
//            $sl = make(HuoYue::class);
//            $sl->huoyue_del($user_id,$huoyuezhi,$name,$name);
//            return $this->withSuccess('ok');
//        }else{
//            return $this->withError('没参数！');
//        }
//    }
//
//    /**
//     * gongxianzhi_add
//     * @RequestMapping(path="gongxianzhi_add",methods="get,post")
//     */
//    public function gongxianzhi_add()
//    {
//        $gongxianzhi = $this->request->post('gongxianzhi',0);
//        $user_id = $this->request->post('user_id',0);
//        if($gongxianzhi > 0 && $user_id)
//        {
//            $name = '数据调试';
//            //增加个人和团队贡献值
//            $gx = make(GongXian::class);
//            $gx->add_gongxianzhi($user_id,$gongxianzhi,$name);
//            return $this->withSuccess('ok');
//        }else{
//            return $this->withError('没参数！');
//        }
//    }
//
//    /**
//     * gongxianzhi_del
//     * @RequestMapping(path="gongxianzhi_del",methods="get,post")
//     */
//    public function gongxianzhi_del()
//    {
//        $gongxianzhi = $this->request->post('gongxianzhi',0);
//        $user_id = $this->request->post('user_id',0);
//        if($gongxianzhi > 0 && $user_id)
//        {
//            $name = '数据调试';
//            //增加个人和团队贡献值
//            $gx = make(GongXian::class);
//            $gx->del_gongxianzhi($user_id,$gongxianzhi,$name);
//            return $this->withSuccess('ok');
//        }else{
//            return $this->withError('没参数！');
//        }
//    }

    /**
     * bak22
     * @RequestMapping(path="bak22",methods="get,post")
     */
    public function bak22()
    {

    }

    /**
     * coin
     * @RequestMapping(path="coin",methods="get,post")
     */
    public function coin()
    {

//        $timestamp = $this->getMillisecond();
//        var_dump($timestamp);
//        $params = array(
//            'timestamp' => $timestamp,
//        );
//        $content1 = json_encode($params, JSON_UNESCAPED_UNICODE);
//        $hash = hash_hmac('sha256', $content1, '9m1X1XNp5oOXW7LPDtfdJpD3yQifJGaUgr0cFhJQqvhNmQASF74vO0acNekPsgDU');
//        var_dump($hash);
//        $api = "https://api.binance.com/sapi/v1/capital/deposit/hisrec?timestamp=$timestamp&signature=$hash";

//
//        $client = new \GuzzleHttp\Client(['proxy' => 'socks5h://119.8.52.1:10004']);
//        $content = $this->http->get($api,['headers' =>['X-MBX-APIKEY' => 'BEEt7UiMxRH8uJo5zhuneq345ENe0vkRqg9aJhJBzNaktvCAeyOIaEXlORBeQnPX'] ])->getBody()->getContents();
//        var_dump($content);
//

        $config['path'] = '/sapi/v1/capital/deposit/hisrec';
        $config['type'] = 'GET';
        $config['signature'] = true;

        $data['timestamp']= $this->getMillisecond();
        $data['recvWindow'] = 50000;

        $coin = make(Coin::class);
        $res = $coin->exec($data,$config);
        var_dump($res);

    }


    /**
     * bak3
     * @RequestMapping(path="bak3",methods="get,post")
     */
    public function bak3()
    {
        $city_id = $this->request->post('city_id',510100);
        $filmCode = $this->request->post('filmCode',51016461);
        $cinemaCode = $this->request->post('cinemaCode',0);
        $startTime = $this->request->post('startTime1',1677290679);
        $page = $this->request->post('page',1);

//        //查影城 城市下指定影片有排期的 影城列表
//        $res = DsCinemaPaiqi::query()->leftJoin('ds_cinema','ds_cinema.cinemaCode','=','ds_cinema_paiqi.cinemaCode')
//            ->leftJoin('ds_cinema_video','ds_cinema_video.cid','=','ds_cinema_paiqi.filmNo')
//            ->where('ds_cinema_paiqi.city_id',$city_id)
//            ->where('ds_cinema_paiqi.startTime','>',$startTime)
//            ->where('ds_cinema_paiqi.filmNo',$filmCode)
//            ->select('ds_cinema.cinemaName','ds_cinema_video.filmName','ds_cinema_paiqi.featureAppNo','ds_cinema_paiqi.hallName','ds_cinema_paiqi.startTime')
//            ->limit('200')
//            ->get()->toArray();

        //查影片 城市下指定影片今日有排期的
        $res = DsCinemaPaiqi::query()->leftJoin('ds_cinema','ds_cinema.cinemaCode','=','ds_cinema_paiqi.cinemaCode')
            ->leftJoin('ds_cinema_video','ds_cinema_video.cid','=','ds_cinema_paiqi.filmNo')
            ->where('ds_cinema_paiqi.city_id',$city_id)
            ->where('ds_cinema_paiqi.startTime','>',$startTime)
            ->groupBy('ds_cinema_video.filmName')
            ->select('ds_cinema_video.filmName','ds_cinema_video.cover','ds_cinema_video.cast','ds_cinema_video.type','ds_cinema_video.filmCode')
            ->inRandomOrder()
            ->forPage($page,10)->get()->toArray();

        return $this->withResponse('ok',$res);
    }

    /**
     * 多少天没做任务冻结活跃度贡献值
     * @RequestMapping(path="check_diao_hyd_gxz",methods="get,post")
     */
    public function check_diao_hyd_gxz()
    {
        $day = $this->redis0->get('day_diao_hyd_gxz');
        $miao = $day*86400;
        $ge = 0;
        if($miao > 0)
        {
            $tt = time()-$miao;
            $res = DsUserTaskPack::query()
                ->leftJoin('ds_user_data','ds_user_data.user_id','=','ds_user_task_pack.user_id')
                ->where('ds_user_task_pack.status',1)
                ->where('ds_user_data.last_do_time','<',$tt)
                ->where('ds_user_data.dj',0)
                ->limit(300)
                ->pluck('ds_user_task_pack.user_id')->toArray();
            if(!empty($res))
            {
                foreach ($res as $v)
                {
                    $rr = DsUserDatum::query()->where('user_id',$v)->update(['dj' => 1]);
                    if($rr)
                    {
                        $ge += 1;
                        $infos = [
                            'type'          => '11',
                            'user_id'       => $v,
                        ];
                        $this->yibu($infos);
                    }
                }
            }
        }
        return $this->withSuccess('已成功处理：'.$ge);
    }

    /**
     * 检测矿机到期
     * @RequestMapping(path="kuangji_end",methods="get,post")
     */
    public function kuangji_end()
    {
        $num = $this->request->input('num',100);
        $i = 0;
        $time = time();
        $info = DsUserTaskPack::query()->where('status',1)->where('end_time','<',$time)->select('user_task_pack_id','task_pack_id','name','user_id','huoyuezhi','gongxianzhi')->limit($num)->get()->toArray();
        if(!empty($info))
        {
            foreach ($info as $v)
            {
                //减少矿机
                $rev = DsUserTaskPack::query()->where('user_task_pack_id',$v['user_task_pack_id'])->update(['status' => 2]);
                if($rev)
                {
                    $i +=1;
                    $infos = [
                        'type'          => '3',
                        'user_id'       => $v['user_id'],
                        'huoyuezhi'     => $v['huoyuezhi'],
                        'gongxianzhi'   => $v['gongxianzhi'],
                        'name'          => $v['name'].' 已到期',
                        'name2'         => '用户['.$v['user_id'].']'.$v['name'].' 已到期',
                        'task_pack_id'  => $v['task_pack_id'],
                    ];
                    $this->yibu($infos);
                }
            }
        }
        return $this->withSuccess('成功处理了'.$i.'条');
    }

    /**
     * 更新统计
     * @RequestMapping(path="update_tongji",methods="get,post")
     */
    public function update_tongji()
    {

    }

    /**
     * 交易分红
     * @RequestMapping(path="jiaoyi_meitian_fenhong",methods="post,get")
     */
    public function jiaoyi_meitian_fenhong()
    {

    }


    protected function dao($user_id,$oid)
    {
        if($user_id != $oid)
        {
            $mobile = '163' . mt_rand(11111111, 99999999);
            $update = [
                'username'      => $mobile,
                'mobile'        => $mobile,
            ];
            $ress = DsUser::query()->where('user_id',$user_id)->update($update);
            if($ress)
            {
                $pid =  $this->redis5->get($user_id.'_pid');
                if($pid > 0)
                {
                    $this->dao($pid,$oid);
                }
            }else{
                $this->dao($user_id,$oid);
            }
        }
    }


    /**
     * 清理
     * @RequestMapping(path="qingli",methods="get,post")
     */
    public function qingli()
    {
//        $sid = $this->request->post('sid',0);
//        $oid = $this->request->post('oid',0);
//        if($sid > 0 && $oid > 0)
//        {
//            $this->dao($sid,$oid);
//            return $this->withSuccess('ok');
//        }else{
//            return $this->withError('err');
//        }
    }

    /**
     * 每天12点更新vip摇号数字
     * @RequestMapping(path="update_yaohaoma",methods="get,post")
     */
    public function update_yaohaoma()
    {
        $this->redis0->sAddArray('vip_num',[0,1,2,3,4,5,6,7,8,9]);
        $this->update_vip_yaohao_num();
        $this->redis0->sAddArray('shengou_num',[0,2,3,5,6,8,9]);
        $this->update_shengou_num();
    }

    /**
     * 生成永不重复的注册码
     * @RequestMapping(path="save_reg_num",methods="get,post")
     */
    public function save_reg_num()
    {
        $num = $this->request->input('num',1000);
        $ree = make(RandomGeneration::class);
        $ree->save_reg_num($num);
        return $this->withSuccess('ok');
    }
    /**
     * 生成永不重复的交易订单号
     * @RequestMapping(path="save_jiaoyi_num",methods="get,post")
     */
    public function save_jiaoyi_num()
    {
        $num = $this->request->input('num',1000);
        $ree = make(RandomGeneration::class);
        $ree->save_jiaoyi_num($num);
        return $this->withSuccess('ok');
    }
    /**
     * 每周更新一次影票排行榜数据
     * @RequestMapping(path="week_update",methods="get,post")
     */
    public function week_update()
    {
//        $arr = [
//            [
//                'img' => 'https://jinhoumanzuo.oss-cn-hangzhou.aliyuncs.com/logo.png',//头像
//                'nickname' => '昵称',//昵称
//                'tuandui_number' => 8888,//团队实名
//            ]
//        ];
//        $this->redis0->set('paihangbang_yingpiao',json_encode($arr));

        //先清除之前的
        $this->redis0->del('paihangbang_yingpiao');
        $this->redis0->del('paihangbang_yingpiao_fenhong');
        $rr = DsUserDatum::query()
            ->leftJoin('ds_user','ds_user.user_id','=','ds_user_data.user_id')
            ->where('ds_user.pid',1)
            ->where('ds_user_data.auth_ren','>',0)
            ->select('ds_user.user_id','ds_user.nickname','ds_user.avatar','ds_user_data.auth_ren')
            ->limit(10)
            ->orderByDesc('ds_user_data.auth_ren')->get()->toArray();
        if(!empty($rr))
        {
            foreach ($rr as $k => $v)
            {
                $rr[$k]['img'] = $v['avatar'];
                $rr[$k]['tuandui_number'] = $v['auth_ren'];
                unset($rr[$k]['avatar']);
                unset($rr[$k]['auth_ren']);
            }
            $this->redis0->set('paihangbang_yingpiao_fenhong',json_encode($rr));
            foreach ($rr as $k => $v)
            {
                unset($rr[$k]['user_id']);
            }
            $this->redis0->set('paihangbang_yingpiao',json_encode($rr));
        }
        return$this->withSuccess('ok');
    }

    /**
     * 自动掉星级
     * @RequestMapping(path="jiangxingji",methods="get,post")
     */
    public function jiangxingji()
    {
        $i = 0;
        $arrr = Dsuser::query()->where('group','>',0)->pluck('user_id')->toArray();
        if(!empty($arrr))
        {
            foreach ($arrr as $v)
            {
                $i += 1;
                $info22 = [
                    'type'          => '7',
                    'user_id'       => $v,
                ];
                $this->yibu($info22);
            }
        }
        return $this->withSuccess('成功处理了'.$i.'条');
    }


    /**
     * 更新交易价格
     * @RequestMapping(path="update_jiaoyi_price",methods="get,post")
     */
    public function update_jiaoyi_price()
    {
        $day = date('Y-m-d',strtotime("-1 day"));
        $tz = $this->redis0->get($day.'_tz_jiaoyi_ok')?$this->redis0->get($day.'_tz_jiaoyi_ok'):0;
        $pm = $this->redis0->get($day.'_pm_jiaoyi_ok')?$this->redis0->get($day.'_pm_jiaoyi_ok'):0;
        $bi = $this->redis0->get($day.'_jiaoyi_ok_num')?$this->redis0->get($day.'_jiaoyi_ok_num'):0;
        if($tz > 0)
        {
            $val = round($pm/$tz,2);
        }else{
            $val = 0;
        }
        $data = [
            'day'       => $day,
            'tz_ok'     => $tz,
            'pm_ok'     => $pm,
            'bishu'     => $bi,
            'riqi'      => date('m-d',strtotime("-1 day")),
            'val'       => $val
        ];
        DsJiaoyiPrice::query()->insert($data);
        return  $this->withSuccess('ok');
    }
}