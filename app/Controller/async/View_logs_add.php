<?php declare(strict_types=1);

namespace App\Controller\async;

use App\Model\DsViewlog;
use Hyperf\Redis\RedisFactory;
use Hyperf\Utils\ApplicationContext;

/**
 * 写入请求日志
 * Class RegUser
 * @package App\Controller
 */
class View_logs_add
{
    protected $redis4;
    public function __construct()
    {
        $this->redis0 = ApplicationContext::getContainer()->get(RedisFactory::class)->get('db0');
    }
    /**
     * 处理
     * @param $info
     */
    public function add($info)
    {
        $day = date('Y-m-d');
        //接口调用次数加1
        $this->redis0->incr($day.'_jiekou');
        //写入请求日志
        DsViewlog::query()->insert($info);
        //记录每日活跃用户
        $user_id = strval($info['user_id']);
        if($user_id > 0)
        {
            //判断今日是否已写
            $ifs = $this->redis0->sIsMember($day.'_user',$user_id);
            if($ifs == false)
            {
                //增加
                $this->redis0->sAdd($day.'_user',$user_id);
            }
        }
    }
}