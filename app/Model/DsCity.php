<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $city_id 
 * @property int $level 
 * @property string $name 
 * @property int $pid 
 */
class DsCity extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_city';
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
    protected $casts = ['city_id' => 'integer', 'level' => 'integer', 'pid' => 'integer'];
    public $timestamps = false;
}