<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $error_log_id 
 * @property string $error_log_type 
 * @property string $error_log_info 
 * @property string $error_log_cont 
 * @property int $error_log_time 
 * @property int $error_log_status 
 */
class DsErrorLog extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_error_log';
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
    protected $casts = ['error_log_id' => 'integer', 'error_log_time' => 'integer', 'error_log_status' => 'integer'];
    public $timestamps = false;
    /**
     * 增加错误日志
     * @param string $type  //变动类型
     * @param string $info  //错误信息
     * @param string $cont  //手动错误信息
     */
    public static function add_log($type = '', $info = '', $cont = '')
    {
        $data = ['error_log_type' => $type, 'error_log_info' => $info, 'error_log_cont' => $cont, 'error_log_time' => time()];
        self::query()->insert($data);
    }
}