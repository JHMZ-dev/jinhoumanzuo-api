<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $cinema_order_id 
 * @property int $user_id 
 * @property string $orderNumber2 
 * @property int $order_status 
 * @property int $is_chuli 
 * @property int $payment_type 
 * @property int $status 
 * @property int $status2 
 * @property int $pay_time 
 * @property string $orderNumber 
 * @property string $mobile 
 * @property string $buyTime 
 * @property string $movieName 
 * @property string $cinemaName 
 * @property string $cinemaAddress 
 * @property string $poster 
 * @property string $language 
 * @property string $plan_type 
 * @property string $startTime 
 * @property int $ticketNum 
 * @property string $hallName 
 * @property string $hallType 
 * @property string $seatName 
 * @property string $cityName 
 * @property string $is_tiaowei 
 * @property string $is_love 
 * @property string $yuanjia 
 * @property float $yuanjia_tz 
 * @property string $curl 
 * @property int $city_id 
 * @property int $guoqi_time 
 * @property string $featureAppNo 
 * @property string $duration 
 * @property string $longitude 
 * @property string $latitude 
 * @property string $erweima 
 * @property string $price_danjia 
 * @property string $beizhu 
 * @property string $filmCode 
 */
class DsCinemaUserOrder extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_cinema_user_order';
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
    protected $casts = ['cinema_order_id' => 'integer', 'user_id' => 'integer', 'order_status' => 'integer', 'is_chuli' => 'integer', 'payment_type' => 'integer', 'status' => 'integer', 'status2' => 'integer', 'pay_time' => 'integer', 'ticketNum' => 'integer', 'yuanjia_tz' => 'float', 'city_id' => 'integer', 'guoqi_time' => 'integer'];
    public $timestamps = false;
}