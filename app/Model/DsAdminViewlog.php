<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $admin_viewlogs_id 
 * @property string $module 
 * @property string $controller 
 * @property string $action 
 * @property string $datas 
 * @property int $time 
 * @property string $ip 
 * @property string $useragent 
 * @property string $name 
 */
class DsAdminViewlog extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_admin_viewlogs';
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
    protected $casts = ['admin_viewlogs_id' => 'integer', 'time' => 'integer'];
    public $timestamps = false;
}