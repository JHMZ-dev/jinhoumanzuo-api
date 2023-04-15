<?php declare(strict_types=1);

namespace App\Controller\async;


use App\Model\DsUser;
use App\Model\DsUserHuoyuedu;
use Hyperf\Redis\RedisFactory;
use Hyperf\Utils\ApplicationContext;
/**
 * 活跃度
 * Class HuoYue
 * @package App\Controller
 */
class HuoYue
{
    // 保存错误信息

    protected $user_id;
    protected $redis0;
    protected $redis5;
    protected $redis6;
    protected $huoyue;
    public function __construct()
    {
        $this->redis0 = ApplicationContext::getContainer()->get(RedisFactory::class)->get('db0');
        $this->redis5 = ApplicationContext::getContainer()->get(RedisFactory::class)->get('db5');
        $this->redis6 = ApplicationContext::getContainer()->get(RedisFactory::class)->get('db6');
    }


    /**
     * 增加活跃
     * @param $user_id
     * @param $huoyue
     * @param $cont
     * @param $cont2
     */
    public function add_huoyue($user_id,$huoyue,$cont,$cont2)
    {
        if($huoyue > 0)
        {
            //增加个人活跃
            $this->add_huoyue_and_log($user_id,$huoyue,$cont);

            //增加上级
            $pid = $this->redis5->get($user_id.'_pid');
            if($pid > 0)
            {
                $this->add_huoyue_and_log($pid,$huoyue*0.4,$cont2);

                //增加上上级
                $ppid = $this->redis5->get($pid.'_pid');
                if($ppid > 0)
                {
                    $this->add_huoyue_and_log($ppid,$huoyue*0.1,$cont2);
                }
            }
        }
    }

    /**
     *
     * @param $user_id
     * @param $huoyue
     * @param $cont
     */
    protected function add_huoyue_and_log($user_id,$huoyue,$cont)
    {
        //增加个人活跃
        $this->redis5->incrBy($user_id.'_huoyue' ,(int)$huoyue);
        //增加日志
        DsUserHuoyuedu::add_log($user_id,1,$huoyue,$cont);
    }


    /**
     * 减少活跃
     * @param $user_id
     * @param $huoyue
     * @param $cont
     * @param $cont2
     */
    public function huoyue_del($user_id,$huoyue,$cont,$cont2)
    {
        if($huoyue > 0)
        {
            //减少个人活跃
            $this->del_huoyue_and_log($user_id,$huoyue,$cont);

            //减少上级
            $pid = $this->redis5->get($user_id.'_pid');
            if($pid > 0)
            {
                $this->del_huoyue_and_log($pid,$huoyue*0.4,$cont2);

                //减少上上级
                $ppid = $this->redis5->get($pid.'_pid');
                if($ppid > 0)
                {
                    $this->del_huoyue_and_log($ppid,$huoyue*0.1,$cont2);
                }
            }
        }
    }
    /**
     * 减少活跃
     * @param $user_id
     * @param $huoyue
     * @param $cont
     * @param $cont2
     */
    public function huoyue_del_2($user_id,$huoyue,$cont,$cont2)
    {
        if($huoyue > 0)
        {
            //减少上级
            $pid = $this->redis5->get($user_id.'_pid');
            if($pid > 0)
            {
                $this->del_huoyue_and_log($pid,$huoyue*0.4,$cont2);

                //减少上上级
                $ppid = $this->redis5->get($pid.'_pid');
                if($ppid > 0)
                {
                    $this->del_huoyue_and_log($ppid,$huoyue*0.1,$cont2);
                }
            }
        }
    }

    /**
     *
     * @param $user_id
     * @param $huoyue
     * @param $cont
     */
    protected function del_huoyue_and_log($user_id,$huoyue,$cont)
    {
        //减少个人活跃
        $this->redis5->decrBy($user_id.'_huoyue' ,(int)$huoyue);
        //增加日志
        DsUserHuoyuedu::add_log($user_id,2,$huoyue,$cont);
    }
}