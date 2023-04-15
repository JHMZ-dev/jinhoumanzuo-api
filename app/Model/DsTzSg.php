<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $sg_id 
 * @property int $user_id 
 * @property float $tongzheng 
 * @property float $price 
 * @property int $time 
 * @property string $day 
 */
class DsTzSg extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_tz_sg';
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
    protected $casts = ['sg_id' => 'integer', 'user_id' => 'integer', 'tongzheng' => 'float', 'price' => 'float', 'time' => 'integer'];
    public $timestamps = false;
    public static function add_log($user_id, $tongzheng, $price)
    {
        $data = ['user_id' => $user_id, 'tongzheng' => $tongzheng, 'price' => $price, 'day' => date('Y-m-d'), 'time' => time()];
        self::query()->insert($data);
    }
}