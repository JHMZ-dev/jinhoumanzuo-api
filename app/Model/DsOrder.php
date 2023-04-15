<?php

declare (strict_types=1);
namespace App\Model;

use App\Job\Async;
use Hyperf\AsyncQueue\Driver\DriverFactory;
use Hyperf\DbConnection\Model\Model;
use Hyperf\Redis\RedisFactory;
use Hyperf\Utils\ApplicationContext;
/**
 * @property int $order_id 
 * @property string $order_sn 
 * @property int $user_id 
 * @property int $to_id 
 * @property int $payment_type 
 * @property float $need_money 
 * @property int $order_status 
 * @property int $add_time 
 * @property int $order_type 
 * @property float $order_relation 
 * @property string $order_relation2 
 * @property float $money 
 * @property int $pay_time 
 * @property int $is_real 
 * @property string $remarks 
 */
class DsOrder extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_order';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [];
    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = ['order_id' => 'integer', 'user_id' => 'integer', 'to_id' => 'integer', 'payment_type' => 'integer', 'need_money' => 'float', 'order_status' => 'integer', 'add_time' => 'integer', 'order_type' => 'integer', 'order_relation' => 'float', 'money' => 'float', 'pay_time' => 'integer', 'is_real' => 'integer'];
    public $timestamps = false;
    /**
     * 创建订单号
     */
    public static function createOrderSN()
    {
        return date('YmdHis') . rand(1000, 9999);
    }
    /**
     * 充值成功的回调函数
     */
    public static function paySuccess($orderSn, $orderMoney)
    {
        $order = self::query()->where(['order_sn' => $orderSn])->first();
        if (!$order) {
            return true;
        }
        $order = $order->toArray();
        if ($order['order_status'] == '1') {
            // 已经回调过，避免重复回调
            return true;
        }
        $orderMoney = strval($orderMoney);
        $update['money'] = strval($order['need_money']);
        //设置支付成功
        $update['order_status'] = 1;
        $update['pay_time'] = time();
        self::query()->where(['order_sn' => $orderSn])->update($update);
        //判断订单金额与交易金额是否相等
        if ($orderMoney != $update['money']) {
            // 记录日志，非常严重，金额对不上
            DsErrorLog::add_log('支付成功', '实际支付金额和用户支付金额不对等', '订单号为：' . $orderSn . '订单金额：' . $update['money'] . '实际支付金额：' . $orderMoney);
            return true;
        }
        $redis0 = ApplicationContext::getContainer()->get(RedisFactory::class)->get('db0');
        $user_id = $order['user_id'];
        if ($order['to_id'] > 0) {
            //帮别人开通的用户id
            $user_id = $order['to_id'];
        }
        //增加总收益
        if ($order['is_real'] == 1) {
            $redis0->incrByFloat('order_money', floatval($orderMoney));
            $day = date('Y-m-d');
            $redis0->incrByFloat('order_money_' . $day, floatval($orderMoney));
        }
        $time = time();
        //开通类型
        switch ($order['order_type']) {
            case 1:
                //1实名认证支付
                //                DsUser::query()->where('user_id', $user_id)->update(['auth' => 4]);
                //赠送代金券
                //DsCoupon::add_coupon($user_id, '全场减三元优惠券', 3, 0, 1, 2);
                break;
            case 2:
                //开通会员
                $day = $order['order_relation'];
                Dsuser::pay_vip($user_id, intval($day), 1);
                break;
            case 3:
                //续费会员
                $day = $order['order_relation'];
                Dsuser::pay_vip($user_id, intval($day), 2);
                break;
            case 4:
                //购买商品
                break;
            case 5:
                //通证申购
                $num = $order['order_relation'];
                $rrs = DsUserTongzheng::add_tongzheng($user_id, $num, '申购');
                if (!$rrs) {
                    //记录错误日
                    DsErrorLog::add_log('支付成功', '增加通证失败', json_encode(['user_id' => $user_id, 'num' => $num, 'cont' => '申购']));
                }
                DsTzSg::add_log($user_id, $num, $orderMoney);
                break;
            case 6:
                //爱心到了
                $redis0->incrByFloat('all_gongyi_price', floatval($orderMoney));
                $love = DsUser::query()->where('user_id', $user_id)->value('love');
                if ($love != 15) {
                    $aixin = DsUser::query()->where('user_id', $user_id)->update(['love' => 15]);
                    if (!$aixin) {
                        //记录错误日
                        DsErrorLog::add_log('支付成功', '重置爱心失败', json_encode(['user_id' => $user_id]));
                    }
                }
                break;
            default:
                //记录错误日
                DsErrorLog::add_log('支付成功', '没找到回调类型', '用户id：' . $user_id . '订单id：' . $order['order_id']);
                break;
        }
        return true;
    }
    public static function yibu_order($info2, $tt = 0)
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
    /**
     * 查找订单信息并返回订单数据
     */
    public static function _chech_order_sn($order_sn = '', $is_pay = false)
    {
        $orderInfo = self::query()->where(['order_sn' => $order_sn])->first();
        if ($orderInfo) {
            if ($is_pay) {
                if ($orderInfo->order_status == '1') {
                    return true;
                } else {
                    return false;
                }
            } else {
                return $orderInfo;
            }
        } else {
            return false;
        }
    }
}