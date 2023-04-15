<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $u_dh_id 
 * @property string $hash 
 * @property float $puman 
 * @property int $user_id 
 * @property int $status 
 * @property int $time 
 * @property int $use_time 
 */
class DsPmUDh extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_pm_u_dh';
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
    protected $casts = ['u_dh_id' => 'integer', 'user_id' => 'integer', 'status' => 'integer', 'time' => 'integer', 'use_time' => 'integer', 'puman' => 'float'];
    public $timestamps = false;
}