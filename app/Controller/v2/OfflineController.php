<?php

declare(strict_types=1);

namespace App\Controller\v2;

use App\Controller\async\Huafeido;
use App\Controller\XiaoController;
use App\Model\Chuli;
use App\Model\DsBang;
use App\Model\DsCinema;
use App\Model\DsCinemaCity;
use App\Model\DsCinemaCityChild;
use App\Model\DsCinemaUserOrder;
use App\Model\DsCinemaVideo;
use App\Model\DsCinemaPaiqi;
use App\Model\DsCity;
use App\Model\DsCzHuafeiUserOrder;
use App\Model\DsCzUserOrder;
use App\Model\DsErrorLog;
use App\Model\DsOffline;
use App\Model\DsOfflineBangbang;
use App\Model\DsUser;
use App\Model\DsUserTongzheng;
use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;



use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Annotation\Middlewares;
use App\Middleware\SignatureMiddleware;
use App\Middleware\UserMiddleware;
use Hyperf\Utils\Context;

use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Annotation\Controller;

use Hyperf\Utils\Parallel;
use Swoole\Exception;

/**
 *
 * @Middlewares({
 *     @Middleware(SignatureMiddleware::class),
 *     @Middleware(UserMiddleware::class)
 * })
 * @package App\Controller\v2
 * @Controller(prefix="v2/offline")
 */
class OfflineController extends XiaoController
{

