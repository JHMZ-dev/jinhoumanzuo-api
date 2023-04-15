<?php declare(strict_types=1);

namespace App\Controller\async;


use App\Model\DsUser;
use App\Model\DsUserGongxianzhi;
use Hyperf\Redis\RedisFactory;
use Hyperf\Utils\ApplicationContext;
/**
 * 贡献值
 * Class GongXian
 * @package App\Controller
 */
class GongXian
{
    // 保存错误信息

    protected $user_id;
    protected $redis0;
    protected $redis5;
    protected $redis6;
    protected $redis4;
    protected $gongxianzhi;
    public function __construct()
    {
        $this->redis0 = ApplicationContext::getContainer()->get(RedisFactory::class)->get('db0');
        $this->redis5 = ApplicationContext::getContainer()->get(RedisFactory::class)->get('db5');
        $this->redis4 = ApplicationContext::getContainer()->get(RedisFactory::class)->get('db4');
        $this->redis6 = ApplicationContext::getContainer()->get(RedisFactory::class)->get('db6');
    }

    /**
     * 增加贡献值
     * @param $user_id
     * @param $gongxianzhi
     * @param $cont
     * @param $cont2
     */
    public function add_gongxianzhi($user_id,$gongxianzhi,$cont)
    {
        if($gongxianzhi > 0)
        {
            $gongxianzhi = (int)$gongxianzhi;
            //增加个人
            $this->redis5->incrBy($user_id.'_gongxianzhi' ,$gongxianzhi);
            //增加日志
            DsUserGongxianzhi::add_log($user_id,1,$gongxianzhi,$cont);

            $pid = $this->redis5->get($user_id.'_pid');
            if($pid > 0)
            {
                //增加上级团队贡献值
                $this->team_gongxianzhi($user_id,$gongxianzhi);
            }
        }
    }

    /**
     * 增加团队贡献值
     * @param $user_id
     * @param $gongxianzhi
     */
    public function team_gongxianzhi($user_id,$gongxianzhi)
    {
        $pid =  $this->redis5->get($user_id.'_pid');
        if($pid > 0)
        {
            //为上级增加团队贡献值
            $this->redis5->incrBy($pid.'_team_gongxianzhi' ,$gongxianzhi);

            //计算大区贡献值
            $this->jian_daqu_gongxianzhi($pid,$user_id);

            $this->team_gongxianzhi($pid,$gongxianzhi);
        }
    }

    /**
     * 计算上级的大区贡献值id是否变化
     * @param $user_id
     * @param $sid
     */
    protected function jia_daqu_gongxianzhi($user_id,$sid)
    {
        var_dump('user_id:'.$user_id);
        var_dump('sid:'.$sid);
        //查询他的大区贡献值id是谁
        $_daqu_gongxianzhi_user_id = $this->redis5->get($user_id.'_daqu_gongxianzhi_user_id');
        if(!$_daqu_gongxianzhi_user_id)
        {
            var_dump('不存在设定：'.$_daqu_gongxianzhi_user_id);
            //设置大区id
            $this->redis5->set($user_id.'_daqu_gongxianzhi_user_id',$sid);
        }else{
            var_dump('存在打印：'.$_daqu_gongxianzhi_user_id);
            var_dump('存在打印：'.$sid);
            //判断第一大区是否是这个用户
            if($_daqu_gongxianzhi_user_id != $sid)
            {
                //判断第二大区是否存在
                $_daqu_gongxianzhi_user_id_2 = $this->redis5->get($user_id.'_daqu_gongxianzhi_user_id_2');
                var_dump('打印第二大区：'.$_daqu_gongxianzhi_user_id_2);
                if(!$_daqu_gongxianzhi_user_id_2)
                {
                    var_dump('不存在设定：'.$_daqu_gongxianzhi_user_id_2);
                    //设置第二大区id
                    $this->redis5->set($user_id.'_daqu_gongxianzhi_user_id_2',$sid);
                }else{
                    //查找大区id团队贡献值
                    $da_ren = $this->redis5->get($_daqu_gongxianzhi_user_id.'_team_gongxianzhi')+$this->redis5->get($_daqu_gongxianzhi_user_id.'_gongxianzhi');
                    //查找子id大区贡献值
                    $zi_ren = $this->redis5->get($sid.'_team_gongxianzhi')+$this->redis5->get($sid.'_gongxianzhi');
                    if($zi_ren > $da_ren)
                    {
                        var_dump('打印-zi_ren：'.$zi_ren);
                        var_dump('打印-da_ren：'.$da_ren);
                        //设置新大区贡献值id
                        $this->redis5->set($user_id.'_daqu_gongxianzhi_user_id',$sid);
                    }else{
                        //查找第二子id大区贡献值
                        $da_ren_2 = $this->redis5->get($_daqu_gongxianzhi_user_id_2.'_team_gongxianzhi')+$this->redis5->get($_daqu_gongxianzhi_user_id_2.'_gongxianzhi');
                        if($zi_ren > $da_ren_2)
                        {
                            var_dump('打印-zi_ren：'.$zi_ren);
                            var_dump('打印-da_ren2：'.$da_ren_2);
                            //设置新大区贡献值id
                            $this->redis5->set($user_id.'_daqu_gongxianzhi_user_id_2',$sid);
                        }
                    }
                }
            }
        }
    }

