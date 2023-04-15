<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $ma_id 
 * @property int $user_id 
 * @property int $type 
 * @property string $ma 
 * @property int $time 
 */
class DsUserMaLog extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_user_ma_log';
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
    protected $casts = ['ma_id' => 'integer', 'user_id' => 'integer', 'type' => 'integer', 'time' => 'integer'];
    public $timestamps = false;
}