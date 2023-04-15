<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $gongxianzhi_id 
 * @property int $user_id 
 * @property int $gongxianzhi_type 
 * @property float $gongxianzhi_num 
 * @property string $gongxianzhi_cont 
 * @property int $gongxianzhi_time 
 */
class DsUserGongxianzhi extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_user_gongxianzhi';
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
    protected $casts = ['gongxianzhi_id' => 'integer', 'user_id' => 'integer', 'gongxianzhi_type' => 'integer', 'gongxianzhi_num' => 'float', 'gongxianzhi_time' => 'integer'];
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
        $data = ['user_id' => $user_id, 'gongxianzhi_type' => $type, 'gongxianzhi_num' => $num, 'gongxianzhi_cont' => $cont, 'gongxianzhi_time' => time()];
        self::query()->insert($data);
    }
}