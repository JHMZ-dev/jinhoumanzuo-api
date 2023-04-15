<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $offline_id 
 * @property int $user_id 
 * @property string $img 
 * @property string $name 
 * @property string $address 
 * @property string $address_detailed 
 * @property int $sheng_id 
 * @property int $shi_id 
 * @property int $qu_id 
 * @property string $longitude 
 * @property string $latitude 
 * @property string $introduction 
 * @property int $offline_in_time 
 * @property int $offline_end_time 
 * @property int $offline_status 
 * @property string $offline_content 
 * @property string $mobile 
 * @property float $price 
 * @property int $shenhe_time 
 */
class DsOffline extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_offline';
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
    protected $casts = ['offline_id' => 'integer', 'user_id' => 'integer', 'sheng_id' => 'integer', 'shi_id' => 'integer', 'qu_id' => 'integer', 'offline_in_time' => 'integer', 'offline_end_time' => 'integer', 'offline_status' => 'integer', 'price' => 'float', 'shenhe_time' => 'integer'];
    public $timestamps = false;
}