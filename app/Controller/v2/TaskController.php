<?php declare(strict_types=1);

namespace App\Controller\v2;


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
 * @package App\Controller\v2
 * @Controller(prefix="v2/task")
 */
class TaskController extends XiaoController
{

    /**
     * 兑换流量
     * @RequestMapping(path="rent",methods="post")
     * @RateLimit(create=1, consume=1, capacity=1, key={TaskController::class, "_key"})
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Swoole\Exception
     */
    public function rent()
    {
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
        //验证码校验
        $code = $this->request->post('code','');
        if(empty($code))
        {
            return $this->withError('验证码不能为空');
        }
        $this->_checkCode($userInfo['mobile'],'rent',$code,time(),$user_id);

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
     * 返回用户id
     * @return string
     */
    public static function _key(): string
    {
        $user_id    = Context::get('user_id');
        return strval($user_id);
    }
}