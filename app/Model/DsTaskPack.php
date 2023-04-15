<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $task_pack_id 
 * @property string $name 
 * @property float $need 
 * @property float $get 
 * @property int $day 
 * @property int $all_day 
 * @property int $status 
 * @property int $paixu 
 * @property float $num 
 * @property int $type 
 * @property string $img 
 * @property float $huoyuezhi 
 * @property float $gongxianzhi 
 */
class DsTaskPack extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_task_pack';
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
    protected $casts = ['task_pack_id' => 'integer', 'need' => 'float', 'get' => 'float', 'day' => 'integer', 'all_day' => 'integer', 'status' => 'integer', 'paixu' => 'integer', 'num' => 'float', 'type' => 'integer', 'huoyuezhi' => 'float', 'one_get' => 'float', 'need_2' => 'float', 'gongxianzhi' => 'float'];
    public $timestamps = false;
}