<?php declare(strict_types=1);

namespace App\Controller\v1;


use App\Controller\async\OrderSn;
use App\Controller\XiaoController;
use App\Model\DsAdminSet;
use App\Model\DsErrorLog;
use App\Model\DsOrder;
use App\Model\DsPmSh;
use App\Model\DsPmUAddress;
use App\Model\DsPmUDh;
use App\Model\DsTzH;
use App\Model\DsTzPrice;
use App\Model\DsTzSg;
use App\Model\DsUser;
use App\Model\DsUserDatum;
use App\Model\DsUserGongxianzhi;
use App\Model\DsUserGroupFh;
use App\Model\DsUserHuoyuedu;
use App\Model\DsUserPuman;
use App\Model\DsUserTask;
use App\Model\DsUserTongzheng;
use App\Model\DsUserYingpiao;
use App\Model\DsVipPrice;
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
 * 数据接口
 * Class DataController
 * @Middlewares({
 *     @Middleware(SignatureMiddleware::class),
 *     @Middleware(UserMiddleware::class)
 * })
 * @package App\Controller\v1
 * @Controller(prefix="v1/data")
 */
class DataController extends XiaoController
{

    /**
     * 获取我的贡献值
     * @RequestMapping(path="get_gongxianzhi",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get_gongxianzhi()
    {
        $user_id  = Context::get('user_id');
        $page     = $this->request->post('page',1);
        if($page <= 0)
        {
            $page = 1;
        }
        $data['more']           = '0';

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

        $data['team_1']         = $daqu_huoyue;
        $data['team_2']         = $xiaoqu_huoyue;

        $data['gongxianzhi_sm']    = DsAdminSet::query()->where('set_cname','gongxianzhi_sm')->value('set_cvalue');
        $data['data']           = [];

        $res = DsUserGongxianzhi::query()->where('user_id',$user_id)
            ->orderByDesc('gongxianzhi_id')
            ->forPage($page,10)->get()->toArray();
        if(!empty($res))
        {
            //查找是否还有下页数据
            if(count($res) >= 10)
            {
                $data['more'] = '1';
            }
            //整理数据
            $datas = [];
            foreach ($res as $k => $v)
            {
                $datas[$k]['cont'] = $v['gongxianzhi_cont'];
                $datas[$k]['num']  = strval($v['gongxianzhi_num']);
                $datas[$k]['type']  = strval($v['gongxianzhi_type']);
                $datas[$k]['time']  = $this->replaceTime($v['gongxianzhi_time']);
            }
            $data['data']           = $datas;
        }

        return $this->withResponse('获取成功',$data);
    }

    /**
     * 获取我的活跃度任务
     * @RequestMapping(path="get_huoyuedu_task",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get_huoyuedu_task()
    {
        $user_id  = Context::get('user_id');

        $huoyuedu = $this->redis5->get($user_id.'_huoyue')?$this->redis5->get($user_id.'_huoyue'):0;
        $h2 = DsUserDatum::query()->where('user_id',$user_id)->sum('huoyuedu');
        $data['huoyuedu']       = $huoyuedu+$h2;
        $data['shouxu']       = $this->get_shouxu($user_id);
        $data['huoyuedu_sm']    = DsAdminSet::query()->where('set_cname','huoyuedu_sm')->value('set_cvalue');

        $data['task'] = [];
        $day = date('Y-m-d');

        $task = DsUserTask::query()->get()->toArray();
        if(!empty($task))
        {
            foreach ($task as $k => $v)
            {
                $task[$k]['is_finish'] = 0;
                $name = $user_id.'_'.$day.'huoyuedu_callback_'.$v['user_task_id'];
                $ecv = $this->redis4->get($name);
                if($ecv == 2)
                {
                    $task[$k]['is_finish'] = 1;
                }
            }
            $data['task'] = $task;
        }
        return $this->withResponse('获取成功',$data);
    }

    /**
     * 获取我的活跃度日志
     * @RequestMapping(path="get_huoyuedu_logs",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get_huoyuedu_logs()
    {
        $user_id  = Context::get('user_id');

        $page     = $this->request->post('page',1);
        if($page <= 0)
        {
            $page = 1;
        }
        $data['more']           = '0';

        $huoyuedu = $this->redis5->get($user_id.'_huoyue')?$this->redis5->get($user_id.'_huoyue'):0;
        $h2 = DsUserDatum::query()->where('user_id',$user_id)->sum('huoyuedu');
        $data['huoyuedu']       = $huoyuedu+$h2;
        $data['shouxu']         = $this->get_shouxu($user_id);
        $data['huoyuedu_sm']    = DsAdminSet::query()->where('set_cname','huoyuedu_sm')->value('set_cvalue');
        $data['data']           = [];

        $res = DsUserHuoyuedu::query()->where('user_id',$user_id)
            ->orderByDesc('huoyuedu_id')
            ->forPage($page,10)->get()->toArray();
        if(!empty($res))
        {
            //查找是否还有下页数据
            if(count($res) >= 10)
            {
                $data['more'] = '1';
            }
            //整理数据
            $datas = [];
            foreach ($res as $k => $v)
            {
                $datas[$k]['cont'] = $v['huoyuedu_cont'];
                $datas[$k]['num']  = strval($v['huoyuedu_num']);
                $datas[$k]['type']  = strval($v['huoyuedu_type']);
                $datas[$k]['time']  = $this->replaceTime($v['huoyuedu_time']);
            }
            $data['data']           = $datas;
        }

        return $this->withResponse('获取成功',$data);
    }

    /**
     * 获取我的影票
     * @RequestMapping(path="get_yingpiao",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get_yingpiao()
    {
        $user_id  = Context::get('user_id');
        $userInfo     = Context::get('userInfo');
        $page     = $this->request->post('page',1);
        if($page <= 0)
        {
            $page = 1;
        }
        $data['more']           = '0';
        $data['yingpiao']       = strval($userInfo['yingpiao']);
        $data['data']           = [];
        $data['yingpiao_sm']    = DsAdminSet::query()->where('set_cname','yingpiao_sm')->value('set_cvalue');
        $res = DsUserYingpiao::query()
            ->where('user_id',$user_id)
            ->orderByDesc('user_yingpiao_id')
            ->forPage($page,10)->get()->toArray();
        if(!empty($res))
        {
            //查找是否还有下页数据
            if(count($res) >= 10)
            {
                $data['more'] = '1';
            }
            //整理数据
            $datas = [];
            foreach ($res as $k => $v)
            {
                $datas[$k]['cont'] = $v['user_yingpiao_cont'];
                $datas[$k]['num']  = strval($v['user_yingpiao_change']);
                $datas[$k]['type']  = strval($v['user_yingpiao_type']);
                $datas[$k]['time']  = $this->replaceTime($v['user_yingpiao_time']);
            }
            $data['data']           = $datas;
        }

        return $this->withResponse('获取成功',$data);
    }

    /**
     * 获取我的通证
     * @RequestMapping(path="get_tongzheng",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get_tongzheng()
    {
        $user_id  = Context::get('user_id');
        $userInfo     = Context::get('userInfo');
        $page     = $this->request->post('page',1);
        if($page <= 0)
        {
            $page = 1;
        }
        $data['more']           = '0';
        $data['tongzheng']       = strval($userInfo['tongzheng']);
        $data['data']           = [];
        $data['tongzheng_sm']    = DsAdminSet::query()->where('set_cname','tongzheng_sm')->value('set_cvalue');
        $res = DsUserTongzheng::query()
            ->where('user_id',$user_id)
            ->orderByDesc('user_tongzheng_id')
            ->forPage($page,10)->get()->toArray();
        if(!empty($res))
        {
            //查找是否还有下页数据
            if(count($res) >= 10)
            {
                $data['more'] = '1';
            }
            //整理数据
            $datas = [];
            foreach ($res as $k => $v)
            {
                $datas[$k]['cont'] = $v['user_tongzheng_cont'];
                $datas[$k]['num']  = strval($v['user_tongzheng_change']);
                $datas[$k]['type']  = strval($v['user_tongzheng_type']);
                $datas[$k]['time']  = $this->replaceTime($v['user_tongzheng_time']);
            }
            $data['data']           = $datas;
        }

        return $this->withResponse('获取成功',$data);
    }

    /**
     * 获取我的扑满
     * @RequestMapping(path="get_puman",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get_puman()
    {
        $user_id  = Context::get('user_id');
        $userInfo     = Context::get('userInfo');
        $page     = $this->request->post('page',1);
        if($page <= 0)
        {
            $page = 1;
        }
        $data['more']           = '0';
        $data['puman']       = strval($userInfo['puman']);
        $data['data']           = [];
        $data['puman_sm']    = DsAdminSet::query()->where('set_cname','puman_sm')->value('set_cvalue');
        $res = DsUserPuman::query()
            ->where('user_id',$user_id)
            ->orderByDesc('user_puman_id')
            ->forPage($page,10)->get()->toArray();
        if(!empty($res))
        {
            //查找是否还有下页数据
            if(count($res) >= 10)
            {
                $data['more'] = '1';
            }
            //整理数据
            $datas = [];
            foreach ($res as $k => $v)
            {
                $datas[$k]['cont'] = $v['user_puman_cont'];
                $datas[$k]['num']  = strval($v['user_puman_change']);
                $datas[$k]['type']  = strval($v['user_puman_type']);
                $datas[$k]['time']  = $this->replaceTime($v['user_puman_time']);
            }
            $data['data']           = $datas;
        }

        return $this->withResponse('获取成功',$data);
    }

//    /**
//     * 获取我的扑满储备
//     * @RequestMapping(path="get_puman_cb",methods="post")
//     * @return \Psr\Http\Message\ResponseInterface
//     */
//    public function get_puman_cb()
//    {
//        $data['address'] = '';
//        $rr = DsPmUAddress::query()->where('type',1)->value('address');
//        if(!empty($rr))
//        {
//            $data['address'] = strval($rr);
//        }
//        return $this->withResponse('ok',$data);
//    }
//
//    /**
//     * 扑满储备兑换
//     * @RequestMapping(path="puman_cb_dh",methods="post")
//     * @return \Psr\Http\Message\ResponseInterface
//     */
//    public function puman_cb_dh()
//    {
//        $puman_cb_dh = $this->redis0->get('puman_cb_dh');
//        if($puman_cb_dh != 1)
//        {
//            return $this->withError('该功能暂时关闭,请耐心等待通知！');
//        }
//
//        $user_id  = Context::get('user_id');
//
//        $hash = $this->request->post('hash','');
//        $info = DsPmUDh::query()->where('hash',$hash)->where('status',0)
//            ->select('u_dh_id','puman')
//            ->first();
//        if(empty($info))
//        {
//            return $this->withError('找不到该哈希值，请确认转账是否成功！');
//        }
//        $info = $info->toArray();
//        //修改状态
//        $update = [
//            'user_id'   => $user_id,
//            'status'   => 1,
//            'use_time'   => time(),
//        ];
//        $rsd = DsPmUDh::query()->where('hash',$hash)->update($update);
//        if($rsd)
//        {
//            $infos = [
//                'type'      => '16',
//                'user_id'   => $user_id,
//                'puman'     => $info['puman'],
//            ];
//            $this->yibu($infos);
//            return $this->withSuccess('兑换成功');
//        }else{
//            return $this->withError('找不到该哈希值，请确认转账是否成功！');
//        }
//    }