    /**
     * 商家入驻
     * @RequestMapping(path="offline_add",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function offline_add(){
        $user_id = Context::get('user_id');
        $userInfo = Context::get('userInfo');

        $is_offline_status = $this->redis0->get('is_offline_status');
        if($is_offline_status != 1){
            return $this->withError('当前暂未开放店铺');
        }
        $offline_money = $this->redis0->get('offline_money');
        if(empty($offline_money)){
            return $this->withError('当前暂未开放入驻');
        }

        $name = $this->request->post('name','');
        $address = $this->request->post('address','');
        $address_detailed = $this->request->post('address_detailed','');
        $sheng_id = $this->request->post('sheng_id',0);
        $shi_id = $this->request->post('shi_id',0);
        $qu_id = $this->request->post('qu_id',0);
        $longitude = $this->request->post('longitude','');
        $latitude = $this->request->post('latitude','');
        $introduction = $this->request->post('introduction','');
        $img = $this->request->post('img','');//多个英文逗号隔开
        $mobile = $this->request->post('mobile','');

        $this->check_often($user_id);
        $this->start_often($user_id);

        if(empty($name)){
            return $this->withError('请填写名称');
        }
        if(empty($img)){
            return $this->withError('至少1张图片');
        }
        if(empty($address)){
            return $this->withError('请选择省市区');
        }
        if(empty($address_detailed)){
            return $this->withError('请填写详细街道镇乡门牌号');
        }
        if(empty($sheng_id)){
            return $this->withError('请选择省ID');
        }
        if(empty($shi_id)){
            return $this->withError('请选择市ID');
        }
        if(empty($qu_id)){
            return $this->withError('请选择区县ID');
        }
        if(empty($longitude)){
            return $this->withError('请选择经度');
        }
        if(empty($latitude)){
            return $this->withError('请选择维度');
        }
        if(empty($introduction)){
            return $this->withError('请填写介绍');
        }
        if($mobile){
            if(!$this->isMobile($mobile)){
                return $this->withError('请填写合规的手机号');
            }
        }else{
            $mobile = $userInfo['mobile'];
        }

        //验证码校验
        $code = $this->request->post('code','');
        if(empty($code))
        {
            return $this->withError('验证码不能为空');
        }
        $this->_checkCode($userInfo['mobile'],'offline_add',$code,time(),$user_id);

        if(mb_strlen($name,"utf-8") > 30){
            return $this->withError('名称不能超过30字');
        }
        if(mb_strlen($address,"utf-8") > 150){
            return $this->withError('省市区不能超过150字');
        }
        if(mb_strlen($address_detailed,"utf-8") > 150){
            return $this->withError('详细地址不能超过150字');
        }
        if(mb_strlen($introduction,"utf-8") > 200){
            return $this->withError('介绍不能超过200字');
        }
        if(!is_numeric($sheng_id) || !is_numeric($shi_id) || !is_numeric($qu_id) || !is_numeric($longitude) || !is_numeric($latitude)){
            return $this->withError('省市区id或经度纬度必须是数字类型');
        }

        //查询是否存在店铺
        $offline_status = DsOffline::query()->where('user_id',$user_id)->orderByDesc('offline_id')->first();
        if(!empty($offline_status)){
            $offline_status = $offline_status->toArray();
            if($offline_status['offline_status'] == '1'){
                if(time() > $offline_status['offline_end_time']){
                    return $this->withError('你已过期，请前去续费');
                }
                return $this->withError('你已通过，请不要重复提交');
            }

            if($offline_status['offline_status'] == '2'){
                return $this->withError('正在审核中，请耐心等待');
            }

        }

        $re = DsCity::query()->where('city_id',$sheng_id)->value('name');
        if(empty($re)){
            return $this->withError('不是省');
        }
        $re2 = DsCity::query()->where('city_id',$shi_id)->value('name');
        if(empty($re2)){
            return $this->withError('不是市');
        }
        $re3 = DsCity::query()->where('city_id',$qu_id)->value('name');
        if(empty($re3)){
            return $this->withError('不是区县');
        }


        if($userInfo['tongzheng'] < $offline_money)
        {
            $cha = round($offline_money - $userInfo['tongzheng'],$this->xiaoshudian);
            return $this->withError('通证不足!,还差'.$cha);
        }
        $kou_status = DsUserTongzheng::del_tongzheng($user_id,$offline_money,'开通店铺扣除');
        if(empty($kou_status)){
            return $this->withError('当前人数较多，请稍后再试');
        }


        if(!preg_match('/^http/',$img)) {
            return $this->withError('图片路径必须http开头');
        }

        $imghou = substr(strrchr($img,'.'),1);
        if($imghou){//匹配后缀是否允许
            if(!in_array($imghou,['bmp','jpg','png','tif','gif','pcx','tga','exif','fpx','svg'])){
                return $this->withError('格式必须为图片');
            }
        }else{
            return $this->withError('格式必须为图片!');
        }

        if($this->check_search_str('*',strval($name)) || $this->check_search_str('<',strval($name)) || $this->check_search_str('#',strval($name))){
            return $this->withError('不能包含特殊字符');
        }

        $data = [
            'img' => $img,
            'name' => $name,
            'address' => $address,
            'address_detailed' => $address_detailed,
            'sheng_id' => $sheng_id,
            'shi_id' => $shi_id,
            'qu_id' => $qu_id,
            'longitude' => $longitude,
            'latitude' => $latitude,
            'introduction' => $introduction,
            'offline_status' => 2,//审核中
            'mobile' => $mobile,
            'price' => $offline_money,
        ];
        if(!empty($offline_status)){
            $data['offline_in_time'] = time();
            $data['offline_end_time'] = time() + 86400*365;
            $res_in = DsOffline::query()->where('user_id',$user_id)->where('offline_id',$offline_status['offline_id'])->update($data);
        }else{
            $data['user_id'] = $user_id;
            $data['offline_in_time'] = time();
            $data['offline_end_time'] = time() + 86400*365;
            $res_in = DsOffline::query()->insert($data);
        }

        if($res_in){
            return $this->withSuccess('开通成功！等待审核');
        }else{
           $kou_tui = DsUserTongzheng::add_tongzheng($user_id,$offline_money,'开通店铺失败退回');
           if(empty($kou_tui)){
              DsErrorLog::add_log('开通店铺失败退回失败',json_encode($data),'开通店铺失败退回失败');
           }
            return $this->withError('开通失败，请稍后重新开通！');
        }
    }

    /**
     *
     * 续费
     * @RequestMapping(path="offline_xufei",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function offline_xufei(){
        $user_id = Context::get('user_id');
        $userInfo = Context::get('userInfo');

        $offline_id = $this->request->post('offline_id',0);

        $is_offline_status = $this->redis0->get('is_offline_status');
        if($is_offline_status != 1){
            return $this->withError('当前暂未开放店铺');
        }
        $offline_money = $this->redis0->get('offline_money');
        if(empty($offline_money)){
            return $this->withError('当前暂未开放入驻');
        }

        if(empty($offline_id)){
            return $this->withError('店铺不能为空');
        }

        $this->check_often($user_id);
        $this->start_often($user_id);

        //验证码校验
        $code = $this->request->post('code','');
        if(empty($code))
        {
            return $this->withError('验证码不能为空');
        }
        $this->_checkCode($userInfo['mobile'],'offline_xufei',$code,time(),$user_id);

        //查询是否存在店铺
        $offline_status = DsOffline::query()->where('user_id',$user_id)->where('offline_id',$offline_id)->orderByDesc('offline_id')->first();
        if(!empty($offline_status)){
            if($offline_status['offline_status'] == '2'){
                return $this->withError('正在审核，请稍后');
            }
            if($offline_status['offline_status'] != 1){
                return $this->withError('你当前不是正常店铺，不能续费');
            }
        }else{
            return $this->withError('你还没入驻，请前去入驻');
        }

        if($userInfo['tongzheng'] < $offline_money)
        {
            $cha = round($offline_money - $userInfo['tongzheng'],$this->xiaoshudian);
            return $this->withError('通证不足!,还差'.$cha);
        }

        $kou_status = DsUserTongzheng::del_tongzheng($user_id,$offline_money,'续费店铺扣除');
        if(empty($kou_status)){
            return $this->withError('当前人数较多，请稍后再试');
        }

        if($offline_status['offline_end_time'] > 0){
            $data['offline_end_time'] = $offline_status['offline_end_time'] + 86400*365;
        }else{
            $data['offline_end_time'] = time() + 86400*365;
        }
        $data['price'] = $offline_money;

        $res_in = DsOffline::query()->where('user_id',$user_id)->where('offline_id',$offline_id)->update($data);

        if($res_in){
            return $this->withSuccess('续费成功！到期时间：'.date('Y-m-d',$data['offline_end_time']));
        }else{
            $kou_tui = DsUserTongzheng::add_tongzheng($user_id,$offline_money,'开通店铺失败退回');
            if(empty($kou_tui)){
                DsErrorLog::add_log('续费店铺失败退回失败',json_encode($data),'开通店铺失败退回失败');
            }
            return $this->withError('续费失败，请稍后重新开通！');
        }
    }


    /**
     * 帮帮加入
     * @RequestMapping(path="bang_add",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function bang_add(){
        $user_id = Context::get('user_id');
        $userInfo = Context::get('userInfo');

        $bang_money = $this->redis0->get('bang_money');
        if(empty($bang_money)){
            return $this->withError('加入帮帮即将开启！');
        }
        $bang_is_on = $this->redis0->get('bang_is_on');
        if($bang_is_on != 1){
            return $this->withError('加入帮帮暂时关闭！');
        }

        $name = $this->request->post('name','');
        $fuwu_banjing = $this->request->post('fuwu_banjing','');
        $mobile = $this->request->post('mobile','');
        $sheng_id = $this->request->post('sheng_id',0);
        $shi_id = $this->request->post('shi_id',0);
        $qu_id = $this->request->post('qu_id',0);
        $address = $this->request->post('address',0);
        $banner = $this->request->post('banner',0);
        $longitude = $this->request->post('longitude','');
        $latitude = $this->request->post('latitude','');
        $introduction = $this->request->post('introduction','');

        $this->check_often($user_id);
        $this->start_often($user_id);

        $this->_check_auth();

        if(empty($name)){
            $name = $userInfo['nickname'];
        }
        if(empty($banner)){
            $banner = $userInfo['avatar'];
        }
        if(empty($address)){
            return $this->withError('请填写详细地址');
        }
        if(empty($sheng_id)){
            return $this->withError('请选择省ID');
        }
        if(empty($shi_id)){
            return $this->withError('请选择市ID');
        }
        if(empty($qu_id)){
            return $this->withError('请选择区县ID');
        }
        if(empty($longitude)){
            return $this->withError('请选择经度');
        }
        if(empty($latitude)){
            return $this->withError('请选择维度');
        }
        if(empty($introduction)){
            return $this->withError('请填写介绍');
        }
        if($mobile){
            if(!$this->isMobile($mobile)){
                return $this->withError('请填写合规的手机号');
            }
        }else{
            $mobile = $userInfo['mobile'];
        }
        if(empty($fuwu_banjing)){
            return $this->withError('请填写服务半径');
        }

        //验证码校验
        $code = $this->request->post('code','');
        if(empty($code))
        {
            return $this->withError('验证码不能为空');
        }
        $this->_checkCode($userInfo['mobile'],'bang_add',$code,time(),$user_id);

        if(mb_strlen($address,"utf-8") > 150){
            return $this->withError('地址不能超过150字');
        }
        if(mb_strlen($introduction,"utf-8") > 200){
            return $this->withError('介绍不能超过200字');
        }
        if(!is_numeric($sheng_id) || !is_numeric($shi_id) || !is_numeric($qu_id) || !is_numeric($longitude) || !is_numeric($latitude)){
            return $this->withError('省市区id或经度纬度必须是数字类型');
        }

        //查询是否存在店铺
        $offline_status = DsBang::query()->where('user_id',$user_id)->orderByDesc('bang_id')->first();
        if(!empty($offline_status)){
            $offline_status = $offline_status->toArray();
            if($offline_status['status'] == '1'){
                if(time() > $offline_status['offline_end_time']){
                    return $this->withError('你已过期，请前去续费');
                }
                return $this->withError('你已通过，请不要重复提交');
            }
            if($offline_status['status'] == '2'){
                return $this->withError('正在审核中，请耐心等待');
            }
        }

        $re = DsCity::query()->where('city_id',$sheng_id)->value('name');
        if(empty($re)){
            return $this->withError('不是省');
        }
        $re2 = DsCity::query()->where('city_id',$shi_id)->value('name');
        if(empty($re2)){
            return $this->withError('不是市');
        }
        $re3 = DsCity::query()->where('city_id',$qu_id)->value('name');
        if(empty($re3)){
            return $this->withError('不是区县');
        }
        if(!preg_match('/^http/',$banner)) {
            return $this->withError('图片路径必须http开头');
        }

        if($this->check_search_str('*',strval($name)) || $this->check_search_str('<',strval($name)) || $this->check_search_str('#',strval($name))){
            return $this->withError('不能包含特殊字符');
        }
        if($userInfo['tongzheng'] < $bang_money)
        {
            $cha = round($bang_money - $userInfo['tongzheng'],$this->xiaoshudian);
            return $this->withError('通证不足!,还差'.$cha);
        }
        $kou_status = DsUserTongzheng::del_tongzheng($user_id,$bang_money,'加入帮帮扣除');
        if(empty($kou_status)){
            return $this->withError('当前人数较多，请稍后再试');
        }

        $data = [
            'banner' => $banner,
            'name' => $name,
            'fuwu_banjing' => $fuwu_banjing,
            'address' => $address,
            'sheng_id' => $sheng_id,
            'shi_id' => $shi_id,
            'qu_id' => $qu_id,
            'longitude' => $longitude,
            'latitude' => $latitude,
            'introduction' => $introduction,
            'status' => 1,//
            'mobile' => $mobile,
            'tongzheng' => $bang_money,
        ];
        if(!empty($offline_status)){
            $data['time'] = time();
            $data['offline_end_time'] = time() + 86400*365;
            $res_in = DsBang::query()->where('user_id',$user_id)->where('bang_id',$offline_status['bang_id'])->update($data);
        }else{
            $data['user_id'] = $user_id;
            $data['time'] = time();
            $data['offline_end_time'] = time() + 86400*365;
            $res_in = DsBang::query()->insert($data);
        }

        if($res_in){
            return $this->withSuccess('提交成功');
        }else{
            $kou_tui = DsUserTongzheng::add_tongzheng($user_id,$bang_money,'加入帮帮失败退回');
            if(empty($kou_tui)){
                DsErrorLog::add_log('加入帮帮失败退回失败',json_encode($data),'加入帮帮失败退回失败');
            }
            return $this->withError('加入帮帮失败，请稍后重新加入！');
        }
    }


    /**
     *
     * 帮帮-续费
     * @RequestMapping(path="bang_xufei",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function bang_xufei(){
        $user_id = Context::get('user_id');
        $userInfo = Context::get('userInfo');

        $bang_money = $this->redis0->get('bang_money');
        if(empty($bang_money)){
            return $this->withError('加入帮帮即将开启！');
        }
        $bang_is_on = $this->redis0->get('bang_is_on');
        if($bang_is_on != 1){
            return $this->withError('加入帮帮暂时关闭！');
        }

        $bang_id = $this->request->post('bang_id',0);
        if(empty($bang_id)){
            return $this->withError('请选择帮帮');
        }

        $this->check_often($user_id);
        $this->start_often($user_id);

        //验证码校验
        $code = $this->request->post('code','');
        if(empty($code))
        {
            return $this->withError('验证码不能为空');
        }
        $this->_checkCode($userInfo['mobile'],'bang_xufei',$code,time(),$user_id);

        //查询是否存在店铺
        $offline_status = DsBang::query()->where('user_id',$user_id)->where('bang_id',$bang_id)->orderByDesc('bang_id')->first();
        if(!empty($offline_status)){
            $offline_status = $offline_status->toArray();
            if($offline_status['status'] == '2'){
                return $this->withError('正在审核，请稍后');
            }
            if($offline_status['status'] != 1){
                return $this->withError('你当前不是正常店铺，不能续费');
            }
        }else{
            return $this->withError('你还没加入帮帮，请前去加入');
        }

        if($userInfo['tongzheng'] < $bang_money)
        {
            $cha = round($bang_money - $userInfo['tongzheng'],$this->xiaoshudian);
            return $this->withError('通证不足!,还差'.$cha);
        }

        $kou_status = DsUserTongzheng::del_tongzheng($user_id,$bang_money,'续费帮帮扣除');
        if(empty($kou_status)){
            return $this->withError('当前人数较多，请稍后再试');
        }

        if($offline_status['offline_end_time'] > 0){
            $data['offline_end_time'] = $offline_status['offline_end_time'] + 86400*365;
        }else{
            $data['offline_end_time'] = time() + 86400*365;
        }
        $data['tongzheng'] = round($bang_money,4);

        $res_in = DsBang::query()->where('user_id',$user_id)->where('bang_id',$bang_id)->update($data);

        if($res_in){
            return $this->withSuccess('续费成功！到期时间：'.date('Y-m-d',$data['offline_end_time']));
        }else{
            $kou_tui = DsUserTongzheng::add_tongzheng($user_id,$bang_money,'续费帮帮失败退回');
            if(empty($kou_tui)){
                DsErrorLog::add_log('续费帮帮失败退回失败',json_encode($data),'续费帮帮失败退回失败');
            }
            return $this->withError('续费失败，请稍后重新操作！');
        }
    }








}
