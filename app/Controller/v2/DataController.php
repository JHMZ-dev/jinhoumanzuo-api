<?php declare(strict_types=1);

namespace App\Controller\v2;


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
 * @package App\Controller\v2
 * @Controller(prefix="v2/data")
 */
class DataController extends XiaoController
{

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

        $love_arr = [
            '0' => 0,
            '1' => 0,
            '2' => 0,
            '3' => 0,
            '4' => 0,
            '5' => 0,
            '6' => 0,
            '7' => 0,
            '8' => 0,
            '9' => 0,
            '10' => 0,
            '11' => 0,
            '12' => 0,
            '13' => 0,
            '14' => 0,
        ];
        $userInfo_love = $userInfo['love']<0?0:$userInfo['love'];
        if($userInfo_love > 0){
            for ($i=0;$i<$userInfo_love;$i++){
                $love_arr[$i] = 1;
            }
        }
        $data['love_array'] = $love_arr;
        $data['love'] = $userInfo_love;

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
     * 2023-4-6 开始下面
     * 扑满赎回
     * @RequestMapping(path="puman_sh_do",methods="post")
     * @RateLimit(create=1, consume=1, capacity=1, key={DataController::class, "_key"})
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function puman_sh_do()
    {
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
        //验证码校验
        $code = $this->request->post('code','');
        if(empty($code))
        {
            return $this->withError('验证码不能为空');
        }
        $this->_checkCode($userInfo['mobile'],'puman_sh_do',$code,time(),$user_id);

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
     * 影票兑通证
     * @RequestMapping(path="yp_to_tz",methods="post")
     * @RateLimit(create=1, consume=1, capacity=1, key={DataController::class, "_key"})
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function yp_to_tz()
    {
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
        //验证码校验
        $code = $this->request->post('code','');
        if(empty($code))
        {
            return $this->withError('验证码不能为空');
        }
        $this->_checkCode($userInfo['mobile'],'yp_to_tz',$code,time(),$user_id);

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
        //验证码校验
        $code = $this->request->post('code','');
        if(empty($code))
        {
            return $this->withError('验证码不能为空');
        }
        $this->_checkCode($userInfo['mobile'],'tz_to_yp',$code,time(),$user_id);

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
     * 通证数据-回收
     * @RequestMapping(path="tz_hs_do",methods="post")
     * @RateLimit(create=1, consume=1, capacity=1, key={DataController::class, "_key"})
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function tz_hs_do()
    {
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

        //验证码校验
        $code = $this->request->post('code','');
        if(empty($code))
        {
            return $this->withError('验证码不能为空');
        }
        $this->_checkCode($userInfo['mobile'],'tz_hs_do',$code,time(),$user_id);

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
     * 返回用户id
     * @return string
     */
    public static function _key(): string
    {
        $user_id    = Context::get('user_id');
        return strval($user_id);
    }
}