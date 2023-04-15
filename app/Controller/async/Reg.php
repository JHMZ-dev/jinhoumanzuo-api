<?php declare(strict_types=1);

namespace App\Controller\async;

use App\Controller\ChatIm;
use App\Model\DsCity;
use App\Model\DsDaoru;
use App\Model\DsUser;
use App\Model\DsUserDatum;
use App\Model\DsUserLine;
use App\Model\DsUserMaLog;
use Hyperf\DbConnection\Db;
use Hyperf\Guzzle\ClientFactory;
use Hyperf\Redis\RedisFactory;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Context;
use Hyperf\Utils\Parallel;

/**
 * 注册用户后的回调
 * Class RegUser
 * @package App\Controller
 */
class Reg
{

    protected $redis0;
    protected $redis4;
    protected $redis3;
    protected $redis5;
    protected $redis6;
    protected $user_id;
    protected $http;
    protected $p_mobile;
    public function __construct()
    {
        $this->redis0 = ApplicationContext::getContainer()->get(RedisFactory::class)->get('db0');
        $this->redis3 = ApplicationContext::getContainer()->get(RedisFactory::class)->get('db3');
        $this->redis4 = ApplicationContext::getContainer()->get(RedisFactory::class)->get('db4');
        $this->redis5 = ApplicationContext::getContainer()->get(RedisFactory::class)->get('db5');
        $this->redis6 = ApplicationContext::getContainer()->get(RedisFactory::class)->get('db6');
        $this->http   = ApplicationContext::getContainer()->get(ClientFactory::class)->create();
    }

    /**
     * 处理
     * @param $info
     */
    public function reg($info)
    {
        //注册人数加1
        $this->redis0->incr('all_ren');
        $this->user_id  = strval($info['user_id']);
        $pid  = strval($info['pid']);
        //为当前用户绑定上级id
        $this->redis5->set($this->user_id.'_pid' ,$pid);
        //为当前用户设置是否实名认证
        $this->redis5->set($this->user_id.'_auth' ,0);
        //为当前用户增加一个个人活跃值
        $this->redis5->set($this->user_id.'_huoyue' ,0);
        //为当前用户增加一个个人贡献值
        $this->redis5->set($this->user_id.'_gongxianzhi' ,0);
        //为当前用户增加一个团队贡献值
        $this->redis5->set($this->user_id.'_team_gongxianzhi' ,0);
        //为当前用户增加一个团队活跃值
        $this->redis5->set($this->user_id.'_team_huoyue' ,0);
        //为当前用户增加一个团队等级
        $this->redis5->set($this->user_id.'_team_group' ,0);
        //为当前用户增加一个是否是活人/只要5天内做过一次任务就算活人
//        $this->redis6->sAdd($this->user_id.'_zhi_is_jihuo' ,$user_id);
        //为团队增加活人
//        $this->redis6->sAdd($this->user_id.'_team_is_jihuo' ,$user_id);
//为当前用户新建一个团队用户表 包含所有下级id 也包括自己
//        $this->redis5->incr($this->user_id.'_team_user');

//        //为当前用户新建一个团队下实名用户表 包含所有实名id
//        $this->redis5->sAdd($this->user_id.'_team_user_real' ,$this->user_id);
//        $this->redis5->lPush($this->user_id.'_team_user_real_' ,$this->user_id);
//        //处理经纬度
//        $this->add_jwd($this->user_id);

        if($pid > 0)
        {
            //增加直推人数
            $this->redis5->incr($pid.'_zhi_user');
            $ppid = $this->redis5->get($pid.'_pid');
            if($ppid > 0)
            {
                $this->redis5->incr($ppid.'_jian_user');
            }
            //为所有上级团队用户表增加当前用户id
            $this->add_team($this->user_id);
        }

        $this->add_huanxin_token($this->user_id);//获取环信

        $this->ma_user_edit($this->user_id );//绑定用户码

        $datacc = ['user_id' => $this->user_id,'last_do_time' => time() ];
        DsUserDatum::query()->insert($datacc);
    }

