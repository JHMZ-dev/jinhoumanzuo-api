<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $cz_huafei_user_order_id 
 * @property int $user_id 
 * @property string $charge_phone 
 * @property int $charge_value 
 * @property string $customer_order_no 
 * @property string $ordersn 
 * @property float $customer_price 
 * @property string $order_id 
 * @property int $cz_huafei_user_order_time 
 * @property int $status 
 * @property string $product_name 
 * @property string $shop_type 
 * @property string $external_biz_id 
 * @property float $price_tongzheng 
 * @property string $charge_finish_time 
 * @property string $recharge_description 
 * @property string $product_id 
 * @property string $name 
 * @property float $price_kou 
 * @property float $balance 
 */
class DsCzHuafeiUserOrder extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_cz_huafei_user_order';
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
    protected $casts = ['cz_huafei_user_order_id' => 'integer', 'user_id' => 'integer', 'charge_value' => 'integer', 'customer_price' => 'float', 'cz_huafei_user_order_time' => 'integer', 'status' => 'integer', 'price_tongzheng' => 'float', 'price_kou' => 'float', 'balance' => 'float'];
    public $timestamps = false;
}