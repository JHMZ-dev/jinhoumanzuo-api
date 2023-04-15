<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $bang_id 
 * @property int $user_id 
 * @property int $status 
 * @property float $fuwu_banjing 
 * @property string $name 
 * @property string $mobile 
 * @property int $sheng_id 
 * @property int $shi_id 
 * @property int $qu_id 
 * @property string $address 
 * @property string $banner 
 * @property string $cont 
 * @property float $longitude 
 * @property float $latitude 
 * @property int $offline_end_time 
 * @property float $tongzheng 
 * @property int $time 
 * @property string $introduction 
 */
class DsBang extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_bang';
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
    protected $casts = ['bang_id' => 'integer', 'user_id' => 'integer', 'status' => 'integer', 'fuwu_banjing' => 'float', 'sheng_id' => 'integer', 'shi_id' => 'integer', 'qu_id' => 'integer', 'longitude' => 'float', 'latitude' => 'float', 'offline_end_time' => 'integer', 'tongzheng' => 'float', 'time' => 'integer'];
    public $timestamps = false;
}