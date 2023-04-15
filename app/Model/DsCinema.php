<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $cinema_id 
 * @property string $cid 
 * @property int $cinemaId 
 * @property int $cinemaCode 
 * @property string $cinemaName 
 * @property string $provinceId 
 * @property string $cityId 
 * @property string $countyId 
 * @property string $address 
 * @property string $longitude 
 * @property string $latitude 
 * @property string $province 
 * @property string $city 
 * @property string $county 
 * @property string $stopSaleTime 
 * @property string $direct 
 * @property string $backTicketConfig 
 * @property int $type 
 * @property int $city_id 
 */
class DsCinema extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_cinema';
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
    protected $casts = ['cinema_id' => 'integer', 'cinemaId' => 'integer', 'cinemaCode' => 'integer', 'type' => 'integer', 'city_id' => 'integer'];
    public $timestamps = false;
}