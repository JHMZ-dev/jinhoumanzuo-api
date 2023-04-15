<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $data_id 
 * @property int $user_id 
 * @property float $gongxianzhi 
 * @property float $huoyuedu 
 * @property int $dj 
 * @property int $last_do_time 
 * @property float $auth_ren 
 * @property float $zhi_auth_ren 
 */
class DsUserDatum extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_user_data';
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
    protected $casts = ['data_id' => 'integer', 'user_id' => 'integer', 'gongxianzhi' => 'float', 'huoyuedu' => 'float', 'dj' => 'integer', 'last_do_time' => 'integer', 'auth_ren' => 'float', 'zhi_auth_ren' => 'float'];
    public $timestamps = false;
    /**
     *
     * @param $user_id
     * @param $num
     * @param int $type
     * @return bool|int
     */
    public static function add_log($user_id, $num, $type = 1)
    {
        $data_id = self::query()->where('user_id', $user_id)->value('user_id');
        switch ($type) {
            case 1:
                if ($data_id) {
                    return self::query()->where('user_id', $user_id)->increment('gongxianzhi', $num);
                } else {
                    $data = ['user_id' => $user_id, 'gongxianzhi' => $num, 'huoyuedu' => 0, 'last_do_time' => time()];
                }
                break;
            case 2:
                if ($data_id) {
                    return self::query()->where('user_id', $user_id)->increment('huoyuedu', $num);
                } else {
                    $data = ['user_id' => $user_id, 'gongxianzhi' => 0, 'huoyuedu' => $num, 'last_do_time' => time()];
                }
                break;
            case 3:
                if ($data_id) {
                    return self::query()->where('user_id', $user_id)->increment('auth_ren', $num);
                } else {
                    $data = ['user_id' => $user_id, 'auth_ren' => $num, 'last_do_time' => time()];
                }
                break;
            case 4:
                if ($data_id) {
                    return self::query()->where('user_id', $user_id)->increment('zhi_auth_ren', $num);
                } else {
                    $data = ['user_id' => $user_id, 'zhi_auth_ren' => $num, 'last_do_time' => time()];
                }
                break;
        }
        return self::query()->insert($data);
    }
}