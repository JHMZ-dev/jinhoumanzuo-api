<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $code_log_id 
 * @property string $code_log_type 
 * @property string $code_log_mobile 
 * @property string $code_log_code 
 * @property int $code_log_res 
 * @property string $code_log_cont 
 * @property int $code_log_time 
 */
class DsCodeLog extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_code_log';
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
    protected $casts = ['code_log_id' => 'integer', 'code_log_res' => 'integer', 'code_log_time' => 'integer'];
    public $timestamps = false;
}