    /**
     * 减少贡献值
     * @param $user_id
     * @param $gongxianzhi
     * @param $cont
     */
    public function del_gongxianzhi($user_id,$gongxianzhi,$cont)
    {
        if($gongxianzhi > 0)
        {
            $gongxianzhi = (int)$gongxianzhi;
            //减少个人
            $this->redis5->decrBy($user_id.'_gongxianzhi' ,$gongxianzhi);
            //增加日志
            DsUserGongxianzhi::add_log($user_id,2,$gongxianzhi,$cont);

            $pid = $this->redis5->get($user_id.'_pid');
            if($pid > 0)
            {
                //减少上级团队贡献值
                $this->del_team_gongxianzhi($user_id,$gongxianzhi);
            }
        }
    }

    /**
     * 减少团队贡献值
     * @param $user_id
     * @param $gongxianzhi
     */
    public function del_team_gongxianzhi($user_id,$gongxianzhi)
    {
        $pid =  $this->redis5->get($user_id.'_pid');
        if($pid > 0)
        {
            $this->redis5->decrBy($pid.'_team_gongxianzhi' ,$gongxianzhi);
            $this->jian_daqu_gongxianzhi($pid,$user_id);
            $this->del_team_gongxianzhi($pid,$gongxianzhi);
        }
    }

    /**
     * 计算上级的大区贡献值id是否变化
     * @param $user_id
     * @param $sid
     */
    protected function jian_daqu_gongxianzhi($user_id,$sid)
    {
        //查找直推下所有团队信息
        $user_ids = DsUser::query()->where('pid',$user_id)->pluck('user_id')->toArray();
        if(!empty($user_ids))
        {
            $data = [];
            foreach ($user_ids as $k =>  $v)
            {
                //查找大区id团队算力
                $gongxianzhi = $this->redis5->get($v.'_team_gongxianzhi')+$this->redis5->get($v.'_gongxianzhi');
                $data[$k]['user_id'] = $v;
                $data[$k]['gongxianzhi'] = $gongxianzhi;
            }
            if(!empty($data))
            {
                $data = $this->arr_paixu($data,'gongxianzhi');
                if(!empty($data[0]['user_id']))
                {
                    //设置大区id
                    $this->redis5->set($user_id.'_daqu_gongxianzhi_user_id',$data[0]['user_id']);
                }
                if(!empty($data[1]['user_id']))
                {
                    //设置大区id
                    $this->redis5->set($user_id.'_daqu_gongxianzhi_user_id_2',$data[1]['user_id']);
                }
            }
        }
    }

    /*
     * 以倒序/顺序的方式排序指定数组值的内容
     * @param $data           //数据
     * @param $zhi            //指定值
     * @param bool $fangshi   //方式 true 正序  false 倒叙
     * @return mixed
     */
    protected function arr_paixu($data,$zhi,$fangshi = false)
    {
        // 取得列的列表
        foreach ($data as $key => $row)
        {
            $volume[$key]  = $row[$zhi];
        }
        if($fangshi){
            array_multisort($volume, SORT_ASC, $data);
        }else{
            array_multisort($volume, SORT_DESC, $data);
        }
        return $data;
    }
}