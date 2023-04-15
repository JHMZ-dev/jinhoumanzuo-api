<?php declare(strict_types=1);

namespace App\Controller\v1;

use App\Controller\AliFileController;
use App\Controller\async\RandomGeneration;
use App\Controller\XiaoController;
use App\Job\AliCms;
use App\Model\DsAdminSet;
use App\Model\DsErrorLog;
use App\Model\DsJiaoyiPm;
use App\Model\DsJiaoyiPrice;
use App\Model\DsJiaoyiTz;
use App\Model\DsPmCb;
use App\Model\DsPmUAddress;
use App\Model\DsPmUDh;
use App\Model\DsPrice;
use App\Model\DsUser;
use App\Model\DsUserDatum;
use App\Model\DsUserMoneyInfo;
use App\Model\DsUserPuman;
use App\Model\DsUserTongzheng;
use Hyperf\AsyncQueue\Driver\DriverFactory;
use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Annotation\Middleware;
use App\Middleware\UserMiddleware;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Context;
use Hyperf\RateLimit\Annotation\RateLimit;
use Swoole\Exception;
use Hyperf\HttpServer\Annotation\Middlewares;
use App\Middleware\SignatureMiddleware;
use App\Middleware\WebMiddleware;
/**
 * 交易接口
 * Class TaskController
 * @Middlewares({
 *     @Middleware(WebMiddleware::class),
 *     @Middleware(UserMiddleware::class)
 * })
 * @package App\Controller\v1
 * @Controller(prefix="v1/transaction")
 */
class TransactionController extends XiaoController
{

    protected $daojishi = 900;
    /**
     * 交易我的
     * @RequestMapping(path="get_my",methods="post")
     */
    public function get_my()
    {
        $user_id      = Context::get('user_id');
        $userInfo     = Context::get('userInfo');
        $data['user_id'] = $user_id;
        $data['nickname']  = strval($userInfo['nickname']);
        $data['avatar']    = strval($userInfo['avatar']);
        $data['tongzheng']    = strval($userInfo['tongzheng']);
        $data['dj_tongzheng']    = DsJiaoyiTz::query()->where('user_id',$user_id)->where('type',1)->sum('tz_num');
        $data['puman']    = strval($userInfo['puman']);
        $data['dj_puman']    = DsJiaoyiPm::query()->where('user_id',$user_id)->where('type',1)->sum('pm_num');
        return $this->withResponse('获取成功',$data);
    }

    /**
     * 交易初始化
     * @RequestMapping(path="chushihua",methods="post")
     */
    public function chushihua()
    {
        $data['week_price'] = [];
        //获取一周内的数量
        $day = date('Y-m-d');
        $z2 = date('Y-m-d',strtotime("-6 day"));
        $res = DsJiaoyiPrice::query()
            ->where('day','>=',$z2)
            ->where('day','<=',$day)
            ->orderBy('price_id')->limit(6)->select('riqi','val','tz_ok','pm_ok','bishu')->get()->toArray();
        if(!empty($res))
        {
            $tz = $this->redis0->get($day.'_tz_jiaoyi_ok')?$this->redis0->get($day.'_tz_jiaoyi_ok'):0;
            $pm = $this->redis0->get($day.'_pm_jiaoyi_ok')?$this->redis0->get($day.'_pm_jiaoyi_ok'):0;
            if($tz > 0)
            {
                $val = round($pm/$tz,2);
            }else{
                $val = 0;
            }
            $rr = [
                'riqi'  => date('m-d'),
                'val'   => $val,
                'tz_ok'  => $tz,
                'pm_ok'  => $pm,
                'bishu'  => $this->redis0->get($day.'_jiaoyi_ok_num')?$this->redis0->get($day.'_jiaoyi_ok_num'):0,
            ];
            array_push($res,$rr);
            $data['week_price'] = $res;
        }
        $data['tz_shangjia'] = $this->redis0->get('tz_shangjia')?$this->redis0->get('tz_shangjia'):0;
        $data['pm_shangjia'] = $this->redis0->get('pm_shangjia')?$this->redis0->get('pm_shangjia'):0;
        $data['tz_jiaoyi_ok'] = $this->redis0->get($day.'_tz_jiaoyi_ok')?$this->redis0->get($day.'_tz_jiaoyi_ok'):0;
        $data['pm_jiaoyi_ok'] = $this->redis0->get($day.'_pm_jiaoyi_ok')?$this->redis0->get($day.'_pm_jiaoyi_ok'):0;
        $data['jiaoyi_guize']    = DsAdminSet::query()->where('set_cname','jiaoyi_guize')->value('set_cvalue');
        return $this->withResponse('ok',$data);
    }

