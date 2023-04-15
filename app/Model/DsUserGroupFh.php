<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $group_fh_id 
 * @property int $group 
 * @property float $num 
 * @property string $day 
 */
class DsUserGroupFh extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_user_group_fh';
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
    protected $casts = ['group_fh_id' => 'integer', 'group' => 'integer', 'num' => 'float'];
    public $timestamps = false;
}