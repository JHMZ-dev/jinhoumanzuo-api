<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $group_log_id 
 * @property int $user_id 
 * @property int $group 
 * @property int $type 
 * @property string $cont 
 * @property int $time 
 */
class DsUserGroupLog extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_user_group_log';
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
    protected $casts = ['group_log_id' => 'integer', 'user_id' => 'integer', 'group' => 'integer', 'type' => 'integer', 'time' => 'integer'];
    public $timestamps = false;
    /**
     * @param $user_id
     * @param $type
     * @param $group
     * @param string $cont
     */
    public static function add_log($user_id, $type, $group, $cont = '')
    {
        $data = ['user_id' => $user_id, 'group' => $group, 'type' => $type, 'cont' => $cont, 'time' => time()];
        self::query()->insert($data);
    }
}