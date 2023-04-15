<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $notice_user_id 
 * @property int $user_id 
 * @property int $notice_id 
 * @property int $watch_time 
 */
class DsNoticeUser extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_notice_user';
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
    protected $casts = ['notice_user_id' => 'integer', 'user_id' => 'integer', 'notice_id' => 'integer', 'time' => 'integer', 'watch_time' => 'integer'];
    public $timestamps = false;
}