    /**
     * 获取通证上架
     * @RequestMapping(path="get_tz_info",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     * @throws Exception
     */
    public function get_tz_info()
    {
        $user_id      = Context::get('user_id');

        $data['more'] = '0';
        $data['data'] = [];
        $page = $this->request->post('page',1);
        if($page <= 0)
        {
            $page =1;
        }
        $where = [];
//        $where = ['user_id','!=',$user_id];
        array_push($where,['type','=',1]);
        $paixu = $this->request->post('paixu',1); //1不排序 2可获得大到小 3可获得小到大 4比列大到小 5比列小到大
        switch ($paixu)
        {
            case 1:
                $res = DsJiaoyiTz::query()
                    ->where($where)
                    ->select('jiaoyi_tz_id','order_sn','tz_num','pm_num','bilie','time')
                    ->forPage($page,10)
                    ->orderByDesc('jiaoyi_tz_id')
                    ->get()->toArray();
                break;
            case 2:
                $res = DsJiaoyiTz::query()
                    ->where($where)
                    ->select('jiaoyi_tz_id','order_sn','tz_num','pm_num','bilie','time')
                    ->forPage($page,10)
                    ->orderByDesc('pm_num')
                    ->get()->toArray();
                break;
            case 3:
                $res = DsJiaoyiTz::query()
                    ->where($where)
                    ->select('jiaoyi_tz_id','order_sn','tz_num','pm_num','bilie','time')
                    ->forPage($page,10)
                    ->orderBy('pm_num')
                    ->get()->toArray();
                break;
            case 4:
                $res = DsJiaoyiTz::query()
                    ->where($where)
                    ->select('jiaoyi_tz_id','order_sn','tz_num','pm_num','bilie','time')
                    ->forPage($page,10)
                    ->orderByDesc('bilie')
                    ->get()->toArray();
                break;
            case 5:
                $res = DsJiaoyiTz::query()
                    ->where($where)
                    ->select('jiaoyi_tz_id','order_sn','tz_num','pm_num','bilie','time')
                    ->forPage($page,10)
                    ->orderBy('bilie')
                    ->get()->toArray();
                break;
            default:
                return  $this->withError('排序选择有误！');
                break;
        }

        if(!empty($res))
        {
            //查找是否还有下页数据
            if(count($res) >= 10)
            {
                $data['more'] = '1';
            }
            foreach ($res as $k => $v)
            {
                $res[$k]['time'] = $this->replaceTime($v['time']);
            }
            $data['data'] = $res;
        }
        return $this->withResponse('获取成功',$data);
    }

    /**
     * 获取扑满上架
     * @RequestMapping(path="get_pm_info",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     * @throws Exception
     */
    public function get_pm_info()
    {
        $user_id      = Context::get('user_id');

        $data['more'] = '0';
        $data['data'] = [];
        $page = $this->request->post('page',1);
        if($page <= 0)
        {
            $page =1;
        }
        $where = [];
//        $where = ['user_id','!=',$user_id];
        array_push($where,['type','=',1]);
        $paixu = $this->request->post('paixu',1); //1不排序 2可获得大到小 3可获得小到大 4比列大到小 5比列小到大
        switch ($paixu)
        {
            case 1:
                $res = DsJiaoyiPm::query()
                    ->where($where)
                    ->select('jiaoyi_pm_id','order_sn','tz_num','pm_num','bilie','time')
                    ->forPage($page,10)
                    ->orderByDesc('jiaoyi_pm_id')
                    ->get()->toArray();
                break;
            case 2:
                $res = DsJiaoyiPm::query()
                    ->where($where)
                    ->select('jiaoyi_pm_id','order_sn','tz_num','pm_num','bilie','time')
                    ->forPage($page,10)
                    ->orderByDesc('tz_num')
                    ->get()->toArray();
                break;
            case 3:
                $res = DsJiaoyiPm::query()
                    ->where($where)
                    ->select('jiaoyi_pm_id','order_sn','tz_num','pm_num','bilie','time')
                    ->forPage($page,10)
                    ->orderBy('tz_num')
                    ->get()->toArray();
                break;
            case 4:
                $res = DsJiaoyiPm::query()
                    ->where($where)
                    ->select('jiaoyi_pm_id','order_sn','tz_num','pm_num','bilie','time')
                    ->forPage($page,10)
                    ->orderByDesc('bilie')
                    ->get()->toArray();
                break;
            case 5:
                $res = DsJiaoyiPm::query()
                    ->where($where)
                    ->select('jiaoyi_pm_id','order_sn','tz_num','pm_num','bilie','time')
                    ->forPage($page,10)
                    ->orderBy('bilie')
                    ->get()->toArray();
                break;
            default:
                return  $this->withError('排序选择有误！');
                break;
        }

        if(!empty($res))
        {
            //查找是否还有下页数据
            if(count($res) >= 10)
            {
                $data['more'] = '1';
            }
            foreach ($res as $k => $v)
            {
                $res[$k]['time'] = $this->replaceTime($v['time']);
            }
            $data['data'] = $res;
        }
        return $this->withResponse('获取成功',$data);
    }

