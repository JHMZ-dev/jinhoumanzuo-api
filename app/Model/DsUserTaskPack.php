<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $user_task_pack_id 
 * @property int $task_pack_id 
 * @property int $user_id 
 * @property string $name 
 * @property float $need 
 * @property float $get 
 * @property int $day 
 * @property int $status 
 * @property int $do_day 
 * @property int $time 
 * @property int $end_time 
 * @property int $ok_time 
 * @property float $all_get 
 * @property string $img 
 * @property float $one_get 
 * @property float $huoyuezhi 
 * @property float $shengyu 
 * @property float $gongxianzhi 
 * @property string $yuanyin 
 */
class DsUserTaskPack extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_user_task_pack';
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
    protected $casts = ['user_task_pack_id' => 'integer', 'task_pack_id' => 'integer', 'user_id' => 'integer', 'need' => 'float', 'get' => 'float', 'day' => 'integer', 'status' => 'integer', 'do_day' => 'integer', 'time' => 'integer', 'end_time' => 'integer', 'ok_time' => 'integer', 'all_get' => 'float', 'yibu' => 'integer', 'one_get' => 'float', 'huoyuezhi' => 'float', 'daishifang' => 'float', 'do_day2' => 'integer', 'shengyu' => 'float', 'gongxianzhi' => 'float'];
    public $timestamps = false;
}