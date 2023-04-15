<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $hs_id 
 * @property int $user_id 
 * @property string $ali_name 
 * @property string $ali_account 
 * @property float $tongzheng 
 * @property int $time 
 * @property int $type 
 * @property string $cont 
 * @property string $day 
 */
class DsTzH extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_tz_hs';
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
    protected $casts = ['hs_id' => 'integer', 'user_id' => 'integer', 'tongzheng' => 'float', 'time' => 'integer', 'type' => 'integer'];
    public $timestamps = false;
}