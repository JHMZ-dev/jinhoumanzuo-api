<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $cz_id 
 * @property int $user_id 
 * @property string $ordersn 
 * @property int $commodityId 
 * @property string $external_orderno 
 * @property int $buyCount 
 * @property string $remark 
 * @property string $callbackUrl 
 * @property float $externalSellPrice 
 * @property string $template 
 * @property int $mainId 
 * @property int $branchId 
 * @property string $branchName 
 * @property string $branchImg 
 * @property string $MainImg 
 * @property string $name 
 * @property float $guidePrice 
 * @property float $price_all 
 * @property int $time 
 * @property int $pay_time 
 * @property int $status 
 * @property string $orderno 
 * @property int $orderid 
 */
class DsCzUserOrder extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_cz_user_order';
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
    protected $casts = ['cz_id' => 'integer', 'user_id' => 'integer', 'commodityId' => 'integer', 'buyCount' => 'integer', 'externalSellPrice' => 'float', 'mainId' => 'integer', 'branchId' => 'integer', 'guidePrice' => 'float', 'price_all' => 'float', 'time' => 'integer', 'pay_time' => 'integer', 'status' => 'integer', 'orderid' => 'integer'];
    public $timestamps = false;
}