<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $auth_error_id 
 * @property int $user_id 
 * @property int $type 
 * @property string $code 
 * @property string $msg 
 * @property string $file 
 * @property string $certifyid 
 * @property string $line 
 * @property int $time 
 */
class DsAuthError extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_auth_error';
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
    protected $casts = ['auth_error_id' => 'integer', 'user_id' => 'integer', 'type' => 'integer', 'time' => 'integer'];
    public $timestamps = false;
    /**
     * 增加错误日志
     * @param string $type  //变动类型
     * @param string $info  //错误信息
     * @param string $cont  //手动错误信息
     */
    public static function add_log($user_id, $code, $msg, $file, $line, $type, $certifyid = '')
    {
        $data = ['user_id' => $user_id, 'type' => $type, 'code' => $code, 'msg' => $msg, 'file' => $file, 'line' => $line, 'certifyid' => $certifyid, 'time' => time()];
        self::query()->insert($data);
    }
}