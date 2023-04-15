<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $renlian_log_id 
 * @property int $user_id 
 * @property string $bizId 
 * @property string $requestId 
 * @property int $livingType 
 * @property string $certName 
 * @property string $certNo 
 * @property string $bestImg 
 * @property string $pass 
 * @property string $rxfs 
 * @property int $renlian_time 
 */
class DsUserRenlianLog extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_user_renlian_log';
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
    protected $casts = ['renlian_log_id' => 'integer', 'user_id' => 'integer', 'livingType' => 'integer', 'renlian_time' => 'integer'];
    public $timestamps = false;
}