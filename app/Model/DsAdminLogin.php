<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $admin_login_id 
 * @property string $admin_login_username 
 * @property string $admin_login_password 
 * @property string $admin_login_status 
 * @property string $admin_login_ip 
 * @property string $admin_login_head 
 * @property string $admin_login_reason 
 * @property int $admin_login_time 
 */
class DsAdminLogin extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_admin_login';
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
    protected $casts = ['admin_login_id' => 'integer', 'admin_login_time' => 'integer'];
    public $timestamps = false;
}