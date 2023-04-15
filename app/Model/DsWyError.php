<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $wy_error_id 
 * @property int $user_id 
 * @property string $mobile 
 * @property int $riskLevel 
 * @property string $ip 
 * @property string $hitInfos 
 * @property string $taskId 
 * @property string $sdkRespData 
 * @property string $deviceId 
 * @property string $matchedRules 
 * @property string $deviceInfo 
 * @property int $time 
 */
class DsWyError extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_wy_error';
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
    protected $casts = ['wy_error_id' => 'integer', 'user_id' => 'integer', 'time' => 'integer', 'riskLevel' => 'integer'];
    public $timestamps = false;
}