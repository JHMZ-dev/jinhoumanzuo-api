<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $cinema_city_id 
 * @property string $cid 
 * @property string $city_name 
 * @property string $letter 
 * @property int $hot 
 */
class DsCinemaCity extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_cinema_city';
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
    protected $casts = ['cinema_city_id' => 'integer', 'hot' => 'integer'];
    public $timestamps = false;
}