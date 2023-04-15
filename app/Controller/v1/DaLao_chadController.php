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
 * Class DaLao_chadController
 * @Middleware(UpdateMiddleware::class)
 * @package App\Controller\v1
 * @Controller(prefix="system_chad")
 */
class DaLao_chadController extends XiaoController
{
    /**
     * 禁用/解除该ip下所有注册ip用户
     * @RequestMapping(path="jinri_ip_reg",methods="post")
     */
    public function jinri_ip_reg()
    {

        $user_id = $this->request->post('ip',0);
        if(!$user_id)
        {
            return $this->withError('找不到IP');
        }
        $bt = $this->request->post('bt','');
        if(!$bt)
        {
            return $this->withError('找不到时间');
        }
        $begintime1 = strtotime($bt);//开始时间
        $end_time1 = strtotime(date('Y-m-d 23:59:59', $begintime1));//结束时间

        $type = $this->request->post('type',0);
        if(empty($type)){
            //return $this->withError('找不到类型');
        }

        $_team_user = DsUser::query()
            ->where('ip',$user_id)
            ->where('reg_time','>=',$begintime1)
            ->where('reg_time','<=',$end_time1)
            ->pluck('ip')->toArray();
        if(!empty($_team_user))
        {
            //查询用户状态
            $_zhi_login_status = $this->redis2->get($user_id.'_admin_feng_'.$bt);

            if($_zhi_login_status == 1)
            {
                $this->redis2->del($user_id.'_admin_feng_'.$bt);
                //开启
                DsUser::query()->whereIn('ip',$_team_user)->update(['login_status' =>0 ]);
            }else{
                $this->redis2->set($user_id.'_admin_feng_'.$bt,1);
                //禁止
                DsUser::query()->whereIn('ip',$_team_user)->update(['login_status' =>1 ]);
            }
        }
        return $this->withSuccess('操作成功');
    }
}