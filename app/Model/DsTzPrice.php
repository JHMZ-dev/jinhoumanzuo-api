<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $tz_price_id 
 * @property float $fen 
 * @property float $tongzheng 
 * @property float $price 
 */
class DsTzPrice extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_tz_price';
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
    protected $casts = ['tz_price_id' => 'integer', 'fen' => 'float', 'tongzheng' => 'float', 'price' => 'float'];
    public $timestamps = false;
}