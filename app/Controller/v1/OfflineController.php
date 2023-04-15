<?php

declare(strict_types=1);

namespace App\Controller\v1;

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
 * @package App\Controller\v1
 * @Controller(prefix="v1/offline")
 */
class OfflineController extends XiaoController
{

    /**
     * 检测商家是否是正常
     * @param $user_id
     * @return \Hyperf\Database\Model\Builder|\Hyperf\Database\Model\Model|object
     */
    protected function check_offline($user_id){
        $res = DsOffline::query()->where('user_id',$user_id)->orderByDesc('offline_id')->first();
        if(empty($res)){
            throw new Exception('你还没有店铺，请前去入驻！', 500);
        }
        if(time() > $res['offline_end_time'] ){
            throw new Exception('你的店铺已过期，请前去续费！', 500);
        }

        switch ($res['offline_status']){
            case '0':
                throw new Exception('你当前店铺已下线，请联系客服处理！', 500);
                break;
            case '2':
                throw new Exception('你当前店铺在审核中，请耐心等待！', 500);
                break;
            case '3':
                throw new Exception('你当前店铺已被下线。备注：'.$res['offline_content'], 500);
                break;
            default:
                if($res['offline_status']>0){
                    return $res;
                }else{
                    throw new Exception('没有该类型！', 500);
                }
                break;
        }
    }

