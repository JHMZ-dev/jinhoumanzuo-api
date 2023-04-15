<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $cinema_re_id 
 * @property string $featureAppNo 
 * @property string $cinemaCode 
 * @property string $sourceFilmNo 
 * @property string $filmNo 
 * @property string $filmName 
 * @property string $hallNo 
 * @property string $hallName 
 * @property string $startTime 
 * @property string $copyType 
 * @property string $copyLanguage 
 * @property string $totalTime 
 * @property float $listingPrice 
 * @property float $ticketPrice 
 * @property float $serviceAddFee 
 * @property float $lowestPrice 
 * @property float $thresholds 
 * @property string $areas 
 * @property float $marketPrice 
 * @property int $city_id 
 * @property string $update_time 
 */
class DsCinemaPaiqi extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_cinema_paiqi';
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
    protected $casts = ['cinema_re_id' => 'integer', 'listingPrice' => 'float', 'ticketPrice' => 'float', 'serviceAddFee' => 'float', 'lowestPrice' => 'float', 'thresholds' => 'float', 'marketPrice' => 'float', 'city_id' => 'integer'];
    public $timestamps = false;
}