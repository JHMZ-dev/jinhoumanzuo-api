<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $viewlogs_id 
 * @property int $user_id 
 * @property string $viewlogs_token 
 * @property string $viewlogs_url 
 * @property string $viewlogs_datas 
 * @property string $viewlogs_header 
 * @property int $viewlogs_time 
 * @property string $viewlogs_ip 
 * @property string $viewlogs_useragent 
 * @property string $viewlogs_longitude 
 * @property string $viewlogs_latitude 
 */
class DsViewlog extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_viewlogs';
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
    protected $casts = ['viewlogs_id' => 'integer', 'user_id' => 'integer', 'viewlogs_time' => 'integer'];
    public $timestamps = false;
}