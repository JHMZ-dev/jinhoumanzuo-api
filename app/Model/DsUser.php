<?php

declare (strict_types=1);
namespace App\Model;

use App\Job\Async;
use Hyperf\AsyncQueue\Driver\DriverFactory;
use Hyperf\DbConnection\Model\Model;
use Hyperf\Utils\ApplicationContext;
/**
 * @property int $user_id 
 * @property string $username 
 * @property string $nickname 
 * @property string $password 
 * @property string $pay_password 
 * @property string $mobile 
 * @property string $avatar 
 * @property int $auth 
 * @property string $auth_name 
 * @property string $auth_num 
 * @property string $auth_error 
 * @property int $reg_time 
 * @property int $login_status 
 * @property int $role_id 
 * @property int $vip_end_time 
 * @property int $pid 
 * @property string $ip 
 * @property string $logout_mobile 
 * @property string $openid 
 * @property string $unionid 
 * @property string $longitude 
 * @property string $latitude 
 * @property float $version 
 * @property int $group 
 * @property int $is_xuni 
 * @property float $wallet 
 * @property int $level 
 * @property float $tongzheng 
 * @property int $last_do_time 
 * @property int $async 
 * @property float $yingpiao 
 * @property string $huanxin_token 
 * @property float $puman 
 * @property string $usdt_address 
 * @property string $ali_name 
 * @property string $ali_account 
 * @property string $ma_zhitui 
 * @property string $ma_paixian 
 * @property int $love 
 * @property string $cont 
 * @property int $ren 
 */
class DsUser extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_user';
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
    protected $casts = ['user_id' => 'integer', 'auth' => 'integer', 'reg_time' => 'integer', 'login_status' => 'integer', 'role_id' => 'integer', 'vip_end_time' => 'integer', 'pid' => 'integer', 'tomato' => 'float', 'gold' => 'float', 'group' => 'integer', 's_tomato' => 'float', 'tui_tomato' => 'float', 'huoyuezhi' => 'float', 'city_id' => 'integer', 'sex' => 'integer', 'city_partner_id' => 'integer', 'tou' => 'integer', 'yibu' => 'integer', 'robot' => 'integer', 'ddz' => 'integer', 'guquan' => 'float', 'otc_tomato' => 'float', 'buy_tomato' => 'float', 'wallet' => 'float', 'cxf' => 'integer', 'tui_gold' => 'float', 'gxz' => 'float', 'jiaoyi_dj' => 'integer', 'last_do_time' => 'integer', 'fruit' => 'float', 'seed' => 'float', 'mgt' => 'float', 'dj_mgt' => 'float', 'partner' => 'integer', 'shufen' => 'float', 'ggbao' => 'float', 'daishifang' => 'float', 'chuxuguan' => 'float', 'yunc' => 'integer', 'yuncang' => 'float', 'version' => 'float', 'gongxianzhi' => 'float', 'xiaofeiguan' => 'float', 'is_xuni' => 'integer', 'level' => 'integer', 'tongzheng' => 'float', 'async' => 'integer', 'yingpiao' => 'float', 'puman' => 'float', 'huoyuedu' => 'float', 'love' => 'integer', 'ren' => 'integer'];
    public $timestamps = false;
    /**
     * 开通/续费会员
     * @param $user_id  //用户id
     * @param $day      //天数
     * @param $type     //1开通 2续费
     */
    public static function pay_vip($user_id, $day, $type)
    {
        $ti = 60 * 60 * 24 * $day;
        $time = time();
        //判断是否是会员
        $vip_end_time = self::query()->where('user_id', $user_id)->value('vip_end_time');
        $is_vip = 0;
        if ($vip_end_time > 0) {
            //判断会员到期时间
            if ($vip_end_time - time() > 1) {
                $is_vip = 1;
            }
        }
        if ($is_vip == 1) {
            //是会员则只增加时间
            $update = ['vip_end_time' => $vip_end_time + $ti, 'role_id' => 1];
        } else {
            //不是会员 开通会员
            $update = ['role_id' => 1, 'vip_end_time' => $time + $ti];
        }
        $res = self::query()->where('user_id', $user_id)->update($update);
        if (!$res) {
            //写入日志
            DsErrorLog::add_log('开会员', '增加天数失败', json_encode(['user_id' => $user_id, 'day' => $day, 'type' => $type]));
        }
        //        //异步统一处理
        //        $info = ['type' => '8', 'user_id' => $user_id];
        //        $job = ApplicationContext::getContainer()->get(DriverFactory::class);
        //        $job->get('async')->push(new Async($info));
    }
}