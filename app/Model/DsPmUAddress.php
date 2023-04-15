<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $u_address_id 
 * @property string $address 
 * @property int $type 
 * @property int $time 
 */
class DsPmUAddress extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_pm_u_address';
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
    protected $casts = ['u_address_id' => 'integer', 'type' => 'integer', 'time' => 'integer'];
    public $timestamps = false;
}