    /**
     * 申请扑满储备初始化
     * @RequestMapping(path="get_puman_cb",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get_puman_cb()
    {
        $token     = Context::get('token');

        $data['address'] = '';
        $data['puman_url'] = '';
        $rr = DsPmUAddress::query()->where('type',1)
            ->inRandomOrder()
            ->value('address');
        if(!empty($rr))
        {
            $data['address'] = strval($rr);
        }
        $data['puman_url'] = $this->redis0->get('get_puman_url').'?token='.$token;
        return $this->withResponse('ok',$data);
    }

    /**
     * 扑满赎回初始化
     * @RequestMapping(path="puman_sh_index",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function puman_sh_index()
    {
        $userInfo     = Context::get('userInfo');

        $data['usdt_address'] = '';
        if(!empty($userInfo['usdt_address']))
        {
            $data['usdt_address'] = $userInfo['usdt_address'];
        }
        $data['shouxu'] = $this->redis0->get('puman_sh_shouxufei');
        return $this->withResponse('ok',$data);
    }

    /**
     * 扑满赎回
     * @RequestMapping(path="puman_sh_do",methods="post")
     * @RateLimit(create=1, consume=1, capacity=1, key={DataController::class, "_key"})
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function puman_sh_do()
    {
        //检测版本
        $this->_check_version('1.1.3');

        $puman_sh_do = $this->redis0->get('puman_sh_do');
        if($puman_sh_do != 1)
        {
            return $this->withError('该功能暂时关闭,请耐心等待通知！');
        }

        $user_id  = Context::get('user_id');
        $userInfo     = Context::get('userInfo');

        if(empty($userInfo['usdt_address']))
        {
            return $this->withError('请先绑定USDT地址！');
        }
        //检查支付密码对不对
        $pay_password = $this->request->post('pay_password','');
        $this->_check_pay_password($pay_password);

        //判断上次是否再处理
        $sf = DsPmSh::query()->where('user_id',$user_id)->orderByDesc('sh_id')
            ->select('type','sh_id')->first();
        if(!empty($sf))
        {
            $sf = $sf->toArray();
            if($sf['type'] == 0)
            {
                return $this->withError('请耐心等待上次赎回处理！');
            }
        }
        $data['usdt_address'] = $userInfo['usdt_address'];
        $puman_sh_shouxufei = $this->redis0->get('puman_sh_shouxufei');
        $num = $this->request->post('num',0);
        //判断数量
        if($num < 1)
        {
            return  $this->withError('您输入的数量有误');
        }
        if(!is_numeric($num))
        {
            return $this->withError('您输入的数量有误');
        }
        if($this->check_search_str('.',$num))
        {
            return $this->withError('您输入的数量只能为整数');
        }
        if($num < $puman_sh_shouxufei+1)
        {
            return $this->withError('您输入的数量至少大于手续费！');
        }
        //判断通证是否足够是否足够
        if($userInfo['puman'] < $num)
        {
            return $this->withError('您的扑满不足！');
        }
        $data['user_id'] = $user_id;
        $data['puman'] = $num;
        $data['shouxu'] = $puman_sh_shouxufei;
        $data['type'] = 0;
        $data['time'] = time();

        $rr = DsUserPuman::del_puman($user_id,$num,'申请赎回'.$num.'个扑满扣除'.$puman_sh_shouxufei.'个手续费');
        if(!$rr)
        {
            return $this->withError('您的操作过快，请稍后再试');
        }
        $rrr = DsPmSh::query()->insert($data);
        if($rrr)
        {
            return $this->withSuccess('申请成功，请耐心等待审核');
        }else{
            $rr1 = DsUserPuman::add_puman($user_id,$num,'退回-申请赎回'.$num.'个扑满扣除'.$puman_sh_shouxufei.'个手续费');
            if(!$rr1)
            {
                DsErrorLog::add_log('申请赎回','增加扑满失败',json_encode(['user_id' =>$user_id,'num'=>$num,'cont'=> '退回-申请赎回'.$num.'个扑满扣除'.$puman_sh_shouxufei.'个手续费' ]));
            }
            return $this->withError('内容填写有误，请重试');
        }
    }

    /**
     * 获取扑满赎回日志
     * @RequestMapping(path="get_puman_sh_logs",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get_puman_sh_logs()
    {
        $user_id  = Context::get('user_id');
        $page     = $this->request->post('page',1);
        if($page <= 0)
        {
            $page = 1;
        }
        $data['more']           = '0';
        $data['data']           = [];

        $res = DsPmSh::query()->where('user_id',$user_id)
            ->orderByDesc('sh_id')
            ->forPage($page,10)
            ->select('usdt_address','puman','time','type','cont')
            ->get()->toArray();
        if(!empty($res))
        {
            //查找是否还有下页数据
            if(count($res) >= 10)
            {
                $data['more'] = '1';
            }
            //整理数据
            foreach ($res as $k => $v)
            {
                $res[$k]['time']  = $this->replaceTime($v['time']);
                $res[$k]['cont']  = strval($v['cont']);

            }
            $data['data']           = $res;
        }

        return $this->withResponse('获取成功',$data);
    }

    /**
     * 获取通证回收日志
     * @RequestMapping(path="get_tongzheng_hs_logs",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get_tongzheng_hs_logs()
    {
        $user_id  = Context::get('user_id');
        $page     = $this->request->post('page',1);
        if($page <= 0)
        {
            $page = 1;
        }
        $data['more']           = '0';
        $data['data']           = [];

        $res = DsTzH::query()->where('user_id',$user_id)
            ->orderByDesc('hs_id')
            ->forPage($page,10)
            ->select('ali_name','ali_account','tongzheng','time','type','cont')
            ->get()->toArray();
        if(!empty($res))
        {
            //查找是否还有下页数据
            if(count($res) >= 10)
            {
                $data['more'] = '1';
            }
            //整理数据
            foreach ($res as $k => $v)
            {
                $res[$k]['time']  = $this->replaceTime($v['time']);
                $res[$k]['cont']  = strval($v['cont']);
            }
            $data['data']           = $res;
        }

        return $this->withResponse('获取成功',$data);
    }

    /**
     * 获取我的星级分红
     * @RequestMapping(path="get_xj_fenhong",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get_xj_fenhong()
    {

        $data['xj_fenhong_sm']  = DsAdminSet::query()->where('set_cname','xj_fenhong_sm')->value('set_cvalue');
        $day = date('Y-m-d');
        $day = date('Y-m-d',strtotime("-1 day"));
        $data['all_shouxu'] = $this->redis0->get($day.'_yp_to_tz_shouxu')?round($this->redis0->get($day.'_yp_to_tz_shouxu'),4):0;
        $data['info'] = [];
        $arr = DsUserGroupFh::query()->where('day',$day)
            ->select('group','num')
            ->get()->toArray();
        if(!empty($arr))
        {
            $data['info'] = $arr;
        }

        return $this->withResponse('获取成功',$data);
    }

    /**
     * 获取我的证票闪兑
     * @RequestMapping(path="get_zpsd",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get_zpsd()
    {
        $user_id  = Context::get('user_id');
        $userInfo     = Context::get('userInfo');

        $data['tongzheng']       = strval($userInfo['tongzheng']);
        $data['yingpiao']       = strval($userInfo['yingpiao']);
        $data['zpsd_sm']  = DsAdminSet::query()->where('set_cname','zpsd_sm')->value('set_cvalue');
        $data['shouxu'] = $this->get_shouxu($user_id);

        return $this->withResponse('获取成功',$data);
    }

    /**
     * 影票兑通证
     * @RequestMapping(path="yp_to_tz",methods="post")
     * @RateLimit(create=1, consume=1, capacity=1, key={DataController::class, "_key"})
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function yp_to_tz()
    {
        //检测版本
        $this->_check_version('1.1.3');
        $yp_to_tz = $this->redis0->get('yp_to_tz');
        if($yp_to_tz != 1)
        {
            return $this->withError('该功能暂时关闭,请耐心等待通知！');
        }

        $user_id  = Context::get('user_id');
        $userInfo     = Context::get('userInfo');

        $num = $this->request->post('num',0);
        //判断数量
        if($num < 0)
        {
            return  $this->withError('您输入的数量有误');
        }
        if(!is_numeric($num))
        {
            return $this->withError('您输入的数量有误');
        }
        if(!$this->checkPrice_4($num))
        {
            return $this->withError('数量只能填四位小数！');
        }
        //检查支付密码对不对
        $pay_password = $this->request->post('pay_password','');
        $this->_check_pay_password($pay_password);

        if($userInfo['yingpiao'] < $num)
        {
            return $this->withError('您的影票不足！');
        }
        $shouxu = round($num*$this->get_shouxu($user_id),$this->xiaoshudian);
        $num2 = $num-$shouxu;
        $res = DsUserYingpiao::del_yingpiao($user_id,$num,'兑换'.$num2.'个通证');
        if(!$res)
        {
            return $this->withError('您的操作过快，请慢一点再试呢');
        }
        $rrr = DsUserTongzheng::add_tongzheng($user_id,$num2,$num.'个影票兑换');
        if($rrr)
        {
            //写入每日手续费分红统计
            $infos = [
                'type'      => '29',
                'user_id'   => $user_id,
                'num'       => $num,
                'shouxu'    => $shouxu,
            ];
            $this->yibu($infos);
            return $this->withSuccess('兑换成功');
        }else{
            DsUserYingpiao::add_yingpiao($user_id,$num,'退回-兑换'.$num2.'个通证');
            return $this->withError('您的操作过快，请慢一点再试呢');
        }
    }

    /**
     * 通证兑影票
     * @RequestMapping(path="tz_to_yp",methods="post")
     * @RateLimit(create=1, consume=1, capacity=1, key={DataController::class, "_key"})
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function tz_to_yp()
    {
        //检测版本
        $this->_check_version('1.1.3');
        $tz_to_yp = $this->redis0->get('tz_to_yp');
        if($tz_to_yp != 1)
        {
            return $this->withError('该功能暂时关闭,请耐心等待通知！');
        }

        $user_id  = Context::get('user_id');
        $userInfo     = Context::get('userInfo');

        $num = $this->request->post('num',0);
        //判断数量
        if($num < 0)
        {
            return  $this->withError('您输入的数量有误');
        }
        if(!is_numeric($num))
        {
            return $this->withError('您输入的数量有误');
        }
        if(!$this->checkPrice_4($num))
        {
            return $this->withError('数量只能填四位小数！');
        }
        //检查支付密码对不对
        $pay_password = $this->request->post('pay_password','');
        $this->_check_pay_password($pay_password);

        if($userInfo['tongzheng'] < $num)
        {
            return $this->withError('您的通证不足！');
        }
        $res = DsUserTongzheng::del_tongzheng($user_id,$num,'兑换'.$num.'个影票');
        if(!$res)
        {
            return $this->withError('您的操作过快，请慢一点再试呢');
        }
        $rrr = DsUserYingpiao::add_yingpiao($user_id,$num,$num.'个通证兑换');
        if($rrr)
        {
            return $this->withSuccess('兑换成功');
        }else{
            DsUserTongzheng::add_tongzheng($user_id,$num,'退回-兑换'.$num.'个影票');
            return $this->withError('您的操作过快，请慢一点再试呢');
        }
    }

    /**
     * 获取USDT到账后地址
     * @RequestMapping(path="get_usdt_address",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get_usdt_address()
    {
        $user_id  = Context::get('user_id');
        $userInfo     = Context::get('userInfo');

        $data['usdt_address'] = '';
        if(!empty($userInfo['usdt_address']))
        {
            $data['usdt_address'] = $userInfo['usdt_address'];
        }
        return $this->withResponse('ok',$data);
    }

    /**
     * 绑定USDT到账后地址
     * @RequestMapping(path="bing_usdt_address",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function bing_usdt_address()
    {
        $user_id  = Context::get('user_id');
        $userInfo     = Context::get('userInfo');

        $usdt_address = $this->request->post('usdt_address','');
        if(empty($usdt_address))
        {
            return $this->withError('请填写内容！');
        }
        $sd = DsUser::query()->where('user_id',$user_id)->update(['usdt_address' =>$usdt_address ]);
        if($sd)
        {
            return $this->withSuccess('操作成功');
        }else{
            return $this->withError('你填写的内容有误，请修改重试');
        }
    }

    /**
     * 获取支付宝配置信息
     * @RequestMapping(path="get_zfb",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get_zfb()
    {
        $user_id  = Context::get('user_id');
        $userInfo     = Context::get('userInfo');

        $data['ali_name'] = '';
        $data['ali_account'] = '';
        if(!empty($userInfo['ali_name']) && !empty($userInfo['ali_account']))
        {
            $data['ali_name'] = $userInfo['ali_name'];
            $data['ali_account'] = $userInfo['ali_account'];
        }
        return $this->withResponse('ok',$data);
    }

    /**
     * 绑定支付宝配置信息
     * @RequestMapping(path="bing_zfb",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function bing_zfb()
    {
        $user_id  = Context::get('user_id');
        $userInfo     = Context::get('userInfo');

        $ali_name = $this->request->post('ali_name','');
        $ali_account = $this->request->post('ali_account','');

        if(empty($ali_name) || empty($ali_account))
        {
            return $this->withError('请填写完整内容！');
        }
        $sd = DsUser::query()->where('user_id',$user_id)->update(['ali_name' =>$ali_name,'ali_account' => $ali_account ]);
        if($sd)
        {
            return $this->withSuccess('操作成功');
        }else{
            return $this->withError('你填写的内容有误，请修改重试');
        }
    }

    /**
     * 通证数据-申购初始化
     * @RequestMapping(path="tz_sg_index",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function tz_sg_index()
    {
        $user_id  = Context::get('user_id');
        $userInfo     = Context::get('userInfo');
        $day = date('Y-m-d');
        //获取申购今日幸运号码列表
        $this->update_shengou_num();

        $data['shengou_num'] = '';
        $data['is_yao'] = 0;
        //判断今日是否摇号
        $yao = $this->redis3->exists($day.'_shengou_num_'.$user_id);
        if($yao)
        {
            $data['shengou_num'] = $this->redis3->get($day.'_shengou_num_'.$user_id);
            $data['is_yao'] = 1;
        }
        //获取申购今日幸运号码列表
        $data['xinyun_num']  = $this->redis0->sMembers($day.'_shengou_num');
        $data['tongzheng_price']  = DsTzPrice::query()->get()->toArray();
        return $this->withResponse('ok',$data);
    }

    /**
     * 通证数据-申购摇号
     * @RequestMapping(path="tz_sg_waggle",methods="post")
     * @RateLimit(create=1, consume=1, capacity=1, key={DataController::class, "_key"})
     * @return \Psr\Http\Message\ResponseInterface
     * @throws Exception
     */
    public function tz_sg_waggle()
    {
        $user_id       = Context::get('user_id');

        $this->_check_auth();

        $day = date('Y-m-d');
        //判断今日是否摇号
        $yao = $this->redis3->exists($day.'_shengou_num_'.$user_id);
        if($yao)
        {
            return $this->withError('今日已摇号！');
        }
        $vip_num = $this->redis0->sRandMember('shengou_num');
        $this->redis3->set($day.'_shengou_num_'.$user_id,strval($vip_num));
        //判断是否抽中幸运数字
        $data['vip_num'] = $vip_num;
        $data['zhong'] = 0;
        if($this->redis0->sIsMember($day.'_shengou_num',$vip_num))
        {
            $data['zhong'] = 1;
        }
        return $this->withResponse('ok',$data);
    }

