<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $price_id 
 * @property string $day 
 * @property float $tz_ok 
 * @property float $pm_ok 
 * @property string $bishu 
 * @property string $riqi 
 * @property float $val 
 */
class DsJiaoyiPrice extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_jiaoyi_price';
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
    protected $casts = ['price_id' => 'integer', 'tz_ok' => 'float', 'pm_ok' => 'float', 'val' => 'float'];
    public $timestamps = false;
}