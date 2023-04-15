<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $user_id 
 * @property string $user_number 
 * @property string $user_name 
 * @property string $nick_name 
 * @property string $user_type 
 * @property string $email 
 * @property string $phonenumber 
 * @property string $sex 
 * @property string $id_card 
 * @property string $avatar 
 * @property string $password 
 * @property string $payment_password 
 * @property string $weixin 
 * @property string $invite_qr_code 
 * @property string $qr_code 
 * @property string $status 
 * @property int $star_label 
 * @property float $movie_tickets_num 
 * @property float $full_seat_points_num 
 * @property float $warehouse_num 
 * @property float $scroll_reward_num 
 * @property float $team_scroll_reward_num 
 * @property float $to_be_returned_num 
 * @property float $contribution_reward_num 
 * @property float $transferable_num 
 * @property float $star_subsidy_num 
 * @property float $active 
 * @property int $user_invitee_id 
 * @property string $real_name_or_not 
 * @property string $del_flag 
 * @property string $login_ip 
 * @property string $login_date 
 * @property string $create_by 
 * @property string $create_time 
 * @property string $update_by 
 * @property string $update_time 
 * @property string $remark 
 * @property int $apply_num 
 * @property int $first_exchange 
 */
class SysUser extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'sys_user';
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
    protected $casts = ['user_id' => 'integer', 'star_label' => 'integer', 'movie_tickets_num' => 'float', 'full_seat_points_num' => 'float', 'warehouse_num' => 'float', 'scroll_reward_num' => 'float', 'team_scroll_reward_num' => 'float', 'to_be_returned_num' => 'float', 'contribution_reward_num' => 'float', 'transferable_num' => 'float', 'star_subsidy_num' => 'float', 'active' => 'float', 'user_invitee_id' => 'integer', 'apply_num' => 'integer', 'first_exchange' => 'integer', 'ttt' => 'integer'];
    public $timestamps = false;
}