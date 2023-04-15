<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $redwrap_get_id 
 * @property int $user_id 
 * @property int $redwrap_out_id 
 * @property int $user_id_out 
 * @property string $room_id 
 * @property float $money 
 * @property int $class 
 * @property int $style 
 * @property int $time 
 * @property string $msg_id 
 */
class DsRedwrapGet extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_redwrap_get';
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
    protected $casts = ['redwrap_get_id' => 'integer', 'user_id' => 'integer', 'redwrap_out_id' => 'integer', 'user_id_out' => 'integer', 'money' => 'float', 'class' => 'integer', 'style' => 'integer', 'time' => 'integer'];
    public $timestamps = false;
}