    /**
     * 处理经纬度
     * @param $user_id
     */
    public function add_jwd($user_id)
    {
        //查询用户是否有经纬度
        $if = DsUser::query()->where('user_id',$user_id)
            ->select('longitude','latitude','city_id')->first()->toArray();
        if(empty($if['longitude']) || empty($if['latitude']))
        {
            //为用户增加经纬度
            $cif = DsCity::query()->where('city_id',$if['city_id'])->first()->toArray();
            if($cif['level'] == 2)
            {
                $sheng = DsCity::query()->where('city_id',$cif['pid'])->value('name');
                $addres = $sheng.$cif['name'];
            }else{
                $shi = DsCity::query()->where('city_id',$cif['pid'])->select('name','pid')->first()->toArray();
                $sheng = DsCity::query()->where('city_id',$shi['pid'])->value('name');
                $addres = $sheng.$shi['name'].$cif['name'];
            }
            $res = $this->gaode_address_to_lo_la($addres);
            if($res)
            {
                DsUser::query()->where('user_id',$user_id)
                    ->update(['longitude' =>$res['lo'], 'latitude' =>$res['la'] ]);
                $user_location_id = DsUserLocation::query()->where('user_id',$user_id)->value('user_location_id');
                if($user_location_id)
                {
                    DsUserLocation::query()->where('user_id',$user_id)->update([
                        't_latitude'    => $res['la'],
                        't_longitude'   => $res['lo'],
                    ]);
                }else{
                    $davv = [
                        'user_id'       => $user_id,
                        't_latitude'    => $res['la'],
                        't_longitude'   => $res['lo'],
                    ];
                    DsUserLocation::query()->insert($davv);
                }
            }
        }
    }

    /**
     * 高德地址转经纬度
     * @param $address
     * @return array|false
     */
    protected function gaode_address_to_lo_la($address)
    {
        //获取系统配置
        $gaodekey = '2d0898f58c40d44364a8d22e44cdb3c2';
        $api = "https://restapi.amap.com/v3/geocode/geo?key=$gaodekey&output=JSON&address=$address&batch=false";
        $content = $this->http->get($api)->getBody()->getContents();
        $arr = json_decode($content,true);
        if(empty($arr))
        {
            return false;
        }
        if($arr['status'] != 1)
        {
            return false;
        }
        if(empty($arr['geocodes']['0']['location']))
        {
            return false;
        }
        $fen = explode(',',$arr['geocodes']['0']['location']);
        return [
            'lo' => $fen[0].mt_rand(111111,999999),
            'la' => $fen[1].mt_rand(111111,999999),
        ];
    }


    /**
     * 导入注册
     * @param $info
     */
    public function daoru_reg($info)
    {
        $id = $info['daoru_id'];
        $data = DsDaoru::query()->where('daoru_id',$id)->value('data');
        if(empty($data))
        {
            //写入错误
            DsDaoru::query()->where('daoru_id',$id)->update(['res' =>json_encode(['0' => '导入的数据为空']) ]);
        }
        $data = json_decode($data,true);
        for ($i=0;$i<count($data);$i++)
        {
            if($i == 0)
            {
                //判断第一个是否注册
                $fir = DsUser::query()->where('mobile',$data[$i])->first();
                if(empty($fir))
                {
                    //写入错误
                    $this->redis3->sAdd('daoru_id_'.$id,$data[$i].' : 第一个账号未注册'."\r\n");
                    break;
                }
                $this->p_mobile = $data[$i];
            }else{
                //注册
                $reo = $this->import_registration($data[$i],$this->p_mobile);
                $this->redis3->sAdd('daoru_id_'.$id,$data[$i].' : '.$reo['msg']."\r\n");
                if($reo['code'] == 200)
                {
                    $this->reg($reo);
                    //注册成功
//                    $infos = [
//                        'user_id'       => $reo['user_id'],
//                        'pid'           => $reo['pid'],
//                        'type'          => 1
//                    ];
//                    $job = ApplicationContext::getContainer()->get(DriverFactory::class);
//                    $job->get('register')->push(new Register($infos));
//                    //修改上级手机号为当前手机号
//                    $this->p_mobile = $data[$i];
                }
            }
        }
        DsDaoru::query()->where('daoru_id',$id)->update(['res' =>'ok' ]);
    }

