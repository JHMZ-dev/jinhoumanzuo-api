<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $ad_error_id 
 * @property int $user_id 
 * @property string $code 
 * @property string $msg 
 * @property int $time 
 */
class DsAdError extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_ad_error';
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
    protected $casts = ['ad_error_id' => 'integer', 'user_id' => 'integer', 'time' => 'integer'];
    public $timestamps = false;
    /**
     * 增加日志
     * @param string $type  //变动类型
     * @param string $info  //错误信息
     * @param string $cont  //手动错误信息
     */
    public static function add_log($user_id, $code = '', $msg = '')
    {
        $data = ['user_id' => $user_id, 'code' => $code, 'msg' => $msg, 'time' => time()];
        self::query()->insert($data);
    }
}