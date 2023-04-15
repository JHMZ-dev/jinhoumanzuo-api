<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $cinema_city_child_id 
 * @property int $cid 
 * @property string $area_name 
 * @property string $city_id 
 */
class DsCinemaCityChild extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_cinema_city_child';
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
    protected $casts = ['cinema_city_child_id' => 'integer', 'cid' => 'integer'];
    public $timestamps = false;
}