    /**
     * 导入注册
     * @param $mobile
     * @param $parent
     * @return array
     */
    public function import_registration($mobile,$parent)
    {
        //检测请求频繁
        $ch_res = $this->check_often($mobile);
        if(!$ch_res)
        {
            return ['code' =>10001,'msg' => '繁忙' ];
        }
        $this->start_often($mobile);
        $time       = time();
        $password = '20212012021020120120';
//        $password = '123456';
        // 查找用户信息
        $user_id = DsUser::query()->where('mobile',$mobile)->value('user_id');
        if($user_id)
        {
            return ['code' =>10001,'msg' => '此手机号已注册' ];
        }
        //获取上级信息
        $parent_info = DsUser::query()->where('mobile',$parent)->select('user_id','login_status')->first();
        if(empty($parent_info))
        {
            return ['code' =>10001,'msg' => '邀请码不存在 请重新填写' ];
        }
        //检测上级信息
        $parent_info = $parent_info->toArray();
        if($parent_info['login_status'] == 1)
        {
            return ['code' =>10001,'msg' => '邀请者账号安全存在风险已被限制' ];
        }
        $parent_id = $parent_info['user_id'];
        $num = 1;
        $pid = $parent_id;
        //数据库查最后一次的id
        $son_res = DsUserLine::query()->where('user_id',$parent_id)->orderByDesc('user_line_id')->first();
        if(!empty($son_res))
        {
            $son_res = $son_res->toArray();
            $num = $son_res['num']+$num;
            $pid = $son_res['son_id'];
        }
        // 注册  用户信息入库
        $userDatas = [
            'username'      => $mobile,
            'nickname'      => '今后满座-'.$this->get_mobile_end_4($mobile),
            'password'      => password_hash($password,PASSWORD_DEFAULT),
            'mobile'        => $mobile,             //手机
            'pid'           => $pid,                //上级id
            'reg_time'      => $time,               //注册时间
            'avatar'        => 'https://jinhoumanzuo.oss-cn-hangzhou.aliyuncs.com/logo.png', //默认头像
            'ip'            => Context::get('ip'),           //注册ip
            'longitude'     => Context::get('longitude'),
            'latitude'      => Context::get('latitude'),
            'auth'          => 0,
            'last_do_time'  => $time,
//            'city_id'       => DsCity::query()->where('level',3)->inRandomOrder()->value('city_id'),
        ];
        $user_id = DsUser::query()->insertGetId($userDatas);
        if($user_id)
        {
            //为当前用户绑定上级id
            $this->redis5->set($user_id.'_pid' ,strval($pid));
            $line = [
                'user_id'   => $parent_id,
                'son_id'    => $user_id,
                'num'       => $num,
                'time'      => $time,
            ];
            DsUserLine::query()->insert($line);
            return ['code' =>200,'msg' => '注册成功 用户id：'.$user_id,'user_id' => $user_id, 'pid' =>$pid,'mobile'=> $mobile,'password' => $userDatas['password'] ];
        }else{
            return ['code' =>10001,'msg' => '邀请者已被注销' ];
        }
    }

    /**
     * 只提取后4位
     * @param $mobile
     * @return false|string
     */
    protected function get_mobile_end_4($mobile)
    {
        return substr(strval($mobile),-4, 4);
    }

    /**
     * @param $mobile
     * @return string|string[]
     */
    protected function replace_mobile_end($mobile)
    {
        return substr_replace(strval($mobile), '****',3, 4);
    }
    /**
     * 判断是否请求过于频繁
     * @param $user_id
     * @return bool
     */
    protected function check_often($user_id)
    {
        $aaa = $this->redis4->get($user_id.'_start_often');
        if($aaa == 2)
        {
            return false;
        }
        return true;
    }

