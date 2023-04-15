<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $cinema_suozuo_id 
 * @property int $user_id 
 * @property string $orderNo 
 * @property string $cinemaId 
 * @property string $featureAppNo 
 * @property string $mobile 
 * @property string $areaId 
 * @property string $seatNo 
 * @property string $seatPieceName 
 * @property string $ticketPrice 
 * @property string $serviceAddFee 
 * @property int $type 
 * @property string $orderId 
 * @property string $serialNum 
 * @property int $direct 
 */
class DsCinemaSuozuo extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_cinema_suozuo';
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
    protected $casts = ['cinema_suozuo_id' => 'integer', 'user_id' => 'integer', 'type' => 'integer', 'direct' => 'integer'];
    public $timestamps = false;
}