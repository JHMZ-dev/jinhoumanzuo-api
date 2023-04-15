<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $user_group_id 
 * @property int $group 
 * @property string $group_name 
 * @property string $group_name2 
 * @property float $group_zhi_real 
 * @property float $group_team_real 
 * @property float $group_all_huoyue 
 * @property float $group_da_huoyue 
 * @property float $group_xiao_huoyue 
 * @property float $group_geren_huoyue 
 * @property float $group_fen 
 * @property int $group_jifen 
 * @property string $group_fenhong 
 */
class DsUserGroup extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_user_group';
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
    protected $casts = ['user_group_id' => 'integer', 'group' => 'integer', 'group_zhi_real' => 'float', 'group_team_real' => 'float', 'group_all_huoyue' => 'float', 'group_da_huoyue' => 'float', 'group_xiao_huoyue' => 'float', 'group_geren_huoyue' => 'float', 'group_fen' => 'float', 'group_jifen' => 'integer'];
    public $timestamps = false;
}