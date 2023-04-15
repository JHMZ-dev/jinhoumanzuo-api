<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $user_line_id 
 * @property int $user_id 
 * @property float $num 
 * @property int $son_id 
 * @property int $time 
 */
class DsUserLine extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_user_line';
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
    protected $casts = ['user_line_id' => 'integer', 'user_id' => 'integer', 'num' => 'float', 'son_id' => 'integer', 'time' => 'integer'];
    public $timestamps = false;
}