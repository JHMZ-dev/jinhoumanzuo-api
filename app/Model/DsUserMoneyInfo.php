<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $user_money_info_id 
 * @property int $user_id 
 * @property string $name 
 * @property string $ali_account 
 * @property string $ali_img 
 * @property string $wx_img 
 * @property string $bank_account 
 * @property string $bank_name 
 */
class DsUserMoneyInfo extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_user_money_info';
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
    protected $casts = ['user_money_info_id' => 'integer', 'user_id' => 'integer'];
    public $timestamps = false;
}