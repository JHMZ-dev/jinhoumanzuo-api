<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $set_id 
 * @property string $set_title 
 * @property string $set_cname 
 * @property string $set_cvalue 
 * @property int $set_time 
 */
class DsAdminSet extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_admin_set';
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
    protected $casts = ['set_id' => 'integer', 'set_time' => 'integer'];
    public $timestamps = false;
}