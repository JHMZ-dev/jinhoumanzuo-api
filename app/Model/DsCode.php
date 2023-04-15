<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $code_id 
 * @property string $mobile 
 * @property string $code_value 
 * @property string $code_type 
 * @property int $user_id 
 * @property int $expired_time 
 * @property int $add_time 
 */
class DsCode extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_code';
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
    protected $casts = ['code_id' => 'integer', 'user_id' => 'integer', 'expired_time' => 'integer', 'add_time' => 'integer'];
    public $timestamps = false;
    /**
     * 生成验证码
     * @return string
     */
    public static function create_code()
    {
        return strval(mt_rand(100000, 999999));
    }
}