    /**
     * 增加请求频繁
     * @param $user_id
     * @param int $time
     */
    protected function start_often($user_id,$time = 2)
    {
        $this->redis4->set($user_id.'_start_often',2);
        $this->redis4->expire($user_id.'_start_often',$time);
    }

    //为所有上级团队用户表增加当前用户id
    public function add_team($user_id)
    {
        $pid =  $this->redis5->get($user_id.'_pid');
        if($pid > 0)
        {
            //为所有上级团队用户表增加当前用户id
//            $this->redis5->incr($pid.'_team_user');
            $this->redis6->sAdd($pid.'_team_user' ,$this->user_id);
            //计算上级的大区id是否变化
//            $this->daqu($pid,$user_id);
            $this->add_team($pid);
        }
    }
    /**
     * 计算上级的大区id是否变化
     * @param $user_id
     * @param $sid
     */
    protected function daqu($user_id,$sid)
    {
        //查询他的大区id是谁
        $_daqu_id = $this->redis5->get($user_id.'_daqu_id');
        if(!$_daqu_id)
        {
            //设置大区id
            $this->redis5->set($user_id.'_daqu_id',$sid);
        }else{
            //查找大区id团队人数
//            $da_ren = $this->redis5->get($_daqu_id.'_team_user')+1;
            $da_ren = $this->redis6->sCard($_daqu_id.'_team_user')+1;
            //查找子id大区人数
//            $zi_ren = $this->redis5->get($sid.'_team_user')+1;
            $zi_ren = $this->redis6->sCard($sid.'_team_user')+1;
            if($zi_ren > $da_ren)
            {
                //设置新大区id
                $this->redis5->set($user_id.'_daqu_id',$sid);
            }
        }
    }

    //生成环信聊天token
    protected function add_huanxin_token($user_id){
        $cms = make(ChatIm::class);
        $go = new Parallel();
        $go->add(function () use ($cms, $user_id)
        {
            $cms->get_user_token($user_id);
        });
        $go->wait();
    }