    /**
     * 通证数据-申购
     * @RequestMapping(path="tz_sg_buy",methods="post")
     * @RateLimit(create=1, consume=1, capacity=1, key={DataController::class, "_key"})
     * @return \Psr\Http\Message\ResponseInterface
     * @throws Exception
     */
    public function tz_sg_buy()
    {
        $tz_sg_buy = $this->redis0->get('tz_sg_buy');
        if($tz_sg_buy != 1)
        {
            return $this->withError('很遗憾，可申购通证总额已全部发行完毕，感谢您对今后满座的关注与支持。');
        }

        $user_id       = Context::get('user_id');

        $day = date('Y-m-d');
        //判断今日是否摇号
        $yao = $this->redis3->exists($day.'_shengou_num_'.$user_id);
        if(!isset($yao))
        {
            return $this->withError('请先摇号！');
        }
        $vip_num = $this->redis3->get($day.'_shengou_num_'.$user_id);
        //判断是否抽中幸运数字
        if(!$this->redis0->sIsMember($day.'_shengou_num',$vip_num))
        {
            return $this->withError('您未摇中今日幸运号码！');
        }

        $id = $this->request->post('id',0);
        $info = DsTzPrice::query()->where('tz_price_id',$id)->first();
        if(empty($info))
        {
            return $this->withError('找不到该消息！');
        }
        $info = $info->toArray();
        $all = DsTzSg::query()->where('user_id',$user_id)->sum('tongzheng');
        if($all > 0)
        {
            if($all > 120)
            {
                return $this->withError('每个用户最多申购120个通证');
            }
            if($all+$info['tongzheng'] > 120)
            {
                return $this->withError('每个用户最多申购120个通证');
            }
            $jinrinum = DsTzSg::query()->where('user_id',$user_id)->where('day',date('Y-m-d'))->sum('tongzheng');
            if($jinrinum > 0)
            {
                if($jinrinum > 40)
                {
                    return $this->withError('每个用户每天最多申购40个通证');
                }
                if($jinrinum+$info['tongzheng'] > 40)
                {
                    return $this->withError('每个用户每天最多申购40个通证');
                }
            }
        }

        //判断是否
        $pay_type = $this->request->post('pay_type',1); //1支付宝 2微信 3银行卡 4余额通证支付 5铺满支付
        switch ($pay_type)
        {
            case 1:
                //生成订单号
                $od = make(OrderSn::class);
                $orderSn = $od->createOrderSN();
                //生成支付订单
                $orderDatas = [
                    'order_sn'          => $orderSn,         // 这里要保证订单的唯一性
                    'user_id'           => $user_id,         //用户id
                    'to_id'             => 0,               //为谁支付的id
                    'payment_type'      => $pay_type,        //支付类型  1.alipay   2.wechat
                    'need_money'        => $info['price'],         //需要支付的金额
                    'order_status'      => 0,              //订单状态 0-未支付；1-已支付
                    'add_time'          => time(),           //订单生成时间
                    'order_type'        => 5,              //开通类型 购买商品开通类型 1实名认证 2开通会员 3续费会员 4购买商品
                    'order_relation'    => $info['tongzheng']
                ];
                //入库
                $res2 = DsOrder::query()->insertGetId($orderDatas);
                if(!$res2)
                {
                    return $this->withError('当前支付订单较多,请稍后再试！');
                }

                $cpay = make(CommonPayController::class);
                $z = $cpay->pay($user_id,$pay_type,$info['price'],$orderSn,'通证申购');
                return $this->withResponse('订单生成成功',$z);
                break;
            case 2:
                return $this->withError('请使用支付宝支付');
                break;
            default :
                return $this->withError('请使用支付宝支付');
                break;
        }
    }

