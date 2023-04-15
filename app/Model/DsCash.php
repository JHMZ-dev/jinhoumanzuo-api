<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $cash_id 
 * @property int $user_id 
 * @property float $cash_money 
 * @property float $cash_money_2 
 * @property int $cash_type 
 * @property string $payee_account 
 * @property string $payee_real_name 
 * @property int $admin_id 
 * @property int $cash_status 
 * @property string $result_msg 
 * @property string $order_number 
 * @property int $cash_time 
 * @property int $cash_chuli_time 
 * @property int $cash_bie 
 * @property int $cach_day 
 * @property int $tjia 
 */
class DsCash extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_cash';
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
    protected $casts = ['cash_id' => 'integer', 'user_id' => 'integer', 'cash_money' => 'float', 'cash_money_2' => 'float', 'cash_type' => 'integer', 'admin_id' => 'integer', 'cash_status' => 'integer', 'cash_time' => 'integer', 'cash_chuli_time' => 'integer', 'cash_bie' => 'integer', 'cach_day' => 'integer', 'tjia' => 'integer'];
    public $timestamps = false;
}