    /**
     * 通证上架
     * @RequestMapping(path="tz_grounding",methods="post")
     * @RateLimit(create=1, consume=1, capacity=1, key={TransactionController::class, "_key"})
     * @return \Psr\Http\Message\ResponseInterface
     * @throws Exception
     */
    public function tz_grounding()
    {
        $user_id      = Context::get('user_id');
        $userInfo     = Context::get('userInfo');

        $tz_grounding_status = $this->redis0->get('tz_grounding_status');
        if($tz_grounding_status != 1)
        {
            return $this->withError('该功能暂时关闭,请耐心等待通知！');
        }

        //判断是否实名
        $this->_check_auth();

        //检查支付密码对不对
        $pay_password = $this->request->post('pay_password','');
        $this->_check_pay_password($pay_password,2);

        //判断是否是会员
        $this->check_vip_and_huoyue150($user_id);

        //检测请求频繁
        $this->check_often($user_id);
        $this->start_often($user_id);

        $tz_num = $this->request->post('tz_num',0);
        //判断数量
        if($tz_num < 1)
        {
            return  $this->withError('您输入的数量有误');
        }
        if(!is_numeric($tz_num))
        {
            return $this->withError('您输入的数量有误');
        }
        if($this->check_search_str('.',$tz_num))
        {
            return $this->withError('您输入的数量只能为整数');
        }

        $bilie = $this->request->post('bilie','');
        if($bilie <= 0)
        {
            return $this->withError('通证兑扑满的比列输入有误！');
        }
        if(!$this->checkPrice($bilie))
        {
            return $this->withError('通证兑扑满的比列只能填两位小数！');
        }

        //判断通证是否足够是否足够
        if($userInfo['tongzheng'] < $tz_num)
        {
            $ge = $tz_num-$userInfo['tongzheng'];
            return $this->withError('您还需要'.$ge.'个通证才能上架！');
        }
        $pm = round($tz_num*$bilie,2);

        $re1 = DsUserTongzheng::del_tongzheng($user_id,$tz_num,'上架'.$tz_num.'个通证');
        if(!$re1)
        {
            return $this->withError('您的提交过快，请稍后再试');
        }
        $order_sn = $this->redis0->sPop('create_jiaoyi_num_qu');
        if(!$order_sn)
        {
            //生成一下
            $ree = make(RandomGeneration::class);
            $ree->save_jiaoyi_num();
            return $this->withError('您的提交过快，请稍后再试');
        }
        $data = [
            'user_id'       => $user_id,
            'order_sn'      => $order_sn,
            'tz_num'        => $tz_num,
            'pm_num'        => $pm,
            'day'           => $this->get_day(),
            'time'          => time(),
            'type'          => 1,
            'bilie'        => $bilie,
        ];
        try {
            $res = DsJiaoyiTz::query()->insertGetId($data);
            if($res)
            {
                //总求购+1
                $this->redis0->incrBy('tz_shangjia',(int)$tz_num);
                return $this->withSuccess('通证上架成功');
            }else{
                //退回通证
                $tui = DsUserTongzheng::add_tongzheng($user_id,$tz_num,'退回-上架'.$tz_num.'个通证');
                if(!$tui)
                {
                    DsErrorLog::add_log('通证上架','增加通证失败',json_encode(['user_id' =>$user_id,'num' =>$tz_num,'cont' => '退回-上架'.$tz_num.'个通证'  ]));
                }
                return $this->withError('您的提交过快，请稍后再试');
            }
        }catch (\Exception $exception)
        {
            var_dump($exception->getMessage());
            //退回通证
            $tui = DsUserTongzheng::add_tongzheng($user_id,$tz_num,'退回-上架'.$tz_num.'个通证');
            if(!$tui)
            {
                DsErrorLog::add_log('通证上架','增加通证失败',json_encode(['user_id' =>$user_id,'num' =>$tz_num,'cont' => '退回-上架'.$tz_num.'个通证'  ]));
            }
            return $this->withError('您的提交过快，请稍后再试');
        }
    }

    /**
     * 扑满上架
     * @RequestMapping(path="pm_grounding",methods="post")
     * @RateLimit(create=1, consume=1, capacity=1, key={TransactionController::class, "_key"})
     * @return \Psr\Http\Message\ResponseInterface
     * @throws Exception
     */
    public function pm_grounding()
    {
        $user_id      = Context::get('user_id');
        $userInfo     = Context::get('userInfo');

        $pm_grounding_status = $this->redis0->get('pm_grounding_status');
        if($pm_grounding_status != 1)
        {
            return $this->withError('该功能暂时关闭,请耐心等待通知！');
        }

        //判断是否实名
        $this->_check_auth();

        //检查支付密码对不对
        $pay_password = $this->request->post('pay_password','');
        $this->_check_pay_password($pay_password,2);

        //检测请求频繁
        $this->check_often($user_id);
        $this->start_often($user_id);

        $tz_num = $this->request->post('pm_num',0);
        //判断数量
        if($tz_num < 1)
        {
            return  $this->withError('您输入的数量有误');
        }
        if(!is_numeric($tz_num))
        {
            return $this->withError('您输入的数量有误');
        }
        if($this->check_search_str('.',$tz_num))
        {
            return $this->withError('您输入的数量只能为整数');
        }

        $bilie = $this->request->post('bilie','');
        if($bilie <= 0)
        {
            return $this->withError('扑满兑通证的比列输入有误！');
        }
        if(!$this->checkPrice_4($bilie))
        {
            return $this->withError('扑满兑通证的比列只能填四位小数！');
        }

        //判断是否足够是否足够
        if($userInfo['puman'] < $tz_num)
        {
            $ge = $tz_num-$userInfo['puman'];
            return $this->withError('您还需要'.$ge.'个扑满才能上架！');
        }
        $pm = round($tz_num*$bilie,4);

        $re1 = DsUserPuman::del_puman($user_id,$tz_num,'上架'.$tz_num.'个扑满');
        if(!$re1)
        {
            return $this->withError('您的提交过快，请稍后再试');
        }
        $order_sn = $this->redis0->sPop('create_jiaoyi_num_qu');
        if(!$order_sn)
        {
            //生成一下
            $ree = make(RandomGeneration::class);
            $ree->save_jiaoyi_num();
            return $this->withError('您的提交过快，请稍后再试');
        }
        $data = [
            'user_id'       => $user_id,
            'order_sn'      => $order_sn,
            'tz_num'        => $pm,
            'pm_num'        => $tz_num,
            'day'           => $this->get_day(),
            'time'          => time(),
            'type'          => 1,
            'bilie'        => $bilie,
        ];
        try {
            $res = DsJiaoyiPm::query()->insertGetId($data);
            if($res)
            {
                //总求购+1
                $this->redis0->incrBy('pm_shangjia',(int)$tz_num);
                return $this->withSuccess('扑满上架成功');
            }else{
                //退回
                $tui = DsUserPuman::add_puman($user_id,$tz_num,'退回-上架'.$tz_num.'个扑满');
                if(!$tui)
                {
                    DsErrorLog::add_log('扑满上架','增加扑满失败',json_encode(['user_id' =>$user_id,'num' =>$tz_num,'cont' => '退回-上架'.$tz_num.'个扑满'  ]));
                }
                return $this->withError('您的提交过快，请稍后再试');
            }
        }catch (\Exception $exception)
        {
            var_dump($exception->getMessage());
            //退回
            $tui = DsUserPuman::add_puman($user_id,$tz_num,'退回-上架'.$tz_num.'个扑满');
            if(!$tui)
            {
                DsErrorLog::add_log('扑满上架','增加扑满失败',json_encode(['user_id' =>$user_id,'num' =>$tz_num,'cont' => '退回-上架'.$tz_num.'个扑满'  ]));
            }
            return $this->withError('您的提交过快，请稍后再试');
        }
    }