    /**
     * @access 生成唯一字符串
     * @param mixed    $type   [字符串的类型]
     * 0 = 纯数字字符串；
     * 1 = 小写字母字符串；
     * 2 = 大写字母字符串；
     * 3 = 大小写数字字符串；
     * 4 = 字符；
     * 5 = 数字，小写，大写，字符混合；
     * 6 = 数字+小写；
     * @param mixed    $length [字符串的长度]
     * @param mixed    $time   [是否带时间 1 = 带，0 = 不带]
     * @return string
     **/
    protected static function ma_rand($type = 6,$length = 6,$time=0){
        $str = $time == 0 ? '':date('YmdHis',time()).mt_rand(10000000,99999999);
        switch ($type) {
            case 0:
                for((int)$i = 0;$i <= $length;$i++){
                    if(mb_strlen($str) == $length){
                        $str = $str;
                    }else{
                        $str .= rand(0,9);
                    }
                }
                break;
            case 1:
                for((int)$i = 0;$i <= $length;$i++){
                    if(mb_strlen($str) == $length){
                        $str = $str;
                    }else{
                        $rand = "qwertyuioplkjhgfdsazxcvbnm";
                        $str .= $rand{mt_rand(0,25)};
                    }
                }
                break;
            case 2:
                for((int)$i = 0;$i <= $length;$i++){
                    if(mb_strlen($str) == $length){
                        $str = $str;
                    }else{
                        $rand = "QWERTYUIOPLKJHGFDSAZXCVBNM";
                        $str .= $rand{mt_rand(0,25)};
                    }
                }
                break;
            case 3:
                for((int)$i = 0;$i <= $length;$i++){
                    if(mb_strlen($str) == $length){
                        $str = $str;
                    }else{
                        $rand = "123456789qwertyuioplkjhgfdsazxcvbnmQWERTYUIOPLKJHGFDSAZXCVBNM";
                        $str .= $rand{mt_rand(0,35)};
                    }
                }
                break;
            case 4:
                for((int)$i = 0;$i <= $length;$i++){
                    if(mb_strlen($str) == $length){
                        $str = $str;
                    }else{
                        $rand = "!@#$%^&*()_+=-~`";
                        $str .= $rand{mt_rand(0,15)};
                    }
                }
                break;
            case 5:
                for((int)$i = 0;$i <= $length;$i++){
                    if(mb_strlen($str) == $length){
                        $str = $str;
                    }else{
                        $rand = "123456789qwertyuioplkjhgfdsazxcvbnmQWERTYUIOPLKJHGFDSAZXCVBNM!@#$%^&*()_+=-~`";
                        $str .= $rand{mt_rand(0,52)};
                    }
                }
            case 6:
                for((int)$i = 0;$i <= $length;$i++){
                    if(mb_strlen($str) == $length){
                        $str = $str;
                    }else{
                        $rand = "1234567890qwertyuioplkjhgfdsazxcvbnm";
                        $str .= $rand{mt_rand(0,35)};
                    }
                }
                break;
        }
        return $str;
    }
    protected function ma_insert($user_id,$rand){
        $re = DsUserMaLog::query()->insert([
            'user_id' => $user_id,
            'type' => 0,
            'ma' => $rand,
            'time' => time(),
        ]);
        if($re){
             return true;
        }else{
            return false;
        }
    }
    protected function ma_string($user_id=0){
            $rand = $this->ma_rand();
            $number_5 = $this->redis3->get('set_ma_number_'.$rand);
            if($number_5 > 10){
                return false;
            }
            $res = DsUserMaLog::query()->where('ma',$rand)->value('type');
            if(empty($res)){//没有重复的
                $this->ma_insert($user_id,$rand);
            }else{
                if($number_5){
                    $this->redis3->incr('set_ma_number_'.$rand);
                }else{
                    $this->redis3->set('set_ma_number_'.$rand,1,20);
                }
                $this->ma_string($user_id);
            }
            return true;
    }
    //生成随机直推排线码 一次2000个不包括重复的
    public function ma_add(){
        for ($i=1;$i<=1000;$i++){
            $this->ma_string();
        }
    }
    public function ma_user_edit($user_id)
    {
        //检测用户是否已经绑定
        $user_zhi_ma = DsUser::query()->where('user_id',$user_id)->select('ma_zhitui','ma_paixian')->first();
        if(!empty($user_zhi_ma))
        {
            $user_zhi_ma = $user_zhi_ma->toArray();
            if(empty($user_zhi_ma['ma_zhitui']) && empty($user_zhi_ma['ma_paixian']))
            {
                //取redis
                $code = $this->redis0->sPop('create_reg_num_qu',2);
                if(!$code)
                {
                    //生成一下
                    $ree = make(RandomGeneration::class);
                    $ree->save_reg_num();
                    return;
                }
                if(count($code) == 2)
                {
                    $update = ['ma_zhitui' =>$code[0],'ma_paixian' => $code[1] ];
                    DsUser::query()->where('user_id',$user_id)->update($update);
                }
            }elseif(empty($user_zhi_ma['ma_zhitui']))
            {
                //取redis
                $code = $this->redis0->sPop('create_reg_num_qu');
                if(!$code)
                {
                    //生成一下
                    $ree = make(RandomGeneration::class);
                    $ree->save_reg_num();
                    return;
                }
                $update = ['ma_zhitui' =>$code ];
                DsUser::query()->where('user_id',$user_id)->update($update);
            }else{
                //取redis
                $code = $this->redis0->sPop('create_reg_num_qu');
                if(!$code)
                {
                    //生成一下
                    $ree = make(RandomGeneration::class);
                    $ree->save_reg_num();
                    return;
                }
                $update = ['ma_paixian' =>$code ];
                DsUser::query()->where('user_id',$user_id)->update($update);
            }
        }
    }

}