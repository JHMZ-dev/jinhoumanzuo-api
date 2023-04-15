<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $vip_price_id 
 * @property float $price 
 * @property float $price_yuan 
 * @property float $puman 
 * @property float $day 
 */
class DsVipPrice extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_vip_price';
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
    protected $casts = ['vip_price_id' => 'integer', 'price' => 'float', 'price_yuan' => 'float', 'puman' => 'float', 'day' => 'float'];
    public $timestamps = false;
}