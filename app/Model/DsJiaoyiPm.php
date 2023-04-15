<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $jiaoyi_pm_id 
 * @property int $user_id 
 * @property int $to_id 
 * @property string $order_sn 
 * @property float $tz_num 
 * @property float $pm_num 
 * @property float $bilie 
 * @property int $day 
 * @property int $time 
 * @property int $type 
 * @property int $ok_time 
 * @property int $off_time 
 */
class DsJiaoyiPm extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_jiaoyi_pm';
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
    protected $casts = ['jiaoyi_pz_id' => 'integer', 'user_id' => 'integer', 'to_id' => 'integer', 'num' => 'float', 'num2' => 'float', 'price_one' => 'float', 'price_all' => 'float', 'day' => 'integer', 'time' => 'integer', 'type' => 'integer', 'ok_time' => 'integer', 'tz_num' => 'float', 'pm_num' => 'float', 'bilie' => 'float', 'jiaoyi_pm_id' => 'integer', 'off_time' => 'integer'];
    public $timestamps = false;
    /**
     * 创建订单号
     */
    public static function createOrderSN()
    {
        return date('YmdHis') . rand(1000, 9999);
    }
}