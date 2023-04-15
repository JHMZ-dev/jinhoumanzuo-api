<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Db;
use Hyperf\DbConnection\Model\Model;
/**
 * @property int $user_yingpiao_id 
 * @property int $user_id 
 * @property int $user_yingpiao_type 
 * @property float $user_yingpiao_change 
 * @property float $user_yingpiao_before 
 * @property float $user_yingpiao_after 
 * @property string $user_yingpiao_cont 
 * @property int $user_yingpiao_time 
 */
class DsUserYingpiao extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_user_yingpiao';
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
    protected $casts = ['user_yingpiao_id' => 'integer', 'user_id' => 'integer', 'user_yingpiao_type' => 'integer', 'user_yingpiao_change' => 'float', 'user_yingpiao_before' => 'float', 'user_yingpiao_after' => 'float', 'user_yingpiao_time' => 'integer'];
    public $timestamps = false;
    /**
     * 增加充值以及日志
     * @param $user_id  //用户id
     * @param $num      //变更数量
     * @param string $cont  //变更原因
     * @return bool
     */
    public static function add_yingpiao($user_id, $num, $cont = '')
    {
        if ($num <= 0) {
            return false;
        }
        $before = DsUser::query()->where('user_id', $user_id)->value('yingpiao');
        if (empty($before)) {
            $before = 0;
        }
        $user_yingpiao_after = self::query()->where('user_id', $user_id)->orderByDesc('user_yingpiao_id')->value('user_yingpiao_after');
        if (empty($user_yingpiao_after)) {
            $user_yingpiao_after = 0;
        }
        //判断金额是否能对上日志
        if ($user_yingpiao_after != $before) {
            //记录错误日志
            //DsErrorLog::add_log('增加充值', '余额和日志对不上', '用户id：' . $user_id . '变更数量：' . $num . '变更原因' . $cont);
            return false;
        }
        $after = $before + $num;
        if ($after < 0) {
            //记录错误日志
            //DsErrorLog::add_log('增加充值', '金额余额小于0', '用户id：' . $user_id . '变更数量：' . $num . '变更原因' . $cont);
            return false;
        }
        $data = ['user_id' => $user_id, 'user_yingpiao_type' => 1, 'user_yingpiao_change' => $num, 'user_yingpiao_before' => $before, 'user_yingpiao_after' => $after, 'user_yingpiao_cont' => $cont, 'user_yingpiao_time' => time()];
        //开启自动事务操作
        Db::beginTransaction();
        try {
            $res1 = DsUser::query()->where('user_id', $user_id)->update(['yingpiao' => $after]);
            $res2 = self::query()->insert($data);
            if ($res1 && $res2) {
                //操作成功
                Db::commit();
                return $res2;
            } else {
                Db::rollBack();
                //写入错误日志
                if (!$res2) {
                    //DsErrorLog::add_log('增加充值', '写入充值日志失败', '用户id：' . $user_id . '变更数量：' . $num . '变更原因' . $cont);
                } else {
                    //DsErrorLog::add_log('增加充值', '修改用户金额失败', '用户id：' . $user_id . '变更数量：' . $num . '变更原因' . $cont);
                }
                return false;
            }
        } catch (\Throwable $ex) {
            Db::rollBack();
            //写入错误日志
            //DsErrorLog::add_log('增加充值', $ex->getMessage(), '用户id：' . $user_id . '变更数量：' . $num . '变更原因' . $cont);
            return false;
        }
    }
    /**
     * 减少充值以及日志
     * @param $user_id  //用户id
     * @param $num      //变更数量
     * @param string $cont  //变更原因
     * @return bool
     */
    public static function del_yingpiao($user_id, $num, $cont = '')
    {
        if ($num <= 0) {
            return false;
        }
        $before = DsUser::query()->where('user_id', $user_id)->value('yingpiao');
        if (empty($before)) {
            $before = 0;
        }
        $user_yingpiao_after = self::query()->where('user_id', $user_id)->orderByDesc('user_yingpiao_id')->value('user_yingpiao_after');
        if (empty($user_yingpiao_after)) {
            $user_yingpiao_after = 0;
        }
        //判断金额是否能对上日志
        if ($user_yingpiao_after != $before) {
            //记录错误日志
            //DsErrorLog::add_log('减少充值', '余额和日志对不上', '用户id：' . $user_id . '变更数量：' . $num . '变更原因' . $cont);
            return false;
        }
        $after = $before - $num;
        if ($after < 0) {
            //记录错误日志
            //DsErrorLog::add_log('减少充值', '金额余额小于0', '用户id：' . $user_id . '变更数量：' . $num . '变更原因' . $cont);
            return false;
        }
        $data = ['user_id' => $user_id, 'user_yingpiao_type' => 2, 'user_yingpiao_change' => $num, 'user_yingpiao_before' => $before, 'user_yingpiao_after' => $after, 'user_yingpiao_cont' => $cont, 'user_yingpiao_time' => time()];
        //开启自动事务操作
        Db::beginTransaction();
        try {
            $res1 = DsUser::query()->where('user_id', $user_id)->update(['yingpiao' => $after]);
            $res2 = self::query()->insert($data);
            if ($res1 && $res2) {
                //操作成功
                Db::commit();
                //                //增加消费记录
                //                $redis5 = ApplicationContext::getContainer()->get(RedisFactory::class)->get('db5');
                //                $redis5->incrByFloat($user_id . '_yingpiao', floatval($num));
                return $res2;
            } else {
                Db::rollBack();
                //写入错误日志
                if (!$res2) {
                    //DsErrorLog::add_log('减少充值', '写入充值日志失败', '用户id：' . $user_id . '变更数量：' . $num . '变更原因' . $cont);
                } else {
                    //DsErrorLog::add_log('减少充值', '修改用户金额失败', '用户id：' . $user_id . '变更数量：' . $num . '变更原因' . $cont);
                }
                return false;
            }
        } catch (\Throwable $ex) {
            Db::rollBack();
            //写入错误日志
            //DsErrorLog::add_log('减少充值', $ex->getMessage(), '用户id：' . $user_id . '变更数量：' . $num . '变更原因' . $cont);
            return false;
        }
    }
}