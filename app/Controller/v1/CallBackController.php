<?php declare(strict_types=1);

namespace App\Controller\v1;

use App\Controller\XiaoController;
use App\Model\DsUserDatum;
use App\Model\DsUserHuoyuedu;
use App\Model\DsUserTask;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Annotation\Middleware;
use App\Middleware\SignatureMiddleware;
use Hyperf\Utils\Context;
use Hyperf\RateLimit\Annotation\RateLimit;

/**
 * 广告回调接口
 * Class CallBackController
 * @Middleware(SignatureMiddleware::class)
 * @package App\Controller\v1
 * @Controller(prefix="v1/callback")
 */
class CallBackController extends XiaoController
{

    /**
     * 任务广告回调
     * @RequestMapping(path="renwu_callback",methods="post")
     * @RateLimit(create=1, consume=1, capacity=1, key={CallBackController::class, "_key"})
     */
    public function renwu_callback()
    {
        $user_id   = Context::get('user_id');
        if(!$user_id)
        {
            return $this->withError('请先登录！');
        }
        $day = date('Y-m-d');
        //判断今日是否领取
        $name = $user_id.'_'.$day.'_app_renwu';
        $namesss = $name.'_status';
        $ecv = $this->redis4->get($namesss);
        if($ecv == 2)
        {
            return $this->withError('今日已领取');
        }
        $name1 = $name.'_ci';
//        $cont = $this->redis4->get($name1);
//        $name3 = $name.'_check';
//        $cont3 = $this->redis4->get($name3);
//        if($cont == $cont3)
//        {
//            //验证是否通过了滑动验证
//            $name4 = $name.'_dingxiang';
//            $cont4 = $this->redis4->get($name4);
//            if($cont4 != 2)
//            {
//                return $this->withError('验证失败了！');
//            }
//        }

        $this->redis4->incr($name1);
        $this->redis4->expire($name1,86400);

        return $this->withSuccess('已成功看完一次广告');
    }


    /**
     * 活跃度广告回调
     * @RequestMapping(path="huoyuedu_callback",methods="post")
     * @RateLimit(create=1, consume=1, capacity=1, key={CallBackController::class, "_key"})
     */
    public function huoyuedu_callback()
    {
        $user_id   = Context::get('user_id');
        if(!$user_id)
        {
            return $this->withError('请先登录！');
        }
        $day = date('Y-m-d');
        $user_task_id = $this->request->post('user_task_id',0);

        $rr = DsUserTask::query()->where('user_task_id',$user_task_id)->first();
        if(empty($rr))
        {
            return $this->withError('找不到该任务');
        }
        //判断今日是否领取
        $name = $user_id.'_'.$day.'huoyuedu_callback_'.$user_task_id;
        $ecv = $this->redis4->get($name);
        if($ecv == 2)
        {
            return $this->withError('今日已领取!');
        }

        $this->redis4->set($name,2);
        $this->redis4->expire($name,86400);
        //增加2.5
        if($user_task_id == 1)
        {
            $resd = DsUserDatum::add_log($user_id,3,2);
            if($resd)
            {
                //增加日志
                DsUserHuoyuedu::add_log($user_id,1,3,'完成活跃度任务');
            }
            return $this->withSuccess('恭喜完成任务获得3活跃');
        }else{
            $resd = DsUserDatum::add_log($user_id,2,2);
            if($resd)
            {
                //增加日志
                DsUserHuoyuedu::add_log($user_id,1,2,'完成活跃度任务');
            }
            return $this->withSuccess('恭喜完成任务获得2活跃');
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