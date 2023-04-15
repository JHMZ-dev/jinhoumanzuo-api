<?php declare(strict_types=1);

namespace App\Controller\async;

use Hyperf\Redis\RedisFactory;
use Hyperf\Utils\ApplicationContext;
use Swoole\Exception;

/**
 * 生成永不重复的订单号
 * Class OrderSn
 * @package App\Controller
 */
class OrderSn
{
    protected $redis0;
    protected $redis5;
    protected $redis6;
    protected $num = 10; //重复执行的次数
    protected $start =1; //开始次数
    public function __construct()
    {
        $this->redis0 = ApplicationContext::getContainer()->get(RedisFactory::class)->get('db0');
        $this->redis5 = ApplicationContext::getContainer()->get(RedisFactory::class)->get('db5');
        $this->redis6 = ApplicationContext::getContainer()->get(RedisFactory::class)->get('db6');
    }

    /**
     * 生成每日永不重复的订单号
     * @return string
     * @throws Exception
     */
    public function createOrderSN()
    {
        $order_sn =   date('Ymd').(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 14), 1)))).mt_rand(100000,999999);
        //查询订单号是否存在
        $name = date('Ymd').'_order_sn';
        $res = $this->redis0->sIsMember($name,$order_sn);
        if($res)
        {
            //判断执行次数
            if($this->start >= $this->num)
            {
                throw new Exception('当前生成订单人数较多,请稍后再试', 10001);
            }
            //执行重试
            $this->start += 1;
            $this->createOrderSN();
        }else{
            //添加
            $this->redis0->sAdd($name,$order_sn);
            return $order_sn;
        }
    }

    /**
     * 生成人脸订单号
     * @return string
     */
    public function createOrderSNAuth()
    {
         return date('Ymd').(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1)))).rand(1000, 9999);
    }
    /**
     * 生成永不重复的订单号ws
     * @return string
     */
    public function createOrderSNWs()
    {
        $order_sn =   rand(1000, 9999).date('Ymd').(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1)))).rand(1000, 9999);
        //查询订单号是否存在
        $res = $this->redis0->sIsMember('all_order_sn',$order_sn);
        if($res)
        {
            $this->createOrderSNWs();
        }else{
            //添加
            $this->redis0->sAdd('all_order_sn',$order_sn);
            return $order_sn;
        }
    }

    /**
     * 生成永不重复的群号码
     * @return string
     * @throws Exception
     */
    public function create_group_num()
    {
        $num  =  date('Ymd').mt_rand(100000, 999999);
        //查询是否存在
        $res = $this->redis0->sIsMember('all_group_num',$num);
        if($res)
        {
            //判断执行次数
            if($this->start >= $this->num)
            {
                throw new Exception('创建群聊的人太多,请稍后再试', 10001);
            }
            //执行重试
            $this->start += 1;
            $this->create_group_num();
        }else{
            //添加
            $this->redis0->sAdd('all_group_num',$num);
            return $num;
        }
    }
}