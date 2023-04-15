<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $pm_cb_id 
 * @property int $user_id 
 * @property string $user_address 
 * @property string $to_address 
 * @property float $puman 
 * @property int $status 
 * @property int $time 
 * @property int $use_time 
 * @property string $haxi 
 */
class DsPmCb extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_pm_cb';
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
    protected $casts = ['pm_cb_id' => 'integer', 'user_id' => 'integer', 'puman' => 'float', 'status' => 'integer', 'time' => 'integer', 'use_time' => 'integer'];
    public $timestamps = false;
}