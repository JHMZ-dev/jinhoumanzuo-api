<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $huoyuedu_id 
 * @property int $user_id 
 * @property int $huoyuedu_type 
 * @property float $huoyuedu_num 
 * @property string $huoyuedu_cont 
 * @property int $huoyuedu_time 
 */
class DsUserHuoyuedu extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_user_huoyuedu';
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
    protected $casts = ['huoyuedu_id' => 'integer', 'user_id' => 'integer', 'huoyuedu_type' => 'integer', 'huoyuedu_num' => 'float', 'huoyuedu_time' => 'integer'];
    public $timestamps = false;
    /**
     * 增加日志
     * @param $user_id
     * @param $type
     * @param $num
     * @param string $cont
     */
    public static function add_log($user_id, $type, $num, $cont = '')
    {
        $data = ['user_id' => $user_id, 'huoyuedu_type' => $type, 'huoyuedu_num' => $num, 'huoyuedu_cont' => $cont, 'huoyuedu_time' => time()];
        self::query()->insert($data);
    }
}