    /**
     * 通证置换
     * @RequestMapping(path="tz_displace",methods="post")
     * @RateLimit(create=1, consume=1, capacity=1, key={TransactionController::class, "_key"})
     * @return \Psr\Http\Message\ResponseInterface
     * @throws Exception
     */
    public function tz_displace()
    {
        $user_id      = Context::get('user_id');
        $userInfo     = Context::get('userInfo');

        $tz_displace_status = $this->redis0->get('tz_displace_status');
        if($tz_displace_status != 1)
        {
            return $this->withError('该功能暂时关闭,请耐心等待通知！');
        }
        //判断是否实名
        $this->_check_auth();

        //检查支付密码对不对
        $pay_password = $this->request->post('pay_password','');
        $this->_check_pay_password($pay_password,2);

        //检测请求频繁
        $this->check_often($user_id);
        $this->start_often($user_id);

        $jiaoyi_id = $this->request->post('jiaoyi_tz_id',0);
        //判断交易是否存在
        $jiaoyi_info = DsJiaoyiTz::query()->where('jiaoyi_tz_id',$jiaoyi_id)->first();
        if(empty($jiaoyi_info))
        {
            return $this->withError('该订单不存在');
        }

        //判断当前交易id是否在进行
        $this->check_jy_order($jiaoyi_id);
        $this->start_jy_order($jiaoyi_id);

        $jiaoyi_info = $jiaoyi_info->toArray();

        if($jiaoyi_info['type']  == 3)
        {
            return $this->withError('该订单取消');
        }
        if($jiaoyi_info['type']  == 4)
        {
            return $this->withError('该订单已完成');
        }

        //判断是否是自己
        if($user_id == $jiaoyi_info['user_id'])
        {
            return $this->withError('你不能交易自己的订单');
        }
        $num = $jiaoyi_info['pm_num'];
        //查询自己是否足够
        if($userInfo['puman'] < $num)
        {
            $ge = $num-$userInfo['puman'];
            return $this->withError('您还需要'.$ge.'个扑满才能置换！');
        }

        //扣除
        $res3 = DsUserPuman::del_puman($user_id,$num,'置换'.$num.'个扑满给用户['.$jiaoyi_info['user_id'].']');
        if(!$res3)
        {
            return $this->withError('您的提交过快，请稍后再试');
        }
        //1求购中 3已取消 4已完成
        $update = [
            'type'          => 4,
            'to_id'         => $user_id,
            'ok_time'       => time()
        ];
        //改变交易状态
        $res2 = DsJiaoyiTz::query()->where('jiaoyi_tz_id',$jiaoyi_id)->update($update);
        if($res2)
        {
            $jiaoyi_info['to_id'] = $user_id;
            //增加异步任务
            $infos = [
                'type'          => '14',
                'user_id'       => $user_id,
                'jiaoyi_info'   => $jiaoyi_info,
            ];
            $this->yibu($infos);

            return $this->withSuccess('置换成功');
        }else{
            //退回
            $erro223 = DsUserPuman::add_puman($user_id,$num,'退回-置换'.$num.'个扑满给用户 ['.$jiaoyi_info['user_id'].']');
            if(!$erro223)
            {
                DsErrorLog::add_log('交易卖出退回失败','增加扑满失败',json_encode(['user_id' =>$user_id,'num'=>$num,'cont'=> '退回-置换'.$num.'个扑满给用户 ['.$jiaoyi_info['user_id'].']' ]));
            }
            return $this->withError('当前人数较多,请稍后再试');
        }
    }

