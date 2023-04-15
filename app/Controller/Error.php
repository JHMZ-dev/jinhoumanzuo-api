<?php declare(strict_types=1);

namespace App\Controller;


use App\Job\Async;
use App\Model\DsErrorLog;
use App\Model\DsUserYingpiao;
use Hyperf\AsyncQueue\Driver\DriverFactory;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\Redis\RedisFactory;
use Hyperf\Utils\ApplicationContext;


/**
 * 处理问题
 * @package App\Error
 * @Controller(prefix="error_edit")
 * Class UpdateController
 */
class Error extends XiaoController
{
    protected $user_id;


    /**
     * 补影票
     * @RequestMapping(path="bu_yingpiao",methods="get,post")
     */
    public function bu_yingpiao()
    {
        $num = 0;
        $rev = DsErrorLog::query()
            ->where('error_log_status',0)
//            ->where('error_log_type','储存罐释放')
            ->where('error_log_info','增加影票失败')
            ->limit(100)
            ->select('error_log_id','error_log_cont')->get()->toArray();
        if(!empty($rev))
        {
            foreach ($rev as $v)
            {
                $reg = DsErrorLog::query()->where('error_log_id',$v['error_log_id'])->update(['error_log_status' => 1]);
                if($reg)
                {
                    $arr = json_decode($v['error_log_cont'],true);
                    $retgsd = DsUserYingpiao::add_yingpiao($arr['user_id'],$arr['num'],'漏掉自动补-'.$arr['cont']);
                    if($retgsd)
                    {
                        $num+=1;
                    }else{
                        DsErrorLog::query()->where('error_log_id',$v['error_log_id'])->update(['error_log_status' => 0]);
                    }
                }
            }
        }
        return $this->withSuccess('ok:'.$num);
    }

    protected static function yibu22($info2, $tt = 0)
    {
        $redis0 = ApplicationContext::getContainer()->get(RedisFactory::class)->get('db0');
        $db = $redis0->get('async_db');
        $redis = ApplicationContext::getContainer()->get(\Redis::class);
        $redis->select((int) $db);
        $job = ApplicationContext::getContainer()->get(DriverFactory::class);
        if ($tt == 0) {
            $job->get('async')->push(new Async($info2));
        } else {
            $job->get('async')->push(new Async($info2), (int) $tt);
        }
    }
}