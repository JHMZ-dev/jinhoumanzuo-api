<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $sh_id 
 * @property int $user_id 
 * @property string $usdt_address 
 * @property float $puman 
 * @property int $time 
 * @property int $type 
 * @property string $cont 
 * @property float $shouxu 
 */
class DsPmSh extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_pm_sh';
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
    protected $casts = ['sg_id' => 'integer', 'user_id' => 'integer', 'tongzheng' => 'float', 'price' => 'float', 'time' => 'integer', 'sh_id' => 'integer', 'puman' => 'float', 'type' => 'integer', 'shouxu' => 'float'];
    public $timestamps = false;
}