    /**
     * 扑满置换
     * @RequestMapping(path="pm_displace",methods="post")
     * @RateLimit(create=1, consume=1, capacity=1, key={TransactionController::class, "_key"})
     * @return \Psr\Http\Message\ResponseInterface
     * @throws Exception
     */
    public function pm_displace()
    {
        $user_id      = Context::get('user_id');
        $userInfo     = Context::get('userInfo');

        $pm_displace_status = $this->redis0->get('pm_displace_status');
        if($pm_displace_status != 1)
        {
            return $this->withError('该功能暂时关闭,请耐心等待通知！');
        }
        //判断是否实名
        $this->_check_auth();

        //检查支付密码对不对
        $pay_password = $this->request->post('pay_password','');
        $this->_check_pay_password($pay_password,2);

        //判断是否是会员和活跃值150
        $this->check_vip_and_huoyue150($user_id);

        //检测请求频繁
        $this->check_often($user_id);
        $this->start_often($user_id);

        $jiaoyi_id = $this->request->post('jiaoyi_pm_id',0);
        //判断交易是否存在
        $jiaoyi_info = DsJiaoyiPm::query()->where('jiaoyi_pm_id',$jiaoyi_id)->first();
        if(empty($jiaoyi_info))
        {
            return $this->withError('该订单不存在');
        }

        //判断当前交易id是否在进行
        $this->check_jy_order($jiaoyi_id,2);
        $this->start_jy_order($jiaoyi_id,2);

        $jiaoyi_info = $jiaoyi_info->toArray();

        if($jiaoyi_info['type']  == 3)
        {
            return $this->withError('该订单取消');
        }
        if($jiaoyi_info['type']  == 4)
        {
            return $this->withError('该订单已完成');
        }

        //判断是否是自己
        if($user_id == $jiaoyi_info['user_id'])
        {
            return $this->withError('你不能交易自己的订单');
        }
        $num = $jiaoyi_info['tz_num'];
        //查询自己是否足够
        if($userInfo['tongzheng'] < $num)
        {
            $ge = $num-$userInfo['tongzheng'];
            return $this->withError('您还需要'.$ge.'个通证才能置换！');
        }

        //扣除
        $res3 = DsUserTongzheng::del_tongzheng($user_id,$num,'置换'.$num.'个通证给用户['.$jiaoyi_info['user_id'].']');
        if(!$res3)
        {
            return $this->withError('您的提交过快，请稍后再试');
        }
        //1求购中 3已取消 4已完成
        $update = [
            'type'          => 4,
            'to_id'         => $user_id,
            'ok_time'       => time()
        ];
        //改变交易状态
        $res2 = DsJiaoyiPm::query()->where('jiaoyi_pm_id',$jiaoyi_id)->update($update);
        if($res2)
        {
            $jiaoyi_info['to_id'] = $user_id;
            //增加异步任务
            $infos = [
                'type'          => '15',
                'user_id'       => $user_id,
                'jiaoyi_info'   => $jiaoyi_info,
            ];
            $this->yibu($infos);

            return $this->withSuccess('置换成功');
        }else{
            //退回
            $erro223 = DsUserTongzheng::add_tongzheng($user_id,$num,'退回-置换'.$num.'个通证给用户 ['.$jiaoyi_info['user_id'].']');
            if(!$erro223)
            {
                DsErrorLog::add_log('交易卖出退回失败','增加通证失败',json_encode(['user_id' =>$user_id,'num'=>$num,'cont'=> '退回-置换'.$num.'个通证给用户 ['.$jiaoyi_info['user_id'].']' ]));
            }
            return $this->withError('当前人数较多,请稍后再试');
        }
    }