    /**
     * 通证数据-回收初始化
     * @RequestMapping(path="tz_hs_index",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function tz_hs_index()
    {
        $user_id  = Context::get('user_id');
        $userInfo     = Context::get('userInfo');
        $data['ali_name'] = '';
        $data['ali_account'] = '';
        $data['tongzheng'] = $userInfo['tongzheng'];
        $fen = intval($userInfo['tongzheng'] / 10);
        $data['fen'] = $fen;
        if(!empty($userInfo['ali_name']) && !empty($userInfo['ali_account']))
        {
            $data['ali_name'] = $userInfo['ali_name'];
            $data['ali_account'] = $userInfo['ali_account'];
        }
        return $this->withResponse('ok',$data);
    }

    /**
     * 通证数据-回收
     * @RequestMapping(path="tz_hs_do",methods="post")
     * @RateLimit(create=1, consume=1, capacity=1, key={DataController::class, "_key"})
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function tz_hs_do()
    {
        //检测版本
        $this->_check_version('1.1.3');
        $tz_hs_do_status = $this->redis0->get('tz_hs_do_status');
        if($tz_hs_do_status != 1)
        {
            return $this->withError('通证回收工作已全面结束，满座通证定价权成功交接自由市场。');
        }

        $user_id  = Context::get('user_id');
        $userInfo     = Context::get('userInfo');

        if(empty($userInfo['ali_name']) || empty($userInfo['ali_account']))
        {
            return $this->withError('请先绑定支付宝信息！');
        }

        //检查支付密码对不对
        $pay_password = $this->request->post('pay_password','');
        $this->_check_pay_password($pay_password);

        //判断上次是否再处理
        $sf = DsTzH::query()->where('user_id',$user_id)->orderByDesc('hs_id')
            ->select('type','hs_id')->first();
        if(!empty($sf))
        {
            $sf = $sf->toArray();
            if($sf['type'] == 0)
            {
                return $this->withError('请耐心等待上次回收处理！');
            }
        }
        $data['ali_name'] = $userInfo['ali_name'];
        $data['ali_account'] = $userInfo['ali_account'];

        $fen = intval($userInfo['tongzheng'] / 10);
        $pfen = $this->request->post('fen',1);
        //判断数量
        if($pfen < 1)
        {
            return  $this->withError('您输入的份数有误');
        }
        if(!is_numeric($pfen))
        {
            return $this->withError('您输入的份数有误');
        }
        if($this->check_search_str('.',$pfen))
        {
            return $this->withError('您输入的份数只能为整数');
        }
        if($pfen > $fen)
        {
            return $this->withError('你最多可回收'.$fen.'份');
        }
        $data['user_id'] = $user_id;
        $data['tongzheng'] = $pfen*10;
        $data['type'] = 0;
        $data['time'] = time();
        $data['day'] = date('Y-m-d');
        //判断是否支付成功
        $re1 = DsUserTongzheng::del_tongzheng($user_id,$pfen*10,'申请回收'.$pfen.'份通证');
        if(!$re1)
        {
            return $this->withError('您的操作过快，请稍后再试');
        }
        $rrr = DsTzH::query()->insert($data);
        if($rrr)
        {
            return $this->withSuccess('申请成功，请耐心等待审核');
        }else{
            //退回
            $rr2 = DsUserTongzheng::add_tongzheng($user_id,$pfen*10,'退回-申请回收'.$pfen.'份通证');
            if(!$rr2)
            {
                DsErrorLog::add_log('申请通证回收','增加通证失败',json_encode(['user_id' =>$user_id,'num'=>$pfen*10,'cont'=> '退回-申请回收'.$pfen.'份通证' ]));
            }
            return $this->withError('内容填写有误，请重试');
        }
    }

    /**
     * 获取公益初始化
     * @RequestMapping(path="get_gy_index",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get_gy_index()
    {
        $user_id  = Context::get('user_id');
        $userInfo     = Context::get('userInfo');
        $data['user_id'] = $user_id;
        $data['nickname'] = mb_substr((string)$userInfo['nickname'],0,6).'...';;
        $data['avatar'] = $userInfo['avatar'];
        $data['love'] = $userInfo['love']<0?0:$userInfo['love'];
        $all_gongyi_price = $this->redis0->get('all_gongyi_price')?strval(round($this->redis0->get('all_gongyi_price'),2)):'0';
        $data['all_price'] = $all_gongyi_price;
        $user_list = DsOrder::query()->where('order_status',1)->where('order_type',6)->select('user_id','money','pay_time')->limit(6)->orderByDesc('pay_time')->get()->toArray();
        if(!empty($user_list) && is_array($user_list)){
            $user_list2 = [];
            foreach ($user_list as $k => $v){
                $user = DsUser::query()->where('user_id',$v['user_id'])->select('mobile','avatar','nickname')->first();
                if(!empty($user)){
                    $user = $user->toArray();
                    $user_list2[$k]['avatar'] = $user['avatar'];

                    $nickname = mb_strlen((string)$user['nickname']);
                    if($nickname > 9){
                        $user['nickname'] = mb_substr((string)$user['nickname'],0,8).'...';
                    }
                    $user_list2[$k]['nickname'] = $user['nickname'];
                    //$user_list2[$k]['mobile'] = $this->hideStr($user['mobile']);
                    $user_list2[$k]['money'] = $v['money'];
                }
            }
        }else{
            $user_list2 = [];
        }
        $data['user_list'] = $user_list2;
        return $this->withResponse('ok',$data);
    }

    /**
     * 支付公益
     * @RequestMapping(path="get_gy_buy",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get_gy_buy()
    {
        $get_gy_buy_status = $this->redis0->get('get_gy_buy_status');
        if($get_gy_buy_status != 1)
        {
            return $this->withError('该功能暂时关闭,请耐心等待通知！');
        }

        $this->_check_auth();

        $user_id  = Context::get('user_id');
        //1.8-1.9随机
        $price = $this->randomFloat(0.01,0.09);
        //判断是否
        $pay_type = $this->request->post('pay_type',1); //1支付宝 2微信 3银行卡 4余额通证支付 5铺满支付
        switch ($pay_type)
        {
            case 1:
                //生成订单号
                $od = make(OrderSn::class);
                $orderSn = $od->createOrderSN();
                //生成支付订单
                $orderDatas = [
                    'order_sn'          => $orderSn,         // 这里要保证订单的唯一性
                    'user_id'           => $user_id,         //用户id
                    'to_id'             => 0,               //为谁支付的id
                    'payment_type'      => $pay_type,        //支付类型  1.alipay   2.wechat
                    'need_money'        => $price,         //需要支付的金额
                    'order_status'      => 0,              //订单状态 0-未支付；1-已支付
                    'add_time'          => time(),           //订单生成时间
                    'order_type'        => 6,
                ];
                //入库
                $res2 = DsOrder::query()->insertGetId($orderDatas);
                if(!$res2)
                {
                    return $this->withError('当前支付订单较多,请稍后再试！');
                }

                $cpay = make(CommonPayController::class);
                $z = $cpay->pay($user_id,$pay_type,$price,$orderSn,'今后满座充满爱');
                return $this->withResponse('订单生成成功',$z);
                break;
            default :
                return $this->withError('请使用支付宝支付');
                break;
        }
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