    /**
     *
     * 商家入驻初始化
     * @RequestMapping(path="offline_in_csh",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function offline_in_csh(){
        $user_id = Context::get('user_id');
        $userInfo = Context::get('userIifo');

        $data['is_offline'] = 0;
        $data['content'] = '';
        $data['end_time'] = 0;
        $data['price'] = $this->redis0->get('offline_money');
        if(empty($data['price'])){
            return $this->withError('入驻商家即将开启！');
        }

        $res = DsOffline::query()->where('user_id',$user_id)->first();
        if($res){
            if ($res['offline_status'] == '1'){
                $data['is_offline'] = 1;
                if($res['offline_end_time'] > time()){
                    $data['end_time'] = $res['offline_end_time'];
                }
            }else{
                $data['content'] = $res['offline_content'];
            }
        }
        $data['offline_status'] = $res['offline_status'];

        return $this->withResponse('获取成功',$data);

    }

    /**
     * 商家入驻
     * @RequestMapping(path="offline_add",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function offline_add(){
        //检测版本
        $this->_check_version('1.1.3');

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

        //检查支付密码对不对
        $pay_password = $this->request->post('pay_password','');
        $this->_check_pay_password($pay_password);

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
     * 商家列表
     * @RequestMapping(path="offline_list",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function offline_list(){
        $user_id = Context::get('user_id');
        $userInfo = Context::get('userInfo');

        //用户经度纬度
        $latitude = $this->redis2->get('latitude_'.$user_id);
        $longitude = $this->redis2->get('longitude_'.$user_id);
        $shi_id = $this->redis2->get('cityCode_'.$user_id);
        if(empty($latitude) || empty($longitude) || empty($shi_id)){
            return $this->withError('请刷新定位或重新进入APP');
        }

        $keyword = $this->request->post('keyword','');

        $page     = $this->request->post('page',1);
        if($page <= 0)
        {
            $page = 1;
        }
        $data['more']           = '0';
        $data['data']           = [];


        //按距离 根据位置 before
        if($page <= 0)
        {
            $p1 = 0 *10;
        }else{
            $p1 = ($page-1) *10;
        }

        if($keyword){
            if($this->check_search_str('*',strval($keyword)) || $this->check_search_str('<',strval($keyword)) || $this->check_search_str('#',strval($keyword))){
                return $this->withError('不能包含特殊字符');
            }
            $sql_lod = "SELECT `img`,`name`,`address_detailed`,`mobile`,`introduction`,`offline_id`,`longitude`,`latitude`,ROUND(6378.138*2*ASIN(SQRT(POW(SIN((".$latitude."*PI()/180-latitude*PI()/180)/2),2)+COS(".$latitude."*PI()/180)*COS(latitude*PI()/180)*POW(SIN((".$longitude."*PI()/180-longitude*PI()/180)/2),2)))*1000) AS juli FROM ds_offline where `shi_id` = $shi_id and `offline_status` = 1 and `name` = '$keyword' ORDER BY juli ASC limit $p1,10";
        }else{
            $sql_lod = "SELECT `img`,`name`,`address_detailed`,`mobile`,`introduction`,`offline_id`,`longitude`,`latitude`,ROUND(6378.138*2*ASIN(SQRT(POW(SIN((".$latitude."*PI()/180-latitude*PI()/180)/2),2)+COS(".$latitude."*PI()/180)*COS(latitude*PI()/180)*POW(SIN((".$longitude."*PI()/180-longitude*PI()/180)/2),2)))*1000) AS juli FROM ds_offline where `shi_id` = $shi_id and `offline_status` = 1 ORDER BY juli ASC limit $p1,10";
        }

        $res = Db::select($sql_lod);
        if(!empty($res))
        {
            //转化为数组
            foreach ($res as $k => $v)
            {
                $v = (array)$v;
                $res[$k] = $v;
                //返回带单位
                if($v['juli'] > 1000)
                {
                    //km
                    $s = $v['juli'] /1000;
                    $mkm = 'km';
                }else{
                    //m
                    $s = $v['juli'];
                    $mkm = 'm';
                }
                $str = strval(round($s, 2));
                $datas[$k]['juli'] = $str.$mkm;

                $datas[$k]['img'] = explode(',',$v['img'])[0];
                $datas[$k]['img_all'] = explode(',',$v['img']);
                $datas[$k]['name']  = strval($v['name']);
                $datas[$k]['address_detailed']  = strval($v['address_detailed']);
                $datas[$k]['mobile']  = strval($v['mobile']);
                $datas[$k]['introduction']  = strval($v['introduction']);
                $datas[$k]['offline_id']  = $v['offline_id'];
                //$datas[$k]['juli']  = '80m';
                $datas[$k]['longitude']  = $v['longitude'];
                $datas[$k]['latitude']  = $v['latitude'];
            }
            $data['data']           = $datas;
        }

        //根据位置 end



//        $res = DsOffline::query()
//            ->orderByDesc('offline_id')
//            ->forPage($page,10)->get()->toArray();
//        if(!empty($res))
//        {
//            //查找是否还有下页数据
//            if(count($res) >= 10)
//            {
//                $data['more'] = '1';
//            }
//            //整理数据
//            $datas = [];
//            foreach ($res as $k => $v)
//            {
//                $datas[$k]['img'] = explode(',',$v['img'])[0];
//                $datas[$k]['img_all'] = explode(',',$v['img']);
//                $datas[$k]['name']  = strval($v['name']);
//                $datas[$k]['address_detailed']  = strval($v['address_detailed']);
//                $datas[$k]['mobile']  = strval($v['mobile']);
//                $datas[$k]['introduction']  = strval($v['introduction']);
//                $datas[$k]['offline_id']  = $v['offline_id'];
//                $datas[$k]['juli']  = '80m';
//                $datas[$k]['longitude']  = $v['longitude'];
//                $datas[$k]['latitude']  = $v['latitude'];
//            }
//            $data['data']           = $datas;
//        }


        return $this->withResponse('获取成功',$data);

    }

    /**
     * 我的店铺
     * @RequestMapping(path="offline_user",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function offline_user(){
        $user_id = Context::get('user_id');
        $userInfo = Context::get('userInfo');

        $data = DsOffline::query()->where('user_id',$user_id)->orderByDesc('offline_id')->first();
        if(empty($data)){
            return $this->withError('你还没开通，请前去开通');
        }
        $data = $data->toArray();
        if($data['offline_status'] == '2'){
            return $this->withError('你的店铺在审核中，请稍后');
        }

        $res = [];

        if($data['img']){
            $res['img'] = explode(',',$data['img']);
        }else{
            $res['img'] = [];
        }

        $res['name'] = $data['name'];
        $res['introduction'] = $data['introduction'];
        $res['address'] = $data['address'];
        $res['address_detailed'] = $data['address_detailed'];
        $res['mobile'] = $userInfo['mobile'];
        $res['offline_in_time'] = $this->replaceTime($data['offline_in_time']);
        $res['offline_end_time'] = $this->replaceTime($data['offline_end_time']);
        $res['offline_id'] = $data['offline_id'];

        $res['sheng_id'] = $data['sheng_id'];
        $res['shi_id'] = $data['shi_id'];
        $res['qu_id'] = $data['qu_id'];
        $res['longitude'] = $data['longitude'];
        $res['latitude'] = $data['latitude'];

        $res['is_end'] = 0;//未过期
        if($data['offline_end_time'] > time()){
            $res['is_end'] = 1;//已过期
        }

        $offline_money = $this->redis0->get('offline_money');
        if($offline_money>0){
            $res['offline_money'] = $offline_money;
        }else{
            $res['offline_money'] = 0;
        }

        return $this->withResponse('获取成功',$res);
    }

    /**
     *
     * 续费
     * @RequestMapping(path="offline_xufei",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function offline_xufei(){
        //检测版本
        $this->_check_version('1.1.3');

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

        //检查支付密码对不对
        $pay_password = $this->request->post('pay_password','');
        $this->_check_pay_password($pay_password);

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
     * 商家修改
     * @RequestMapping(path="offline_edit",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function offline_edit(){
        $user_id = Context::get('user_id');
        $userInfo = Context::get('userInfo');

        $offline_id = $this->request->post('offline_id','');

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

        $this->check_often($user_id);
        $this->start_often($user_id);

        if(empty($offline_id)){
            return $this->withError('请选择店铺！');
        }

        //查询是否存在店铺
        $offline_status = DsOffline::query()->where('user_id',$user_id)->where('offline_id',$offline_id)->orderByDesc('offline_id')->first();
        if(!empty($offline_status)){
            if(time() > $offline_status['offline_end_time']){
                return $this->withError('你已过期，请前去续费');
            }
        }else{
            return $this->withError('你当前没有店铺，请前去入住');
        }

        $update = [];
        if(!empty($name)){
            //$ads = $this->string_check_ok();
            //if($ads){var_dump($ads);}

            if(mb_strlen($name,"utf-8") > 30){
                return $this->withError('名称不能超过30字');
            }
            $update['name'] = $name;
        }
        if(!empty($img)){
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
            $update['img'] = $img;
        }
        if(!empty($address)){
            if(mb_strlen($address,"utf-8") > 150){
                return $this->withError('省市区不能超过150字');
            }
            $update['address'] = $address;
        }
        if(!empty($address_detailed)){
            if(mb_strlen($address_detailed,"utf-8") > 150){
                return $this->withError('详细地址不能超过150字');
            }
            $update['address_detailed'] = $address_detailed;
        }
        if(!empty($sheng_id)){
            if(!is_numeric($sheng_id)){
                return $this->withError('省市区id或经度纬度必须是数字类型');
            }
            $re = DsCity::query()->where('city_id',$sheng_id)->value('name');
            if(empty($re)){
                return $this->withError('不是省');
            }
            $update['sheng_id'] = $sheng_id;
        }
        if(!empty($shi_id)){
            if(!is_numeric($shi_id)){
                return $this->withError('省市区id或经度纬度必须是数字类型');
            }
            $re = DsCity::query()->where('city_id',$shi_id)->value('name');
            if(empty($re)){
                return $this->withError('不是省');
            }
            $update['shi_id'] = $shi_id;
        }
        if(!empty($qu_id)){
            if(!is_numeric($qu_id)){
                return $this->withError('省市区id或经度纬度必须是数字类型');
            }
            $re = DsCity::query()->where('city_id',$qu_id)->value('name');
            if(empty($re)){
                return $this->withError('不是区县');
            }
            $update['qu_id'] = $qu_id;
        }

        if(!empty($longitude)){
            if(!is_numeric($longitude)){
                return $this->withError('省市区id或经度纬度必须是数字类型');
            }
            $update['longitude'] = $longitude;
        }
        if(!empty($latitude)){
            if(!is_numeric($latitude)){
                return $this->withError('省市区id或经度纬度必须是数字类型');
            }
            $update['latitude'] = $latitude;
        }
        if(!empty($introduction)){
            if(mb_strlen($introduction,"utf-8") > 200){
                return $this->withError('介绍不能超过200字');
            }
            $update['introduction'] = $introduction;
        }
        if(!empty($update)){
            $res_in = DsOffline::query()->where('user_id',$user_id)->where('offline_id',$offline_id)->update($update);
            if($res_in){
                return $this->withSuccess('修改成功！');
            }else{
                return $this->withError('当前人数较多，请稍后再试');
            }
        }else{
            return $this->withError('当前什么都没修改');
        }
    }

    /**
     * 承兑列表
     * @RequestMapping(path="get_bangbang_list",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get_bangbang_list()
    {
        $user_id  = Context::get('user_id');
        $userInfo     = Context::get('userInfo');
        $page     = $this->request->post('page',1);
        if($page <= 0)
        {
            $page = 1;
        }

        $data['con1'] = [
            '1、对应消费渠道的通证承兑商将根据当日通证市场最低共识价值对通证消费比例进行动态调整。',
            '2、所有通证消费渠道的消费比例不得高或低于当日通证市场最低共识价值的2%，敬请所有用户共同监督执行。',
            '3、具备API或者集成SDK的消费渠道，可约谈任意通证承兑商进行对接，达成共识后，共同提交今后满座首席运营官进行审核，通过后可上架您的消费渠道。',
            '4、通证承兑商不得向2星以下用户提供通证承兑服务，且单次承兑服务不得低于2000.0000满座通证，敬请所有用户共同监督执行。',
            '5、通证承兑商的通证数据均来自今后满座用户在对应消费渠道的消费累积。',
        ];
        $data['more']           = '0';
        $data['data']         = [];
        $res = DsOfflineBangbang::query()
            ->where('status',1)
            ->orderByDesc('bangbang_id')
            ->forPage($page,10)->get()->toArray();
        if(!empty($res))
        {
            //查找是否还有下页数据
            if(count($res) == 10)
            {
                $data['more'] = '1';
            }
            //整理数据
            $datas = [];
            foreach ($res as $k => $v)
            {
                $user = DsUser::query()->where('user_id',$v['user_id'])->select('nickname','avatar')->first();
                if(!empty($user)){
                    $user = $user->toArray();
                }
                $datas[$k]['bangbang_id'] = $v['bangbang_id'];
                $datas[$k]['user_id']  =  $v['user_id'];
                $datas[$k]['cont']  = strval($v['nickname']);
                $datas[$k]['nickname']  = strval($user['nickname']);
                $datas[$k]['avatar']  = strval($user['avatar']);
            }
            $data['data']           = $datas;
        }

        return $this->withResponse('获取成功',$data);
    }

    /**
     * 帮帮-列表
     * @RequestMapping(path="bang_list",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function bang_list(){
        $page = $this->request->post('page',1);
        $type = $this->request->post('type',0);
        $keyword = $this->request->post('keyword',0);

        $user_id = Context::get('user_id');
        $userInfo = Context::get('userInfo');
        $city_id = $this->redis2->get('cityCode_'.$user_id);
        if(empty($city_id)){
            return $this->withError('请重新进入app后再访问');
        }
        //用户经度纬度
        $latitude = $this->redis2->get('latitude_'.$user_id);
        $longitude = $this->redis2->get('longitude_'.$user_id);
        if(empty($latitude) || empty($longitude)){
            return $this->withError('请重新进入app后再访问！');
        }


        $data['more']           = '0';
        $data['data']         = [];

        //按距离 根据位置 before
        if($page <= 0)
        {
            $p1 = 0 *10;
        }else{
            $p1 = ($page-1) *10;
        }

        $time = time();
        if(!empty($keyword)){
            $sql_lod = "SELECT *,ROUND(6378.138*2*ASIN(SQRT(POW(SIN((".$latitude."*PI()/180-latitude*PI()/180)/2),2)+COS(".$latitude."*PI()/180)*COS(latitude*PI()/180)*POW(SIN((".$longitude."*PI()/180-longitude*PI()/180)/2),2)))*1000) AS juli FROM ds_bang where `status` = 1 and `name` = '$keyword' ORDER BY juli ASC limit $p1,10";
        }else{
            $sql_lod = "SELECT *,ROUND(6378.138*2*ASIN(SQRT(POW(SIN((".$latitude."*PI()/180-latitude*PI()/180)/2),2)+COS(".$latitude."*PI()/180)*COS(latitude*PI()/180)*POW(SIN((".$longitude."*PI()/180-longitude*PI()/180)/2),2)))*1000) AS juli FROM ds_bang where `status` = 1 ORDER BY juli ASC limit $p1,10";
        }

        $res = Db::select($sql_lod);
        if(!empty($res))
        {
            //转化为数组
            foreach ($res as $k => $v)
            {
                $v = (array)$v;
                $res[$k] = $v;
                //返回带单位
                if($v['juli'] > 1000)
                {
                    //km
                    $s = $v['juli'] /1000;
                    $mkm = 'km';
                }else{
                    //m
                    $s = $v['juli'];
                    $mkm = 'm';
                }
                $str = strval(round($s, 2));
                $res[$k]['juli'] = $str.$mkm;
            }
        }
        //根据位置 end

        if(!empty($res))
        {
            //查找是否还有下页数据
            if(count($res) == 30)
            {
                $data['more'] = '1';
            }
            //整理数据
            $datas = [];
            foreach ($res as $k => $v)
            {
                $datas[$k]['img'] = '';
                if($v['banner']){
                    $datas[$k]['img'] = explode(',',$v['banner'])[0];
                }
                $userInfo2 = DsUser::query()->where('user_id',$v['user_id'])->value('nickname');
                $datas[$k]['name'] = strval($userInfo2);
                $datas[$k]['user_id'] = $v['user_id'];
                $datas[$k]['fuwu_banjing'] = strval($v['fuwu_banjing']);
                $datas[$k]['introduction'] = strval($v['introduction']);
                $datas[$k]['address'] = strval($v['address']);
                $datas[$k]['juli'] = strval($v['juli']);
                $datas[$k]['mobile'] = strval($v['mobile']);
                $datas[$k]['longitude'] = strval($v['longitude']);
                $datas[$k]['latitude'] = strval($v['latitude']);
            }
            $data['data']           = $datas;
        }
        return $this->withSuccess('获取成功',200,$data);
    }

    /**
     *
     * 帮帮-加入-初始化
     * @RequestMapping(path="bang_add_csh",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function bang_add_csh(){
        $user_id = Context::get('user_id');
        $userInfo = Context::get('userIifo');

        $data['content'] = '';
        $data['end_time'] = 0;
        $data['is_bang'] = 0;
        $data['offline_status'] = 0;
        $data['price'] = $this->redis0->get('bang_money');
        if(empty($data['price'])){
            return $this->withError('加入帮帮即将开启！');
        }
        $bang_is_on = $this->redis0->get('bang_is_on');
        if($bang_is_on != 1){
            return $this->withError('加入帮帮暂时关闭！');
        }

        $res = DsBang::query()->where('user_id',$user_id)->first();
        if($res){
            $res = $res->toArray();
            if ($res['status'] == '1'){
                $data['is_bang'] = 1;
                if($res['offline_end_time'] > time()){
                    $data['end_time'] = date('Y-m-d H:i:s',$res['offline_end_time']);
                }
            }else{
                $data['content'] = $res['cont'];
            }
            $data['offline_status'] = $res['status'];
        }

        return $this->withResponse('获取成功',$data);
    }

    /**
     * 帮帮加入
     * @RequestMapping(path="bang_add",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function bang_add(){
        //检测版本
        $this->_check_version('1.1.3');

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

        //检查支付密码对不对
        $pay_password = $this->request->post('pay_password','');
        $this->_check_pay_password($pay_password);

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
        //检测版本
        $this->_check_version('1.1.3');
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

        //检查支付密码对不对
        $pay_password = $this->request->post('pay_password','');
        $this->_check_pay_password($pay_password);

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

    /**
     * 我的帮帮
     * @RequestMapping(path="bang_user",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function bang_user(){
        $user_id = Context::get('user_id');
        $userInfo = Context::get('userInfo');

        $data = DsBang::query()->where('user_id',$user_id)->orderByDesc('bang_id')->first();
        if(empty($data)){
            return $this->withError('你还没开通，请前去开通');
        }
        $data = $data->toArray();

        $res = [];

        if($data['banner']){
            $res['img'] = explode(',',$data['banner'])[0];
        }else{
            $res['img'] = '';
        }

        $res['name'] = $data['name'];
        $res['introduction'] = $data['introduction'];
        $res['address'] = $data['address'];
        $res['offline_in_time'] = date('Y-m-d',$data['time']);
        $res['offline_end_time'] = date('Y-m-d',$data['offline_end_time']);
        $res['bang_id'] = $data['bang_id'];

        $res['sheng_id'] = $data['sheng_id'];
        $res['shi_id'] = $data['shi_id'];
        $res['qu_id'] = $data['qu_id'];
        $res['longitude'] = strval($data['longitude']);
        $res['latitude'] = strval($data['latitude']);

        $res['sheng_name'] = DsCity::query()->where('city_id',$data['sheng_id'])->value('name');
        $res['shi_name'] = DsCity::query()->where('city_id',$data['shi_id'])->value('name');
        $res['qu_name'] = DsCity::query()->where('city_id',$data['qu_id'])->value('name');

        $res['is_end'] = 0;//未过期
        if($data['offline_end_time'] < time()){
            $res['is_end'] = 1;//已过期
        }

        $offline_money = $this->redis0->get('bang_money');
        if($offline_money>0){
            $res['offline_money'] = $offline_money;
        }else{
            $res['offline_money'] = 0;
        }
        $res['fuwu_banjing'] = strval($data['fuwu_banjing']);
        $res['juli'] = strval(0);
        $res['mobile'] = strval($data['mobile']);

        return $this->withResponse('获取成功',$res);
    }

    /**
     * 帮帮-修改
     * @RequestMapping(path="bang_edit",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function bang_edit(){
        $user_id = Context::get('user_id');
        $userInfo = Context::get('userInfo');

        $offline_id = $this->request->post('bang_id','');

        $fuwu_banjing = $this->request->post('fuwu_banjing','');
        $address = $this->request->post('address','');
        $sheng_id = $this->request->post('sheng_id',0);
        $shi_id = $this->request->post('shi_id',0);
        $qu_id = $this->request->post('qu_id',0);
        $longitude = $this->request->post('longitude','');
        $latitude = $this->request->post('latitude','');
        $introduction = $this->request->post('introduction','');

        $this->check_often($user_id);
        $this->start_often($user_id);

        if(empty($offline_id)){
            return $this->withError('请选择！');
        }

        //查询是否存在
        $offline_status = DsBang::query()->where('user_id',$user_id)->where('bang_id',$offline_id)->orderByDesc('bang_id')->first();
        if(!empty($offline_status)){
            $offline_status = $offline_status->toArray();
            if(time() > $offline_status['offline_end_time']){
                return $this->withError('你已过期，请前去续费');
            }
        }else{
            return $this->withError('你当前没有帮帮，请前去加入');
        }

        $update = [];

        if(!empty($fuwu_banjing)){
            if(!is_numeric($fuwu_banjing)){
                return $this->withError('范围只能是数字');
            }
            $update['fuwu_banjing'] = $fuwu_banjing;
        }
        if(!empty($address)){
            if(mb_strlen($address,"utf-8") > 150){
                return $this->withError('省市区不能超过150字');
            }
            $update['address'] = $address;
        }
        if(!empty($sheng_id)){
            if(!is_numeric($sheng_id)){
                return $this->withError('省市区id或经度纬度必须是数字类型');
            }
            $re = DsCity::query()->where('city_id',$sheng_id)->value('name');
            if(empty($re)){
                return $this->withError('不是省');
            }
            $update['sheng_id'] = $sheng_id;
        }
        if(!empty($shi_id)){
            if(!is_numeric($shi_id)){
                return $this->withError('省市区id或经度纬度必须是数字类型');
            }
            $re = DsCity::query()->where('city_id',$shi_id)->value('name');
            if(empty($re)){
                return $this->withError('不是省');
            }
            $update['shi_id'] = $shi_id;
        }
        if(!empty($qu_id)){
            if(!is_numeric($qu_id)){
                return $this->withError('省市区id或经度纬度必须是数字类型');
            }
            $re = DsCity::query()->where('city_id',$qu_id)->value('name');
            if(empty($re)){
                return $this->withError('不是区县');
            }
            $update['qu_id'] = $qu_id;
        }

        if(!empty($longitude)){
            if(!is_numeric($longitude)){
                return $this->withError('省市区id或经度纬度必须是数字类型');
            }
            $update['longitude'] = $longitude;
        }
        if(!empty($latitude)){
            if(!is_numeric($latitude)){
                return $this->withError('省市区id或经度纬度必须是数字类型');
            }
            $update['latitude'] = $latitude;
        }
        if(!empty($introduction)){
            if(mb_strlen($introduction,"utf-8") > 200){
                return $this->withError('介绍不能超过200字');
            }
            $update['introduction'] = $introduction;
        }
        if(!empty($update)){
            $res_in = DsBang::query()->where('user_id',$user_id)->where('bang_id',$offline_id)->update($update);
            if($res_in){
                return $this->withSuccess('修改成功！');
            }else{
                return $this->withError('当前人数较多，请稍后再试');
            }
        }else{
            return $this->withError('当前什么都没修改');
        }
    }






    protected function string_check_ok(){
        $words = array('我','你','他');
        $content="测一测我是不是违禁词";
        $banned=$this->generateRegularExpression($words);
        //检查违禁词
        $res_banned=$this->check_words($banned,$content);
        return $res_banned;
    }

    /**
     * 数组生成正则表达式
     * @param array $words
     * @return string
     */
    function generateRegularExpression($words)
    {
        $regular = implode('|', array_map('preg_quote', $words));
        return "/$regular/i";
    }
    /**
     * 字符串 生成正则表达式
     * @param array $words
     * @return string
     */
    protected function generateRegularExpressionString($string){
        $str_arr[0]=$string;
        $str_new_arr=  array_map('preg_quote', $str_arr);
        return $str_new_arr[0];
    }
    /**
     * 检查敏感词
     * @param $banned
     * @param $string
     * @return bool|string
     */
    protected function check_words($banned,$string)
    {    $match_banned=array();
        //循环查出所有敏感词

        $new_banned=strtolower($banned);
        $i=0;
        do{
            $matches=null;
            if (!empty($new_banned) && preg_match($new_banned, $string, $matches)) {
                $isempyt=empty($matches[0]);
                if(!$isempyt){
                    $match_banned = array_merge($match_banned, $matches);
                    $matches_str=strtolower($this->generateRegularExpressionString($matches[0]));
                    $new_banned=str_replace("|".$matches_str."|","|",$new_banned);
                    $new_banned=str_replace("/".$matches_str."|","/",$new_banned);
                    $new_banned=str_replace("|".$matches_str."/","/",$new_banned);
                }
            }
            $i++;
            if($i>20){
                $isempyt=true;
                break;
            }
        }while(count($matches)>0 && !$isempyt);

        //查出敏感词
        if($match_banned){
            return $match_banned;
        }
        //没有查出敏感词
        return array();
    }



}