    /**
     * 通证上架取消
     * @RequestMapping(path="tz_call_off",methods="post")
     * @RateLimit(create=1, consume=1, capacity=1, key={TransactionController::class, "_key"})
     * @return \Psr\Http\Message\ResponseInterface
     * @throws Exception
     */
    public function tz_call_off()
    {
        $user_id      = Context::get('user_id');

        $jiaoyi_id = $this->request->post('jiaoyi_tz_id',0);
        //判断交易是否存在
        $jiaoyi_info = DsJiaoyiTz::query()->where('jiaoyi_tz_id',$jiaoyi_id)->first();
        if(empty($jiaoyi_info))
        {
            return $this->withError('该订单不存在');
        }

        //判断当前交易id是否在进行
        $this->check_jy_order($jiaoyi_id);
        $this->start_jy_order($jiaoyi_id);

        $jiaoyi_info = $jiaoyi_info->toArray();

        if($jiaoyi_info['type']  == 3)
        {
            return $this->withError('该订单取消');
        }
        if($jiaoyi_info['type']  == 4)
        {
            return $this->withError('该订单已完成');
        }
        if($jiaoyi_info['user_id'] != $user_id)
        {
            return $this->withError('你找错地方了~');
        }

        //检测请求频繁
        $this->check_often($user_id);
        $this->start_often($user_id);

        //判断时间是否满足10分钟
        if((time()-$jiaoyi_info['time']) < 600)
        {
            return $this->withError('未到取消时间');
        }
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
            $tui = DsUserTongzheng::add_tongzheng($user_id,$jiaoyi_info['tz_num'],'取消上架');
            if(!$tui)
            {
                DsErrorLog::add_log('取消上架','增加通证失败',json_encode(['user_id' =>$user_id,'num' =>$jiaoyi_info['tz_num'],'cont' => '取消上架'  ]));
            }
            return $this->withSuccess('操作成功');
        }else{
            return $this->withError('当前人数较多,请稍后再试');
        }
    }

    /**
     * 扑满上架取消
     * @RequestMapping(path="pm_call_off",methods="post")
     * @RateLimit(create=1, consume=1, capacity=1, key={TransactionController::class, "_key"})
     * @return \Psr\Http\Message\ResponseInterface
     * @throws Exception
     */
    public function pm_call_off()
    {
        $user_id      = Context::get('user_id');

        $jiaoyi_id = $this->request->post('jiaoyi_pm_id',0);
        //判断交易是否存在
        $jiaoyi_info = DsJiaoyiPm::query()->where('jiaoyi_pm_id',$jiaoyi_id)->first();
        if(empty($jiaoyi_info))
        {
            return $this->withError('该订单不存在');
        }

        //判断当前交易id是否在进行
        $this->check_jy_order($jiaoyi_id,2);
        $this->start_jy_order($jiaoyi_id,2);

        $jiaoyi_info = $jiaoyi_info->toArray();

        if($jiaoyi_info['type']  == 3)
        {
            return $this->withError('该订单取消');
        }
        if($jiaoyi_info['type']  == 4)
        {
            return $this->withError('该订单已完成');
        }
        if($jiaoyi_info['user_id'] != $user_id)
        {
            return $this->withError('你找错地方了~');
        }

        //检测请求频繁
        $this->check_often($user_id);
        $this->start_often($user_id);

        //判断时间是否满足10分钟
        if((time()-$jiaoyi_info['time']) < 600)
        {
            return $this->withError('未到取消时间');
        }
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
            $erro223 = DsUserPuman::add_puman($user_id,$num,'取消上架');
            if(!$erro223)
            {
                DsErrorLog::add_log('取消上架','增加扑满失败',json_encode(['user_id' =>$user_id,'num'=>$num,'cont'=> '取消上架' ]));
            }
            return $this->withSuccess('操作成功');
        }else{
            return $this->withError('当前人数较多,请稍后再试');
        }
    }

    /**
     * 判断是否请求过于频繁
     * @param $user_id
     * @throws Exception
     */
    protected function check_jy_order($user_id,$type = 1)
    {
        if($type == 1)
        {
            $name = 'start_jy_order_tz';
        }else{
            $name = 'start_jy_order_pm';
        }
        $aaa = $this->redis4->get($user_id.$name);
        if($aaa == 2)
        {
            throw new Exception('该订单正在操作,请耐心等待!', 10001);
        }
    }

    /**
     * 增加请求频繁
     * @param $user_id
     * @param int $time
     */
    protected function start_jy_order($user_id,$type=1,$time = 2)
    {
        if($type == 1)
        {
            $name = 'start_jy_order_tz';
        }else{
            $name = 'start_jy_order_pm';
        }
        $this->redis4->set($user_id.$name,2);
        $this->redis4->expire($user_id.$name,$time);
    }

    /**
     * 通证获取交易订单
     * @RequestMapping(path="tz_get_jiaoyi_info",methods="post")
     */
    public function tz_get_jiaoyi_info()
    {
        $user_id      = Context::get('user_id');
        $page     = $this->request->post('page',1); //页码
        if($page <= 0)
        {
            $page = 1;
        }
        $data['more'] = '0';
        $data['data'] = [];
        $type = $this->request->post('type',1);
        //type 1上架中 3已取消 4已完成
        $time = time();
        switch ($type)
        {
            case 1:
                $res = DsJiaoyiTz::query()
                    ->where(['user_id' => $user_id,'type'=>1])
                    ->forPage($page, 10)
                    ->select('jiaoyi_tz_id','order_sn','tz_num','pm_num','bilie','time')
                    ->orderByDesc('time')
                    ->get()->toArray();
                break;
            case 3:
                $res = DsJiaoyiTz::query()
                    ->where(['user_id' => $user_id,'type'=>3])
                    ->forPage($page, 10)
                    ->select('jiaoyi_tz_id','order_sn','tz_num','pm_num','bilie','time','off_time')
                    ->orderByDesc('off_time')
                    ->get()->toArray();
                break;
            case 4:
                $res = DsJiaoyiTz::query()
                    ->where(['user_id' => $user_id,'type'=>4])
                    ->forPage($page, 10)
                    ->select('jiaoyi_tz_id','order_sn','tz_num','pm_num','bilie','time','ok_time')
                    ->orderByDesc('ok_time')
                    ->get()->toArray();
                break;
            default:
                return $this->withError('类型选择错误');
                break;

        }
        if(!empty($res))
        {
            if(count($res) >= 10)
            {
                $data['more'] = '1';
            }
            //处理数据
            foreach ($res as $k => $v)
            {
                $res[$k]['time'] = $this->replaceTime($v['time']);

                if($type == 1)
                {
                    if(($v['time']+600)-$time > 0)
                    {
                        $res[$k]['time_off'] = strval(($v['time']+600)-$time);
                    }else{
                        $res[$k]['time_off'] = '0';
                    }
                }
                if($type == 3)
                {
                    $res[$k]['time'] = $this->replaceTime($v['off_time']);
                    unset($res[$k]['off_time']);
                }
                if($type == 4)
                {
                    $res[$k]['time'] = $this->replaceTime($v['ok_time']);
                    unset($res[$k]['ok_time']);
                }
            }
            $data['data'] = $res;
        }
        return $this->withResponse('获取成功',$data);
    }

    /**
     * 扑满获取交易订单
     * @RequestMapping(path="pm_get_jiaoyi_info",methods="post")
     */
    public function pm_get_jiaoyi_info()
    {
        $user_id      = Context::get('user_id');
        $page     = $this->request->post('page',1); //页码
        if($page <= 0)
        {
            $page = 1;
        }
        $data['more'] = '0';
        $data['data'] = [];
        $type = $this->request->post('type',1);
        //type 1上架中 3已取消 4已完成
        $time = time();
        switch ($type)
        {
            case 1:
                $res = DsJiaoyiPm::query()
                    ->where(['user_id' => $user_id,'type'=>1])
                    ->forPage($page, 10)
                    ->select('jiaoyi_pm_id','order_sn','tz_num','pm_num','bilie','time')
                    ->orderByDesc('time')
                    ->get()->toArray();
                break;
            case 3:
                $res = DsJiaoyiPm::query()
                    ->where(['user_id' => $user_id,'type'=>3])
                    ->forPage($page, 10)
                    ->select('jiaoyi_pm_id','order_sn','tz_num','pm_num','bilie','time','off_time')
                    ->orderByDesc('off_time')
                    ->get()->toArray();
                break;
            case 4:
                $res = DsJiaoyiPm::query()
                    ->where(['user_id' => $user_id,'type'=>4])
                    ->forPage($page, 10)
                    ->select('jiaoyi_pm_id','order_sn','tz_num','pm_num','bilie','time','ok_time')
                    ->orderByDesc('ok_time')
                    ->get()->toArray();
                break;
            default:
                return $this->withError('类型选择错误');
                break;

        }
        if(!empty($res))
        {
            if(count($res) >= 10)
            {
                $data['more'] = '1';
            }
            //处理数据
            foreach ($res as $k => $v)
            {
                $res[$k]['time'] = $this->replaceTime($v['time']);

                if($type == 1)
                {
                    if(($v['time']+600)-$time > 0)
                    {
                        $res[$k]['time_off'] = strval(($v['time']+600)-$time);
                    }else{
                        $res[$k]['time_off'] = '0';
                    }
                }
                if($type == 3)
                {
                    $res[$k]['time'] = $this->replaceTime($v['off_time']);
                    unset($res[$k]['off_time']);
                }
                if($type == 4)
                {
                    $res[$k]['time'] = $this->replaceTime($v['ok_time']);
                    unset($res[$k]['ok_time']);
                }
            }
            $data['data'] = $res;
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

    /**
     * 申请扑满储备初始化
     * @RequestMapping(path="apply_puman_cb_index",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function apply_puman_cb_index()
    {
        $data['address'] = '';
        $rr = DsPmUAddress::query()->where('type',1)->inRandomOrder()->value('address');
        if(!empty($rr))
        {
            $data['address'] = strval($rr);
        }
        return $this->withResponse('ok',$data);
    }

    /**
     * 申请扑满储备
     * @RequestMapping(path="apply_puman_cb",methods="post")
     * @RateLimit(create=1, consume=1, capacity=1, key={TransactionController::class, "_key"})
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function apply_puman_cb()
    {
        $user_id  = Context::get('user_id');

        $puman_cb_dh = $this->redis0->get('puman_cb_dh');
        if($puman_cb_dh != 1)
        {
            return $this->withError('该功能暂时关闭,请耐心等待通知！');
        }
        $user_address = $this->request->post('user_address','');
        if(empty($user_address))
        {
            return $this->withError('请填写地址');
        }
        $to_address = $this->request->post('to_address','');
        if(empty($to_address))
        {
            return $this->withError('请填写地址');
        }
        $puman = $this->request->post('puman','');
        if($puman < 1)
        {
            return  $this->withError('您输入的扑满数量有误');
        }
        if(!is_numeric($puman))
        {
            return $this->withError('您输入的扑满数量有误');
        }
        if($this->check_search_str('.',$puman))
        {
            return $this->withError('您输入的扑满数量只能为整数');
        }
        $u_address_id = DsPmUAddress::query()->where('type',1)
            ->where('address',$to_address)->value('u_address_id');
        if(!$u_address_id)
        {
            return $this->withError('你干啥？');
        }
        $status = 0;
        //判断上次订单是否处理
        $inf = DsPmCb::query()->where('user_id',$user_id)->orderByDesc('pm_cb_id')
            ->select('user_id','status')->first();
        if(!empty($inf))
        {
            $inf = $inf->toArray();
            if($inf['status'] == 0)
            {
                return $this->withError('您有未处理的订单！请前往订单查看处理！');
            }
            if($inf['status'] == 1)
            {
                return $this->withError('您有审核中的订单！请前往订单查看处理！');
            }
        }
        $inf2 = DsPmCb::query()->where('user_address',$user_address)->orderByDesc('pm_cb_id')
            ->select('user_id','status')->first();
        if(!empty($inf2))
        {
            $inf2 = $inf2->toArray();
            if($inf2['status'] == 0)
            {
                return $this->withError('您提交的钱包地址有未处理的订单！');
            }
            if($inf2['status'] == 1)
            {
                return $this->withError('您提交的钱包地址有审核中的订单！');
            }
        }
        $data = [
            'user_id'       => $user_id,
            'user_address'  => $user_address,
            'to_address'    => $to_address,
            'puman'         => $puman,
            'status'        => $status,
            'time'          => time(),
            'use_time'      => 0,
        ];
        $resid = DsPmCb::query()->insertGetId($data);
        if($resid)
        {
            //异步取消
            $info = [
                'type' => 18,
                'id' => $resid,
            ];
            $this->yibu($info,(int)$this->daojishi+1);
            return $this->withResponse('操作成功',['pm_cb_id' => $resid]);
        }else{
            return $this->withError('填写有误！');
        }
    }

    /**
     * 获取扑满储备日志
     * @RequestMapping(path="get_puman_cb_logs",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get_puman_cb_logs()
    {
        $user_id  = Context::get('user_id');

        $page     = $this->request->post('page',1);
        if($page <= 0)
        {
            $page = 1;
        }
        $data['more']           = '0';
        $data['data']           = [];

        $res = DsPmCb::query()->where('user_id',$user_id)
            ->orderByDesc('pm_cb_id')
            ->forPage($page,10)
            ->select('pm_cb_id','user_address','to_address','puman','status','time')
            ->get()->toArray();
        if(!empty($res))
        {
            //查找是否还有下页数据
            if(count($res) >= 10)
            {
                $data['more'] = '1';
            }
            $time = time();
            //整理数据
            foreach ($res as $k => $v)
            {
                $res[$k]['time']  = $this->replaceTime($v['time']);
                $res[$k]['daojishi'] = '0';
                if($v['status'] == 0)
                {
                    if(($v['time']+$this->daojishi)-$time > 0)
                    {
                        $res[$k]['daojishi'] = strval(($v['time']+$this->daojishi)-$time);
                    }else{
                        $res[$k]['daojishi'] = '0';
                    }
                    $res[$k]['time']  = $this->replaceTime($v['time']);
                }
            }
            $data['data']           = $res;
        }

        return $this->withResponse('获取成功',$data);
    }

    /**
     * 取消 确认储备
     * @RequestMapping(path="pm_off_chubei",methods="post")
     * @RateLimit(create=1, consume=1, capacity=1, key={TransactionController::class, "_key"})
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function pm_off_chubei()
    {
        $user_id  = Context::get('user_id');

        $pm_cb_id     = $this->request->post('pm_cb_id',0);
        $resss = DsPmCb::query()->where('pm_cb_id',$pm_cb_id)->where('user_id',$user_id)->first();
        if(empty($resss))
        {
            return $this->withError('找不到该订单！');
        }
        $resss = $resss->toArray();
        $type = $this->request->post('type',1);// 1成功 2取消
        if($type == 1)
        {
            if($resss['status'] == 1)
            {
                return $this->withSuccess('操作成功,等待系统审核');
            }
            if($resss['status'] == 2)
            {
                return $this->withError('该订单已完成');
            }
            if($resss['status'] == 3)
            {
                return $this->withError('该订单已取消');
            }
            $res = DsPmCb::query()->where('pm_cb_id',$pm_cb_id)->update(['status' => 1]);
        }else{
//            return $this->withError('请耐心等待！');
            if($resss['status'] == 1)
            {
                return $this->withError('该订单审核中');
            }
            if($resss['status'] == 2)
            {
                return $this->withError('该订单已完成');
            }
            if($resss['status'] == 3)
            {
                return $this->withError('该订单已取消');
            }
            $res = DsPmCb::query()->where('pm_cb_id',$pm_cb_id)->update(['status' => 3]);
        }
        if($res)
        {
            return $this->withSuccess('操作成功');
        }else{
            return $this->withSuccess('操作过快');
        }
    }

    /**
     * 取消 确认储备2
     * @RequestMapping(path="pm_off_chubei_2",methods="post")
     * @RateLimit(create=1, consume=1, capacity=1, key={TransactionController::class, "_key"})
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function pm_off_chubei_2()
    {
        $user_id  = Context::get('user_id');

        $pm_cb_id     = $this->request->post('pm_cb_id',0);
        $resss = DsPmCb::query()->where('pm_cb_id',$pm_cb_id)->where('user_id',$user_id)->first();
        if(empty($resss))
        {
            return $this->withError('找不到该订单！');
        }
        $resss = $resss->toArray();
        $type = $this->request->post('type',1);// 1成功 2取消
        $haxi = $this->request->post('haxi','');
        if($type == 1)
        {
            if(empty($haxi))
            {
                return $this->withError('请输入哈希！');
            }
            if($resss['status'] == 1)
            {
                return $this->withSuccess('操作成功,等待系统审核');
            }
            if($resss['status'] == 2)
            {
                return $this->withError('该订单已完成');
            }
            if($resss['status'] == 3)
            {
                return $this->withError('该订单已取消');
            }
            $res = DsPmCb::query()->where('pm_cb_id',$pm_cb_id)->update(['status' => 1,'haxi' =>$haxi ]);
        }else{
//            return $this->withError('请耐心等待！');
            if($resss['status'] == 1)
            {
                return $this->withError('该订单审核中');
            }
            if($resss['status'] == 2)
            {
                return $this->withError('该订单已完成');
            }
            if($resss['status'] == 3)
            {
                return $this->withError('该订单已取消');
            }
            $res = DsPmCb::query()->where('pm_cb_id',$pm_cb_id)->update(['status' => 3,'haxi' =>$haxi]);
        }
        if($res)
        {
            return $this->withSuccess('操作成功');
        }else{
            return $this->withSuccess('操作过快');
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