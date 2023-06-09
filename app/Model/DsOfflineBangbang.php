<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $bangbang_id 
 * @property int $user_id 
 * @property string $nickname 
 * @property int $time 
 * @property int $status 
 */
class DsOfflineBangbang extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_offline_bangbang';
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
    protected $casts = ['bangbang_id' => 'integer', 'user_id' => 'integer', 'time' => 'integer', 'status' => 'integer'];
    public $timestamps = false;
}