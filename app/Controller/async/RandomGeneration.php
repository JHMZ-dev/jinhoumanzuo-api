<?php declare(strict_types=1);

namespace App\Controller\async;

use Hyperf\Redis\RedisFactory;
use Hyperf\Utils\ApplicationContext;
use Swoole\Exception;

/**
 * 生成随机码
 * Class RandomGeneration
 * @package App\Controller
 */
class RandomGeneration
{
    protected $redis0;
    protected $jiaoyi_num;
    protected $jiaoyi_num_end;
    protected $reg_num;
    protected $reg_num_end;
    public function __construct()
    {
        $this->redis0 = ApplicationContext::getContainer()->get(RedisFactory::class)->get('db0');
    }


    /**
     * 生成永不重复的交易订单号
     * @param int $end
     */
    public function save_jiaoyi_num($end = 1000)
    {
        if(is_numeric($end) && $end > 0)
        {
            $this->jiaoyi_num = 0;
            $this->jiaoyi_num_end = (int)$end;
            $this->save_jiaoyi_num_do();
        }
    }

    /**
     * 生成永不重复的注册码
     * @param int $end
     */
    public function save_reg_num($end = 1000)
    {
        if(is_numeric($end) && $end > 0)
        {
            $this->reg_num = 0;
            $this->reg_num_end = (int)$end;
            $this->save_reg_num_do();
        }
    }


    protected function save_jiaoyi_num_do()
    {
        if($this->jiaoyi_num < $this->jiaoyi_num_end)
        {
            $num = self::create_jiaoyi_num(18);
            $rr = $this->redis0->sAdd('create_jiaoyi_num',$num);
            if($rr)
            {
                $this->jiaoyi_num +=1;
                $this->redis0->sAdd('create_jiaoyi_num_qu',$num);
            }
            $this->save_jiaoyi_num_do();
        }
    }

    protected function save_reg_num_do()
    {
        if($this->reg_num < $this->reg_num_end)
        {
            $num = self::create_reg_num(8);
            $rr = $this->redis0->sAdd('create_reg_num',$num);
            if($rr)
            {
                $this->reg_num +=1;
                $this->redis0->sAdd('create_reg_num_qu',$num);
            }
            $this->save_reg_num_do();
        }
    }

    public static function create_jiaoyi_num($length)
    {
        // 密码字符集，可任意添加你需要的字符
        $chars = '3019265748';
        $num = "";
        for ( $i = 0; $i < $length; $i++ )
        {
            // 这里提供两种字符获取方式
            // 第一种是使用 substr 截取$chars中的任意一位字符；
            // 第二种是取字符数组 $chars 的任意元素
            // $password .= substr($chars, mt_rand(0, strlen($chars) – 1), 1);
            $num .= $chars[ mt_rand(0, strlen($chars) - 1) ];
        }
        return $num;
    }

    public static function create_reg_num($length)
    {
        // 密码字符集，可任意添加你需要的字符
        $chars = 'abcde2fgh6jkm5n4pqr7stuv8wx9yz3';
        $num = "";
        for ( $i = 0; $i < $length; $i++ )
        {
            // 这里提供两种字符获取方式
            // 第一种是使用 substr 截取$chars中的任意一位字符；
            // 第二种是取字符数组 $chars 的任意元素
            // $password .= substr($chars, mt_rand(0, strlen($chars) – 1), 1);
            $num .= $chars[ mt_rand(0, strlen($chars) - 1) ];
        }
        return $num;
    }
}