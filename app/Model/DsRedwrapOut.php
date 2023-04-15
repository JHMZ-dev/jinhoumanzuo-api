<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $redwrap_out_id 
 * @property string $room_id 
 * @property int $user_id 
 * @property float $money 
 * @property int $type 
 * @property int $class 
 * @property int $scene 
 * @property int $status 
 * @property int $num 
 * @property int $style 
 * @property int $time 
 * @property int $time_over 
 * @property int $redwrap_user_id 
 * @property string $msg_id 
 */
class DsRedwrapOut extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_redwrap_out';
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
    protected $casts = ['redwrap_out_id' => 'integer', 'user_id' => 'integer', 'money' => 'float', 'type' => 'integer', 'class' => 'integer', 'scene' => 'integer', 'status' => 'integer', 'num' => 'integer', 'style' => 'integer', 'time' => 'integer', 'time_over' => 'integer', 'redwrap_user_id' => 'integer', 'time_end' => 'integer', 'status2' => 'integer'];
    public $timestamps = false;
}