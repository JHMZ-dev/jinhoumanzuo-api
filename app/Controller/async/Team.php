<?php declare(strict_types=1);

namespace App\Controller\async;

use Hyperf\DbConnection\Db;
use Hyperf\Guzzle\ClientFactory;
use Hyperf\Redis\RedisFactory;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Context;
use Hyperf\Utils\Parallel;

/**
 * 团队的所有方法
 * Class Team
 * @package App\Controller
 */
class Team
{

    protected $redis0;
    protected $redis5;
    protected $redis6;
    protected $user_id;
    protected $http;
    public function __construct()
    {
        $this->redis0 = ApplicationContext::getContainer()->get(RedisFactory::class)->get('db0');
        $this->redis5 = ApplicationContext::getContainer()->get(RedisFactory::class)->get('db5');
        $this->redis6 = ApplicationContext::getContainer()->get(RedisFactory::class)->get('db6');
        $this->http   = ApplicationContext::getContainer()->get(ClientFactory::class)->create();
    }

    //增加团队指定等级人数
    public function add_team_group($user_id,$group)
    {
        $this->add_team_group_do($user_id,$group);
    }

    //为所有上级团队用户表加指定等级人数
    protected function add_team_group_do($user_id,$group)
    {
        $pid =  $this->redis5->get($user_id.'_pid');
        if($pid > 0)
        {
            $this->redis5->incr($pid.'_group_'.$group.'_num');
            $this->add_team_group_do($pid,$group);
        }
    }

    //减少团队指定等级人数
    public function del_team_group($user_id,$group)
    {
        $this->del_team_group_do($user_id,$group);
    }

    //为所有上级团队用户减少指定等级人数
    protected function del_team_group_do($user_id,$group)
    {
        $pid =  $this->redis5->get($user_id.'_pid');
        if($pid > 0)
        {
            $this->redis5->decr($pid.'_group_'.$group.'_num');
            $this->del_team_group_do($pid,$group);
        }
    }
}