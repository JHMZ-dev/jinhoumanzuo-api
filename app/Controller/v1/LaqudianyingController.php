<?php

declare(strict_types=1);

namespace App\Controller\v1;

use App\Controller\XiaoController;
use App\Model\Chuli;
use App\Model\DsCinema;
use App\Model\DsCinemaCity;
use App\Model\DsCinemaCityChild;
use App\Model\DsCinemaSuozuo;
use App\Model\DsCinemaUserOrder;
use App\Model\DsCinemaVideo;
use App\Model\DsCinemaPaiqi;
use App\Model\DsCinemaVideoComment;
use App\Model\DsCinemaVideoCommentLike;
use App\Model\DsCity;
use App\Model\DsErrorLog;
use App\Model\DsUser;
use App\Model\DsUserTongzheng;
use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Annotation\Controller;

use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Annotation\Middlewares;
use App\Middleware\SignatureMiddleware;
use App\Middleware\UserMiddleware;
use Hyperf\Utils\Context;

use Hyperf\RateLimit\Annotation\RateLimit;
/**
 * @Middlewares({
 *     @Middleware(SignatureMiddleware::class),
 *     @Middleware(UserMiddleware::class)
 * })
 * @package App\Controller\v1
 * @Controller(prefix="v1/laqudianying")
 */
class LaqudianyingController extends XiaoController
{
    protected $url_dy = 'https://api.nldyp.com';
    protected $apikey = 'ccd78abcd7ec84879ee0598fb813a6c7';
    protected $vendor = '00274';
    //protected $hui_url = 'http://x:9510/v1/callbacklaqu/callbacklaqu_dy';//回调测试
    protected $hui_url = 'https://api.manzchain.com/v1/callbacklaqu/callbacklaqu_dy';//回调

    protected function makeSign($data, $apikey)
    {
        ksort($data);
        $sign = "";
        foreach ($data as $key => $val) {
            if ($key == "sign" || $key == "vendor" || $key == "ts") {
                continue;
            }
            if (is_array($val) || is_object($val)) {
                $sign .= $key;
            } else {
                if (is_null($val)) {
                    $val = '';
                }
                $sign .= $val;
            }
        }
        if ($sign != "") {
            $sign = md5(strtolower($sign));
        }
        $sign .= $data["ts"] . $data["vendor"] . $apikey;
        return strtolower(md5(strtolower($sign)));
    }

    /**生成唯一单号
     * @return string
     */
    protected function get_number(){
        return  date('Ymd').substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8) . rand(100000,9999999);
    }

    /**拉取城市 并加入数据库
     * @RequestMapping(path="get_dy_city",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get_dy_city()
    {
        $url = '/partner/data4/getCityLists';

        $data = [
            'vendor' => $this->vendor,
            'ts' => time(),
        ];
        $params = [
            'vendor' => $data['vendor'],
            'ts' => $data['ts'],
            'sign' => $this->makeSign($data,$this->apikey),
        ];
        $cinema_info = $this->http->post($this->url_dy.$url, ['headers' =>['Content-Type' => 'application/x-www-form-urlencoded'], 'form_params' => $params])->getBody()->getContents();
        if(empty($cinema_info))
        {
            return $this->withError('当前获取人数较多，请稍后再试');
        }else{
            $re = json_decode($cinema_info,true);

            if($re['code'] == 0){
                if(!empty($re['data'])){
                    DsCinemaCity::query()->delete();
                    foreach ($re['data'] as $k => $v){
                        $data = [
                            'cid' => $v['id'],
                            'city_name' => $v['city_name'],
                            'letter' => $v['letter'],
                            'hot' => $v['hot'],
                        ];
                        DsCinemaCity::query()->insert($data);
                    }
                }
                var_dump('更新电影院城市ok');
                return $this->withSuccess('更新电影院城市ok');
            }else{
                return $this->withError($re['msg']);
            }
        }
    }

    /** 拉取地区 并加入数据库
     * @RequestMapping(path="get_dy_diqu",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get_dy_diqu()
    {
        $url = '/partner/data4/getAddressLists';

        $data = [
            'vendor' => $this->vendor,
            'ts' => time(),
        ];
        $params = [
            'vendor' => $data['vendor'],
            'ts' => $data['ts'],
            'sign' => $this->makeSign($data,$this->apikey),
        ];
        $cinema_info = $this->http->post($this->url_dy.$url, ['headers' =>['Content-Type' => 'application/x-www-form-urlencoded'], 'form_params' => $params])->getBody()->getContents();
        if(empty($cinema_info))
        {
            return $this->withError('当前获取人数较多，请稍后再试');
        }else{
            $re = json_decode($cinema_info,true);

            if($re['code'] == 0){
                if(!empty($re['data'])){
                    DsCinemaCityChild::query()->delete();
                    foreach ($re['data'] as $k => $v){
                        $data = [
                            'cid' => $v['id'],
                            'area_name' => $v['area_name'],
                            'city_id' => $v['city_id'],
                        ];
                        DsCinemaCityChild::query()->insert($data);
                    }
                    var_dump('更新电影院地区ok');
                }
                return $this->withSuccess('更新电影院地区ok');
            }else{
                return $this->withError($re['msg']);
            }
        }
    }

    /**
     * 获取所有影院 并加入数据库
     * @RequestMapping(path="get_dy_cinema",methods="post")
     * @return \Psr\Http\Message\ResponseInterface|void
     */
    public function get_dy_cinema()
    {
        $url = '/partner/data4/getCinema';
        $data = [
            'vendor' => $this->vendor,
            'ts' => time(),
        ];
        $params = [
            'vendor' => $data['vendor'],
            'ts' => $data['ts'],
            'sign' => $this->makeSign($data,$this->apikey),
        ];
        $cinema_info = $this->http->post($this->url_dy.$url, ['headers' =>['Content-Type' => 'application/x-www-form-urlencoded'], 'form_params' => $params])->getBody()->getContents();
        if(empty($cinema_info))
        {
            return $this->withError('当前获取人数较多，请稍后再试');
        }else{
            $re = json_decode($cinema_info,true);

            $this->withResponse('获取所有影院ok',$re);

//            if($re['code'] == 0){
//                if(!empty($re['data'])){
//                    DsCinema::query()->delete();
//                    foreach ($re['data'] as $k => $v){
//                        $city_id = DsCity::query()->where('name',$v['city'])->value('city_id');
//                        if(!$city_id){
//                            $city_id = 110000;
//                        }
//                        $data = [
//                            'cid' => $v['id'],
//                            'cinemaId' => $v['cinemaId'],
//                            'cinemaCode' => $v['cinemaCode'],
//                            'cinemaName' => $v['cinemaName'],
//                            'provinceId' => $v['provinceId'],
//                            'cityId' => $v['cityId'],
//                            'countyId' => $v['countyId'],
//                            'address' => $v['address'],
//                            'longitude' => $v['longitude'],
//                            'latitude' => $v['latitude'],
//                            'province' => $v['province'],
//                            'city' => $v['city'],
//                            'county' => $v['county'],
//                            'stopSaleTime' => $v['stopSaleTime'],
//                            'direct' => $v['direct'],
//                            'backTicketConfig' => $v['backTicketConfig'],
//                            'city_id' => $city_id,//本地系统的城市id
//                        ];
//                        DsCinema::query()->insert($data);
//                    }
//                    var_dump('更新电影院ok');
//                }
//
//                return $this->withSuccess('获取所有影院ok');
//            }else{
//                return $this->withError($re['msg']);
//            }
        }
    }

    /**获取所有影片 并加入数据库
     * @RequestMapping(path="get_dy_video",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get_dy_video()
    {
        $url = '/partner/data4/getFilmList';
        $data = [
            'vendor' => $this->vendor,
            'ts' => time(),
        ];
        $params = [
            'vendor' => $data['vendor'],
            'ts' => $data['ts'],
            'sign' => $this->makeSign($data,$this->apikey),
        ];
        $cinema_info = $this->http->post($this->url_dy.$url, ['headers' =>['Content-Type' => 'application/x-www-form-urlencoded'], 'form_params' => $params])->getBody()->getContents();
        if(empty($cinema_info))
        {
            return $this->withError('当前获取人数较多，请稍后再试');
        }else{
            $re = json_decode($cinema_info,true);

            if($re['code'] == 0){
                if(!empty($re['data'])){
                    DsCinemaVideo::query()->delete();
                    foreach ($re['data'] as $k => $v){
                        $comment_num = 0;
                        $like_num = 0;

                        $c_r = DsCinemaVideoComment::query()->where('cid',$v['id'])->count();//影片的评论数
                        $l_r = DsCinemaVideoCommentLike::query()->where('cid',$v['id'])->count();//影片的点赞数

                        if($c_r){
                            $comment_num = $c_r;
                        }
                        if($l_r){
                            $like_num = $l_r;
                        }

                        $data = [
                            'cid' => $v['id'],
                            'filmCode' => $v['filmCode'],
                            'filmName' => $v['filmName'],
                            'version' => $v['version'],
                            'duration' => $v['duration'],
                            'publishDate' => $v['publishDate'],
                            'director' => $v['director'],
                            'castType' => $v['castType'],
                            'cast' => $v['cast'],
                            'introduction' => $v['introduction'],
                            'wantview' => $v['wantview'],
                            'score' => $v['score'],
                            'cover' => $v['cover'],
                            'area' => $v['area'],
                            'type' => $v['type'],
                            'planNum' => $v['planNum'],
                            'preSaleFlag' => $v['preSaleFlag'],
                            'comment_num' => $comment_num,
                            'like_num' => $like_num,

                        ];
                        DsCinemaVideo::query()->insert($data);
                    }
                    var_dump('更新影票ok');
                }

                return $this->withSuccess('获取所有影片ok');
            }else{
                return $this->withError($re['msg']);
            }
        }
    }

    /**拉取影院排期 api 实时
     * @RequestMapping(path="get_dy_paiqi",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get_dy_paiqi($cinemaId)
    {

        $cinemaId = $this->request->post('cinemaId','');

        $url = '/partner/data4/getPlan';

        $data = [
            'vendor' => $this->vendor,
            'ts' => time(),
            'cinemaId' => $cinemaId,
        ];
        $params = [
            'vendor' => $data['vendor'],
            'ts' => $data['ts'],
            'cinemaId' => $data['cinemaId'],
            'sign' => $this->makeSign($data,$this->apikey),
        ];
        $cinema_info = $this->http->post($this->url_dy.$url, ['headers' =>['Content-Type' => 'application/x-www-form-urlencoded'], 'form_params' => $params])->getBody()->getContents();
        if(empty($cinema_info))
        {
            return $this->withError('当前获取人数较多，请稍后再试');
        }else{
            $re = json_decode($cinema_info,true);
            if($re['code'] == 0){
                if(!empty($re['data'])){

                }
                return $this->withSuccess('ok',200,$re['data']);
            }else{
                return $this->withError($re['content']);
            }
        }
    }

    /**更新所有影城的 排期前置操作
     * @RequestMapping(path="get_dy_paiqi_do_before",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get_dy_paiqi_do_before()
    {
        DsCinema::query()->update(['type'=>0]);
        return $this->withSuccess('将所有影院更新字段重置为0未更新');
    }

    /**更新所有影城的 排期 每隔时间执行就行
     * @RequestMapping(path="get_dy_paiqi_do",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get_dy_paiqi_do()
    {
//        DsCinema::query()->update(['type'=>0]);
//        $yingcheng_list = DsCinema::query()
//            ->where('type',0)
//            ->inRandomOrder()
//            ->select('cid','city')->get()->toArray();
//        $url = $this->url_dy . '/partner/data4/getPlan';
//        //先检查是否正在执行影院更新 如果正在更新就不清理
//        $DsCinema = DsCinema::query()->where('type',1)->value('cid');
//        if(empty($DsCinema)){
//            DsCinemaPaiqi::query()->delete();
//        }



        $url = $this->url_dy . '/partner/data4/getPlan';
        DsCinemaPaiqi::query()->delete();
        $yingcheng_list = DsCinema::query()
            ->inRandomOrder()
            ->select('cid','city')->get()->toArray();



        $num1 = 0;
        $num2 = 0;
        if($yingcheng_list){
            foreach ($yingcheng_list as $k => $v){

                $num1 += 1;
                //查询单个影城的排期并加入数据库
                $data = [
                    'vendor' => $this->vendor,
                    'ts' => time(),
                    'cinemaId' => $v['cid'],
                ];
                $params = [
                    'vendor' => $data['vendor'],
                    'ts' => $data['ts'],
                    'cinemaId' => $data['cinemaId'],
                    'sign' => $this->makeSign($data,$this->apikey),
                ];

                $cinema_info = $this->http->post($url, ['headers' =>['Content-Type' => 'application/x-www-form-urlencoded'], 'form_params' => $params])->getBody()->getContents();
                if($cinema_info)
                {
                    $re = json_decode($cinema_info,true);
                    if($re['code'] == 0){
                        if(!empty($re['data'])){
                            if(is_array($re['data'])){
                                $city_id = DsCity::query()->where('name',$v['city'])->value('city_id');
                                if(!$city_id){
                                    $city_id = 110000;
                                }
                                foreach ($re['data'] as $vv){
                                    $num2 += 1;
                                    $data2 = [
                                        'featureAppNo' => $vv['featureAppNo'],
                                        'cinemaCode' => $vv['cinemaCode'],
                                        'sourceFilmNo' => $vv['sourceFilmNo'],
                                        'filmNo' => $vv['filmNo'],
                                        'filmName' => $vv['filmName'],
                                        'hallNo' => $vv['hallNo'],
                                        'hallName' => $vv['hallName'],
                                        'startTime' => $vv['startTime'],
                                        'copyType' => $vv['copyType'],
                                        'copyLanguage' => $vv['copyLanguage'],
                                        'totalTime' => $vv['totalTime'],
                                        'listingPrice' => $vv['listingPrice'],
                                        'ticketPrice' => $vv['ticketPrice'],
                                        'serviceAddFee' => $vv['serviceAddFee'],
                                        'lowestPrice' => $vv['lowestPrice'],
                                        'thresholds' => $vv['thresholds'],
                                        'areas' => $vv['areas'],
                                        'marketPrice' => $vv['marketPrice'],
                                        'city_id' => $city_id,
                                        'update_time' => date('Y-m-d H:i:s',time()),
                                    ];
                                    DsCinemaPaiqi::query()->insert($data2);
                                }
                            }
                        }
                    }
                    //DsCinema::query()->where('cid',$v['cid'])->update(['type'=>1]);
                }
            }
        }
        var_dump('本次更新成功影院个：'.$num1);
        var_dump('本次更新成功影院排期个：'.$num2);
    }

    /**获取排期详细
     * @RequestMapping(path="get_dy_paiqi_data",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get_dy_paiqi_data()
    {
        $cinemaId = $this->request->post('cinemaId','');
        $featureAppNo = $this->request->post('featureAppNo','');

        if(empty($cinemaId) || empty($featureAppNo)){
            return $this->withError('影城id或排期编码不能为空！');
        }

        $url = '/partner/data4/getPlanDetail';

        $data = [
            'vendor' => $this->vendor,
            'ts' => time(),
            'cinemaId' => $cinemaId,
            'featureAppNo' => $featureAppNo,
        ];
        $params = [
            'vendor' => $data['vendor'],
            'ts' => $data['ts'],
            'cinemaId' => $data['cinemaId'],
            'featureAppNo' => $data['featureAppNo'],
            'sign' => $this->makeSign($data,$this->apikey),
        ];
        $cinema_info = $this->http->post($this->url_dy.$url, ['headers' =>['Content-Type' => 'application/x-www-form-urlencoded'], 'form_params' => $params])->getBody()->getContents();
        if(empty($cinema_info))
        {
            return $this->withError('当前获取人数较多，请稍后再试');
        }else{
            $re = json_decode($cinema_info,true);
            if($re['code'] == 0){
                if(!empty($re['data'])){

                }
                return $this->withSuccess($re['content'],200,$re['data']);
            }else{
                return $this->withError($re['content']);
            }
        }
    }

    /**获取实时座位图
     * @RequestMapping(path="dy_get_zuowei",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function dy_get_zuowei(){
        $cinemaId = $this->request->post('cinemaId','');//影院cinemaId
        $featureAppNo = $this->request->post('featureAppNo','');//排期编码

        if(empty($cinemaId) || empty($featureAppNo)){
            return $this->withError('影院cinemaId和排期编码不能为空');
        }
        $url = '/partner/data4/getPlanSeat';

        $data = [
            'vendor' => $this->vendor,
            'ts' => time(),
            'cinemaId' => $cinemaId,
            'featureAppNo' => $featureAppNo,
        ];
        $params = [
            'vendor' => $data['vendor'],
            'ts' => $data['ts'],
            'cinemaId' => $data['cinemaId'],
            'featureAppNo' => $data['featureAppNo'],
            'sign' => $this->makeSign($data,$this->apikey),
        ];
        $cinema_info = $this->http->post($this->url_dy.$url, ['headers' =>['Content-Type' => 'application/x-www-form-urlencoded'], 'form_params' => $params])->getBody()->getContents();
        if(empty($cinema_info))
        {
            return $this->withError('当前获取人数较多，请稍后再试');
        }else{
            $re = json_decode($cinema_info,true);
            if($re['code'] == 0){
                if(!empty($re['data'])){

                }
                return $this->withSuccess('ok',200,$re['data']);
            }else{
                return $this->withError($re['content']);
            }
        }
    }


    /**
     *锁座 测试
     * @RequestMapping(path="dy_suozuo",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function dy_suozuo(){

//        $cinemaId = $this->request->post('cinemaId','');//影院cinemaId
//        $featureAppNo = $this->request->post('featureAppNo','');//排期编码
//
//        $orderNo = $this->get_number();
//        $user_id = Context::get('user_id');
//        $userInfo = Context::get('userInfo');
//        $mobile = $userInfo['mobile'];
//        if(empty($userInfo)){
//            return $this->withError('请登录');
//        }
//
//        $areaId = $this->request->post('areaId','');//0000000000000001,0000000000000002 座位区间ID，多个参数用英 文半角逗号拼接（新增）
//        $seatNo = $this->request->post('seatNo','');//0000000000000001-5-19,0000000000000002-5-17 座位编码，多个参数用英 文半角逗号拼接
//        $seatPieceName = $this->request->post('seatPieceName','');//5排19座,5排17座 座位名称，多个参数用英 文半角逗号拼接（新增）
//        $ticketPrice = $this->request->post('ticketPrice','');//50.00,50.00 票价，多个参数用英文半 角逗号拼接，顺序要与座 位编码对应
//        $serviceAddFee = $this->request->post('serviceAddFee',0);//5.00,5.00 服务费，多个参数用英文 半角逗号拼接，顺序要与 座位编码对应，提交的金 额范围必须在排期接口中 返回的服务费上下限值之间
//
//        if(empty($cinemaId) || empty($featureAppNo) || empty($areaId) || empty($seatNo) || empty($seatPieceName) || empty($ticketPrice)){
//            return $this->withError('影院cinemaId/排期编码/座位区间ID/座位编码/座位名称/票价/服务费/ 不能空');
//        }
//
//        $yc_data = DsCinemaPaiqi::query()->where('featureAppNo',$featureAppNo)->first();
//        if(empty($yc_data)){
//            return $this->withError('没有该排期');
//        }
//
//        $url = '/partner/data4/lockSeat';
//
//        $data = [
//            'vendor' => $this->vendor,
//            'ts' => time(),
//            'orderNo' => $orderNo,
//            'cinemaId' => $cinemaId,
//            'featureAppNo' => $featureAppNo,
//            'mobile' => $mobile,
//            'areaId' => $areaId,
//            'seatNo' => $seatNo,
//            'seatPieceName' => $seatPieceName,
//            'ticketPrice' => $ticketPrice,
//            'serviceAddFee' => $serviceAddFee,
//        ];
//        $params = [
//            'vendor' => $data['vendor'],
//            'ts' => $data['ts'],
//            'sign' => $this->makeSign($data,$this->apikey),
//            'orderNo' => $data['orderNo'],
//            'cinemaId' => $data['cinemaId'],
//            'featureAppNo' => $data['featureAppNo'],
//            'mobile' => $data['mobile'],
//            'areaId' => $data['areaId'],
//            'seatNo' => $data['seatNo'],
//            'seatPieceName' => $data['seatPieceName'],
//            'ticketPrice' => $data['ticketPrice'],
//            'serviceAddFee' => $data['serviceAddFee'],
//        ];
//        var_dump($params);
//        $cinema_info = $this->http->post($this->url_dy.$url, ['headers' =>['Content-Type' => 'application/x-www-form-urlencoded'], 'form_params' => $params])->getBody()->getContents();
//        if(empty($cinema_info))
//        {
//            return $this->withError('当前获取人数较多，请稍后再试');
//        }else{
//            $re = json_decode($cinema_info,true);
//            var_dump($re);
//            if($re['status'] == 0){
//                if(!empty($re['data'])){
//
//                }
//
//                return $this->withSuccess($re['content'],200,$re['data']);
//            }else{
//                return $this->withError($re['content']);
//            }
//        }
    }

    /**
     * 下单测试
     * @RequestMapping(path="dy_order_add",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function dy_order_add(){
//        $orderNumber = $this->get_number();
//        $user_id =  Context::get('user_id');
//        $userInfo = Context::get('userInfo');
//        $mobile =  $userInfo['mobile'];
//        if(empty($userInfo)){
//            return $this->withError('请登录..');
//        }
//
//        $yc_id = $this->request->post('yc_id','');//影城id
//        $pq_id = $this->request->post('pq_id','');//排期编码
//        $num = $this->request->post('num','');//购票数量
//
//        $seatName = $this->request->post('seatName','');//座位名称，多个座位号使用英文版符合“|”分割，（注：不要复制文档上的“|”），例：1排2座|1排3座
//        $cityName = $this->request->post('cityName','');//城市名称,到区镇。例：北京朝阳区
//        $is_tiaowei = 2;//是否接收调位,1接受，2不接受
//        $is_love = $this->request->post('is_love',0);//是否情侣座,1是，0否
//
//        if(empty($seatName)){
//            return $this->withError('请选择座位名称');
//        }
//        if($num < 1){
//            return $this->withError('请选择数量');
//        }
//        if(empty($yc_id) || empty($pq_id)){
//            return $this->withError('影城id/排期编码 不能空');
//        }
//
//        $yc_data = DsCinema::query()->where('cid',$yc_id)->first();
//        if(empty($yc_data)){
//            return $this->withError('没有该影城了');
//        }
//        $pq_data = DsCinemaPaiqi::query()->where('featureAppNo',$pq_id)->first();
//        if(empty($pq_data)){
//            return $this->withError('没有该排期了');
//        }
//        //查影片
//        $yp_data = DsCinemaVideo::query()->where('cid',$pq_data['filmNo'])->first();
//        if(empty($yp_data)){
//            return $this->withError('没有该影片了');
//        }
//        if(empty($cityName)){
//            return $this->withError('地址不能空');
//        }
//
//        $buyTime =  time();
//        $cinemaName = $yc_data['cinemaName'];
//        $movieName = $pq_data['filmName'];
//        $cinemaAddress = $yc_data['address'];
//        $poster = $yp_data['cover'];
//        $language = $pq_data['copyLanguage'];
//        $plan_type = $pq_data['copyType'];
//        $startTime = $pq_data['startTime'];
//        $ticketNum = $num;
//        $hallName = $pq_data['hallName'];
//        $hallType = $pq_data['copyType'];
//        $yuanjia = ($pq_data['ticketPrice']+$pq_data['ticketPrice']) * $num;
//
//        $url = '/partner/data4/GetOrderData';
//
//        $data = [
//            'vendor' => $this->vendor,
//            'ts' => time(),
//            'orderNumber' => $orderNumber,
//            'mobile' => $mobile,
//            'buyTime' => $buyTime,
//            'movieName' => $movieName,
//            'cinemaName' => $cinemaName,
//            'cinemaAddress' => $cinemaAddress,
//            'poster' => $poster,
//            'language' => $language,
//            'plan_type' => $plan_type,
//            'startTime' => $startTime,
//            'ticketNum' => $ticketNum,
//            'hallName' => $hallName,
//            'hallType' => $hallType,
//            'seatName' => $seatName,
//            'cityName' => $cityName,
//            'is_tiaowei' => $is_tiaowei,
//            'is_love' => $is_love,
//            'yuanjia' => $yuanjia,
//            'curl' => $this->c_url,
//        ];
//
//        $params = [
//            'vendor' => $data['vendor'],
//            'ts' => $data['ts'],
//            'sign' => $this->makeSign($data,$this->apikey),
//            'orderNumber' => $data['orderNumber'],
//            'mobile' => $data['mobile'],
//            'buyTime' => $data['buyTime'],
//            'movieName' => $data['movieName'],
//            'cinemaName' => $data['cinemaName'],
//            'cinemaAddress' => $data['cinemaAddress'],
//            'poster' => $data['poster'],
//            'language' => $data['language'],
//            'plan_type' => $data['plan_type'],
//            'startTime' => $data['startTime'],
//            'ticketNum' => $data['ticketNum'],
//            'hallName' => $data['hallName'],
//            'hallType' => $data['hallType'],
//            'seatName' => $data['seatName'],
//            'cityName' => $data['cityName'],
//            'is_tiaowei' => $data['is_tiaowei'],
//            'is_love' => $data['is_love'],
//            'yuanjia' => $data['yuanjia'],
//            'curl' => $data['curl'],
//        ];
//
//        $cinema_info = $this->http->post($this->url_dy.$url, ['headers' =>['Content-Type' => 'application/x-www-form-urlencoded'], 'form_params' => $params])->getBody()->getContents();
//        if(empty($cinema_info))
//        {
//            return $this->withError('当前获取人数较多，请稍后再试');
//        }else{
//            $re = json_decode($cinema_info,true);
//var_dump($re);
//            if($re['status'] == 0){
//                if(!empty($re['data'])){
//                    $params['user_id'] = $user_id;
//                    DsCinemaUserOrder::query()->insert($params);
//                }
//
//                return $this->withSuccess('ok',200,$re['data']);
//            }else{
//                return $this->withError($re['content']);
//            }
//        }
    }


    /**
     *正式接口==========================================================================
     */
    /**城市列表
     * @RequestMapping(path="get_dy_api_city",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get_dy_api_city()
    {
        $dy_cyty = $this->redis1->get('dy_cyty_list');
        if($dy_cyty){
            return $this->withSuccess('ok',200,json_decode($dy_cyty,true));
        }
        $dy_cyty_list = DsCinemaCity::query()->select()->get()->toArray();
        if($dy_cyty_list){
            $this->redis1->set('dy_cyty_list',json_encode($dy_cyty_list),86400);
            return $this->withSuccess('ok',200,$dy_cyty_list);
        }else{
            return $this->withError('当前人数较多，请稍后再试');
        }
    }

    /**
     * 获取热映电影 列表原
     * @RequestMapping(path="get_dy_api_re",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get_dy_api_re()
    {
        $page = $this->request->post('page',1);
        $key_word = $this->request->post('key_word','');

        $user_id = Context::get('user_id');

        $city_id = $this->redis2->get('cityCode_'.$user_id);
        if(empty($city_id)){
            return $this->withError('请进入首页刷新定位后再访问');
        }

        if($page <= 0)
        {
            $page = 1;
        }
        $data['more']           = '0';
        $data['data']         = [];

        if(!empty($key_word)){
            $res = DsCinemaPaiqi::query()->leftJoin('ds_cinema','ds_cinema.cinemaCode','=','ds_cinema_paiqi.cinemaCode')
                ->leftJoin('ds_cinema_video','ds_cinema_video.cid','=','ds_cinema_paiqi.filmNo')
                ->where('ds_cinema_video.filmName','like','%'.$key_word.'%')
                ->where('ds_cinema_paiqi.city_id',$city_id)
                ->where('ds_cinema_paiqi.startTime','>',time()+60*80)
                ->groupBy('ds_cinema_video.filmName')
                ->select('ds_cinema_video.filmName','ds_cinema_video.cover','ds_cinema_video.cast','ds_cinema_video.type','ds_cinema_video.cid','ds_cinema_video.comment_num','ds_cinema_video.like_num')
                ->orderByDesc('ds_cinema_video.like_num')
                ->forPage($page,10)->get()->toArray();
        }else{
            $res = DsCinemaPaiqi::query()->leftJoin('ds_cinema','ds_cinema.cinemaCode','=','ds_cinema_paiqi.cinemaCode')
                ->leftJoin('ds_cinema_video','ds_cinema_video.cid','=','ds_cinema_paiqi.filmNo')
                ->where('ds_cinema_paiqi.city_id',$city_id)
                ->where('ds_cinema_paiqi.startTime','>',time()+60*80)
                ->groupBy('ds_cinema_video.filmName')
                ->select('ds_cinema_video.filmName','ds_cinema_video.cover','ds_cinema_video.cast','ds_cinema_video.type','ds_cinema_video.cid','ds_cinema_video.comment_num','ds_cinema_video.like_num')
                ->orderByDesc('ds_cinema_video.like_num')
                ->forPage($page,10)->get()->toArray();
        }

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
                $datas[$k]['name'] = '影片';
                $datas[$k]['cover'] = 'https://jinhoumanzuo.oss-cn-hangzhou.aliyuncs.com/logo.png';
                $datas[$k]['cast'] = '合作影片';
                $datas[$k]['type'] = '剧情';
                $datas[$k]['comment_num'] = 2;
                $datas[$k]['like'] = 2;
                $datas[$k]['filmCode'] = '0';

                if($v['filmName']){
                    $datas[$k]['name'] = $v['filmName'];
                }
                if($v['cover']){
                    $datas[$k]['cover'] = $v['cover'];
                }
                if($v['cast']){
                    $datas[$k]['cast'] = $v['cast'];
                }
                if($v['type']){
                    $datas[$k]['type'] = $v['type'];
                }
                if($v['cid']){
                    $datas[$k]['filmCode'] = $v['cid'];
                }

                $datas[$k]['comment_num'] = $v['comment_num'];
                $datas[$k]['like'] = $v['like_num'];
            }
            $data['data']           = $datas;
        }
        return $this->withSuccess('获取成功',200,$data);
    }

    /**
     * 获取影片详情
     * @RequestMapping(path="get_dy_api_cinema_video_data",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get_dy_api_cinema_video_data(){
        $filmCode = $this->request->post('filmCode','');

        if(empty($filmCode)){
            return $this->withError('请选择影片');
        }

        $user_id = Context::get('user_id');
        if(empty($user_id)){
            return $this->withError('请登录');
        }

        $city_id = $this->redis2->get('cityCode_'.$user_id);
        if(empty($city_id)){
            return $this->withError('请进入首页刷新定位后再访问');
        }

        $data = DsCinemaVideo::query()->where('cid',$filmCode)->first();
        if($data['cast']){
            $castarr =  explode(',',$data['cast']);
            $castarr2 = [];
            if(is_array($castarr)){
                foreach ($castarr as $k => $v){
                    $castarr2[$k]['name'] = $v;
                    $castarr2[$k]['img'] = 'https://jinhoumanzuo.oss-cn-hangzhou.aliyuncs.com/logo.png';
                }
                $data['cast'] = $castarr2;
            }else{
                $data['cast'] = [['name' => '无','img'=> 'https://jinhoumanzuo.oss-cn-hangzhou.aliyuncs.com/logo.png']];
            }
        }else{
            $data['cast'] = [['name' => '无','img'=> 'https://jinhoumanzuo.oss-cn-hangzhou.aliyuncs.com/logo.png']];
        }

        $data2 = [
          'name' => $data['filmName'],
          'cover' => $data['cover'],
          'cast' => '主演：' . $data['director']?$data['director']:'暂无',
          'type' => $data['type'],
          'publishDate' =>  date('Y-m-d H:i',(int)$data['publishDate']),
          'duration' =>  $data['duration'].'分钟',
          'class' =>  ['IMAX 2D','DOlby Cinema'],
          'introduction' =>  $data['introduction'],
          'cast2' =>  $data['cast'],
          'filmCode' =>  $data['cid'],
          'yp_cid' =>  $data['cid'],
        ];

        //查询评论
        $comment_list_d = [
            'num' => 0,
            'list' => [],
        ];
        $comment_list = DsCinemaVideoComment::query()->where('cid',$filmCode)->orderByDesc('comment_like')->limit(50)->select()->get()->toArray();
        if(!empty($comment_list)){
            foreach ($comment_list as $k => $v){
                $u = DsUser::query()->where('user_id',$v['user_id'])->select('avatar','nickname')->first();
                $comment_list2[$k]['img'] = $u['avatar'];
                $comment_list2[$k]['nickname'] = $u['nickname']?Chuli::str_hide($u['nickname'],2,4):'****';
                $comment_list2[$k]['content'] = $v['comment_content'];
                $comment_list2[$k]['time'] = $this->replaceTime($v['comment_time']);
                $zan = DsCinemaVideoCommentLike::query()->where('comment_like_id',$v['comment_id'])->count();
                $comment_list2[$k]['zan'] = $v['comment_like'];
                $comment_list2[$k]['comment_id'] = $v['comment_id'];
                $comment_list2[$k]['is_zan'] = 0;
                $is_zan_data = DsCinemaVideoCommentLike::query()->where('user_id',$user_id)->where('comment_id',$v['comment_id'])->value('comment_like_id');
                if($is_zan_data){
                    $comment_list2[$k]['is_zan'] = 1;
                }
            }
            $data2['comment_list']['num'] = count($comment_list);
            $data2['comment_list']['list'] = $comment_list2;
        }else{
            $data2['comment_list'] = $comment_list_d;
        }

        return $this->withSuccess('获取成功',200,$data2);
    }

    /**
     * 购买初始化-影城列表
     * @RequestMapping(path="dy_buy_csh_yc",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function dy_buy_csh_yc(){

        $filmCode = $this->request->post('filmCode','');
        $page = $this->request->post('page',1);

        $user_id = Context::get('user_id');
        if(empty($user_id)){
            return $this->withError('请登录');
        }

        $city_id = $this->redis2->get('cityCode_'.$user_id);
        if(empty($city_id)){
            return $this->withError('请进入首页刷新定位后再访问');
        }

        if(empty($filmCode)){
            return $this->withError('请选择影片');
        }

        $data_v = DsCinemaVideo::query()->where('cid',$filmCode)->first();
        $datas_top['name'] = $data_v['filmName'];
        $datas_top['cover'] = $data_v['cover'];
        $datas_top['cast'] = '主演：' . $data_v['director']?$data_v['director']:'暂无';
        $datas_top['type'] = $data_v['type'];
        $datas_top['comment_num'] = 999;
        $datas_top['like'] = 999;
        $datas_top['filmCode'] = $data_v['cid'];
        $datas_top['class'] = ['IMAX 2D','DOlby Cinema'];
        $datas_top['duration'] = $data_v['duration'].'分钟';

        $datas_top['publishDate'] = date('Y-m-d H:i:s',(int)$data_v['publishDate']);

        $data['data_top'] = $datas_top;

        if($page <= 0)
        {
            $page = 1;
        }
        $data['more']           = '0';
        $data['data']         = [];

//        $res = DsCinema::query()
//            ->where('city_id',$city_id)
//            ->forPage($page,30)->get()->toArray();

        //用户经度纬度
        $latitude = $this->redis2->get('latitude_'.$user_id);
        $longitude = $this->redis2->get('longitude_'.$user_id);
        if(empty($latitude) || empty($longitude)){
            return $this->withError('请刷新定位或重新进入APP');
        }

        //不按距离
//        $res = DsCinemaPaiqi::query()->leftJoin('ds_cinema','ds_cinema.cinemaCode','=','ds_cinema_paiqi.cinemaCode')
//            ->leftJoin('ds_cinema_video','ds_cinema_video.cid','=','ds_cinema_paiqi.filmNo')
//            ->where('ds_cinema_paiqi.filmNo',$filmCode)
//            ->where('ds_cinema.city_id',$city_id)
//            ->where('ds_cinema_paiqi.startTime','>',time())
//            ->groupBy('ds_cinema.cinemaName')
//            ->select('ds_cinema.cinemaName','ds_cinema.address','ds_cinema.longitude','ds_cinema.latitude','ds_cinema.cinemaCode')
//            ->inRandomOrder()
//            ->forPage($page,10)->get()->toArray();

        //按距离 根据位置 before
        if($page <= 0)
        {
            $p1 = 0 *10;
        }else{
            $p1 = ($page-1) *10;
        }

        $time = time()+(60*80);
        //$sql_lod = "SELECT `cinema_id`,`cid`,`cinemaId`,`cinemaCode`,`cinemaName`,`cityId`,`address`,`stopSaleTime`,`longitude`,`latitude`,ROUND(6378.138*2*ASIN(SQRT(POW(SIN((".$latitude."*PI()/180-latitude*PI()/180)/2),2)+COS(".$latitude."*PI()/180)*COS(latitude*PI()/180)*POW(SIN((".$longitude."*PI()/180-longitude*PI()/180)/2),2)))*1000) AS juli FROM ds_cinema where `cinemaCode` >0 and `cinemaCode` in (SELECT cinemaCode FROM ds_cinema_paiqi where city_id = ".$city_id." and filmNo=".$filmCode_bianma." and startTime > " . $time . ") ORDER BY juli ASC limit $p1,10";
        $sql_lod = "SELECT `cinema_id`,`cid`,`cinemaId`,`cinemaCode`,`cinemaName`,`cityId`,`address`,`stopSaleTime`,`longitude`,`latitude`,ROUND(6378.138*2*ASIN(SQRT(POW(SIN((".$latitude."*PI()/180-latitude*PI()/180)/2),2)+COS(".$latitude."*PI()/180)*COS(latitude*PI()/180)*POW(SIN((".$longitude."*PI()/180-longitude*PI()/180)/2),2)))*1000) AS juli FROM ds_cinema ORDER BY juli ASC limit $p1,10";
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
                $datas[$k]['c_name'] = $v['cinemaName'];
                $datas[$k]['c_changci'] = '展中';
                $datas[$k]['c_address'] = $v['address'];

                $datas[$k]['longitude'] = $v['longitude'];
                $datas[$k]['latitude'] = $v['latitude'];
                $datas[$k]['cinemaCode'] = $v['cinemaCode'];
                $datas[$k]['juli'] = $v['juli'];
            }
            $data['data']           = $datas;
        }
        return $this->withSuccess('获取成功',200,$data);
    }

    /**
     * 购买初始化-排期列表-获取排期顶部数据
     * @RequestMapping(path="dy_buy_csh_pq_top",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function dy_buy_csh_pq_top(){

        $filmCode = $this->request->post('filmCode','');
        $cinemaCode = $this->request->post('cinemaCode','');

        if(empty($filmCode) || empty($cinemaCode)){
            return $this->withError('请选择/影片/影城');
        }

        $user_id = Context::get('user_id');
        $city_id = $this->redis2->get('cityCode_'.$user_id);
        if(empty($city_id)){
            return $this->withError('请进入首页刷新定位后再访问');
        }

        $data_v = DsCinemaVideo::query()->where('cid',$filmCode)->first();
        $datas_top[0]['name'] = $data_v['filmName'];
        $datas_top[0]['type'] = $data_v['type'];
        $datas_top[0]['cover'] = $data_v['cover'];
        $datas_top[0]['class'] = ['IMAX 2D','DOlby Cinema'];
        $datas_top[0]['duration'] = $data_v['duration'].'分钟';
        $datas_top[0]['filmCode'] = $data_v['cid'];

        $xg_list = DsCinemaPaiqi::query()->where('city_id',$city_id)->where('cinemaCode',$cinemaCode)
            ->where('startTime','>',time()+60*80)
            ->groupBy('filmName')
            ->get()->toArray();
        if($xg_list){
            foreach ($xg_list as $kk => $vv){

                    $data_video = DsCinemaVideo::query()->where('cid',$vv['filmNo'])->first();
                    $datas_top[$kk+1]['name'] = $data_video['filmName'];
                    $datas_top[$kk+1]['type'] = $data_video['type'];
                    $datas_top[$kk+1]['cover'] = $data_video['cover'];
                    $datas_top[$kk+1]['class'] =  ['IMAX 2D','DOlby Cinema'];
                    $datas_top[$kk+1]['duration'] = $data_v['duration'].'分钟';
                    $datas_top[$kk+1]['filmCode'] = $vv['filmNo'];


            }
        }

        return $this->withSuccess('ok',200,$datas_top);
    }

    /**
     * 购买初始化-排期列表
     * @RequestMapping(path="dy_buy_csh_pq",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function dy_buy_csh_pq(){
        $tz_beilv = $this->tz_beilv();

        $filmCode = $this->request->post('filmCode','');
        $cinemaCode = $this->request->post('cinemaCode','');
        $startTime1 = $this->request->post('startTime1',0);

        $user_id = Context::get('user_id');
        if(empty($user_id)){
            return $this->withError('请登录');
        }

        $city_id = $this->redis2->get('cityCode_'.$user_id);
        if(empty($city_id)){
            return $this->withError('请进入首页刷新定位后再访问');
        }

        $data = [];

        //$city_id = $this->redis2->get('cityCode_'.$user_id);
        if(empty($city_id)){
            return $this->withError('请前往首页获取位置信息');
        }

        if(empty($cinemaCode)  || empty($filmCode)){
            return $this->withError('cinemaCode/filmCode必选');
        }

        switch ($startTime1){
            case '0':
                $mtlc = time()+60*80;
                $mtlc_js = strtotime(date('Y-m-d 23:59:59',time()));
                break;
            case '1':
                $mtlc = strtotime(date('Y-m-d',strtotime('+1 day'))." 00:00:00");
                $mtlc_js = strtotime(date("Y-m-d",strtotime("+1 day"))." 23:59:59");
                break;
            case '2':
                $mtlc = strtotime(date('Y-m-d',strtotime('+2 day'))." 00:00:00");
                $mtlc_js = strtotime(date("Y-m-d",strtotime("+2 day"))." 23:59:59");
                break;
            case '3':
                $mtlc = strtotime(date('Y-m-d',strtotime('+3 day'))." 00:00:00");
                $mtlc_js = strtotime(date("Y-m-d",strtotime("+3 day"))." 23:59:59");
                break;
            case '4':
                $mtlc = strtotime(date('Y-m-d',strtotime('+4 day'))." 00:00:00");
                $mtlc_js = strtotime(date("Y-m-d",strtotime("+4 day"))." 23:59:59");
                break;
            default:
                return $this->withError('没有该类型');
                break;
        }

        $res = DsCinemaPaiqi::query()
            ->where('city_id',$city_id)
            ->where('filmNo',$filmCode)
            ->where('cinemaCode',$cinemaCode)
            ->whereBetween('startTime',[$mtlc,$mtlc_js])
            ->orderBy('startTime')
            ->get()->toArray();

        if(!empty($res))
        {
            //整理数据
            $datas = [];
            foreach ($res as $k => $v)
            {
                $data_v_yc = DsCinema::query()->where('cinemaCode',$v['cinemaCode'])->first();
                $datas[$k]['c_name'] = $data_v_yc['cinemaName'];
                $datas[$k]['c_address'] = $data_v_yc['address'];
                $datas[$k]['c_longitude'] = $data_v_yc['longitude'];
                $datas[$k]['c_latitude'] = $data_v_yc['latitude'];

                $datas[$k]['p_hallName'] = $v['hallName'];
                $datas[$k]['p_copyLanguage'] = $v['copyLanguage'];

                $data_v = DsCinemaVideo::query()->where('cid',$filmCode)->first();
                $datas[$k]['name'] = $data_v['filmName'].$v['hallName'];
                $datas[$k]['cover'] = $data_v['cover'];
                $datas[$k]['type'] = $data_v['type'];
                $datas[$k]['duration'] = $data_v['duration'].'分钟';
                $datas[$k]['class'] =  ['IMAX 2D','DOlby Cinema'];
                $datas[$k]['duration2'] = $data_v['duration'];
                $datas[$k]['publishDate'] = $data_v['publishDate'];
                $datas[$k]['publishDate2'] = date('Y-m-d H:i',(int)$data_v['publishDate']);
                $datas[$k]['filmCode'] = $data_v['cid'];
                $datas[$k]['filmCode_bianma'] = $data_v['filmCode'];
                $datas[$k]['listingPrice'] = $v['listingPrice'];

                $datas[$k]['price'] = round($v['ticketPrice']/$tz_beilv,$this->xiaoshudian);//通证单价
                $datas[$k]['ticketPrice'] = $v['ticketPrice'];//实际单价

                $datas[$k]['cinemaCode'] = $data_v_yc['cinemaCode'];
                $datas[$k]['featureAppNo'] = $v['featureAppNo'];
                $datas[$k]['startTime'] = date('Y-m-d H:i',(int)$v['startTime']);
                $datas[$k]['startTime2'] = $v['startTime'];

                $datas[$k]['start_time'] = date('H:i',(int)$v['startTime']);
                $datas[$k]['end_time'] =   date('H:i',(int)($v['startTime']+$data_v['duration']*60));
                $datas[$k]['copyType'] = $v['copyType'];
                $datas[$k]['filmName'] = $v['filmName'];
                $datas[$k]['areas'] = $v['areas'];
                $datas[$k]['serviceAddFee'] = $v['serviceAddFee'];
                $datas[$k]['lowestPrice'] = $v['lowestPrice'];
                $datas[$k]['thresholds'] = $v['thresholds'];

            }
            $data           = $datas;
        }
        return $this->withSuccess('获取成功',200,$data);
    }

    /**
     * 获取实时座位图
     * @RequestMapping(path="dy_api_get_zuowei",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function dy_api_get_zuowei(){

        $tz_beilv = $this->tz_beilv();

        $cinemaId = $this->request->post('cinemaId','');//影院cinemaId
        $featureAppNo = $this->request->post('featureAppNo','');//排期编码

        if(empty($cinemaId) || empty($featureAppNo)){
            return $this->withError('影院cinemaId和排期编码不能为空');
        }

        //查询排期
        $paiqi = DsCinemaPaiqi::query()->where('featureAppNo',$featureAppNo)->first();
        if(empty($paiqi)){
            return $this->withError('影院下该排期已经下场');
        }
        $paiqi = $paiqi->toArray();

        $zuowei_img = $this->redis2->get('c_paiqi_'.$featureAppNo);
        if(!empty($zuowei_img)){
            return $this->withSuccess('ok',200,json_decode($zuowei_img,true));
        }

        $user_id = Context::get('user_id');
        if(empty($user_id)){
            return $this->withError('请登录');
        }

        $url = '/partner/data4/getPlanSeat';

        $data = [
            'vendor' => $this->vendor,
            'ts' => time(),
            'cinemaId' => $cinemaId,
            'featureAppNo' => $featureAppNo,
        ];
        $params = [
            'vendor' => $data['vendor'],
            'ts' => $data['ts'],
            'cinemaId' => $data['cinemaId'],
            'featureAppNo' => $data['featureAppNo'],
            'sign' => $this->makeSign($data,$this->apikey),
        ];
        $cinema_info = $this->http->post($this->url_dy.$url, ['headers' =>['Content-Type' => 'application/x-www-form-urlencoded'], 'form_params' => $params])->getBody()->getContents();
        if(empty($cinema_info))
        {
            return $this->withError('当前获取人数较多，请稍后再试');
        }else{
            $re = json_decode($cinema_info,true);
            if($re['code'] == 0){
                if($re['data']){
                    if(is_array($re['data'])){
                        $paiqi_areas = json_decode($paiqi['areas'],true);
                        //是否区间价格 赋值
                        foreach ($re['data'] as $k => $v){
                            $re['data'][$k]['ticketPrice'] = $paiqi['ticketPrice'];
                            $re['data'][$k]['tongzheng'] = round($paiqi['ticketPrice']/$tz_beilv,$this->xiaoshudian);
                            if(!empty($v['areaId']) && $v['areaId'] > 0){

                                if(!empty($paiqi_areas) && is_array($paiqi_areas)){
                                    foreach ($paiqi_areas as $kk => $vv){
                                        if($v['areaId'] == $vv['areaId']){
                                            $re['data'][$k]['ticketPrice'] = strval($vv['ticketPrice']);
                                            $re['data'][$k]['tongzheng'] = strval(round($vv['ticketPrice']/$tz_beilv,$this->xiaoshudian));
                                        }
                                    }
                                }
                            }
                        }
                    }
                    $this->redis2->set('c_paiqi_'.$featureAppNo,json_encode($re['data']),60*15);
                    return $this->withSuccess('ok',200,$re['data']);
                }else{
                    return $this->withError('没有座位了，请重新选择影片选座');
                }
            }else{
                return $this->withError($re['content']);
            }
        }
    }

    /**
     *锁座
     * @RequestMapping(path="dy_api_suozuo",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function dy_api_suozuo(){

        $tz_beilv = $this->tz_beilv();

        $cinemaId = $this->request->post('cinemaId','');//影院cinemaId
        $featureAppNo = $this->request->post('featureAppNo','');//排期编码

        $orderNo = 'MZ'.$this->get_number();
        $user_id = Context::get('user_id');
        $userInfo = Context::get('userInfo');
        $mobile = $userInfo['mobile'];
        if(empty($user_id)){
            return $this->withError('请登录');
        }

        $redwrap_switch = $this->redis0->get('is_dianying_buy');
        if($redwrap_switch != 1){
            return $this->withError('暂未开放');
        }

        $this->check_futou($user_id);

        $areaId = $this->request->post('areaId','0');//0000000000000001,0000000000000002 座位区间ID，多个参数用英 文半角逗号拼接（新增）
        $seatNo = $this->request->post('seatNo','');//0000000000000001-5-19,0000000000000002-5-17 座位编码，多个参数用英 文半角逗号拼接
        $seatPieceName = $this->request->post('seatPieceName','');//5排19座,5排17座 座位名称，多个参数用英 文半角逗号拼接（新增）

        $ticketPrice = $this->request->post('ticketPrice','');//50.00,50.00 票价，多个参数用英文半 角逗号拼接，顺序要与座 位编码对应
        $serviceAddFee = $this->request->post('serviceAddFee',0);//5.00,5.00 服务费，多个参数用英文 半角逗号拼接，顺序要与 座位编码对应，提交的金 额范围必须在排期接口中 返回的服务费上下限值之间

        if(empty($cinemaId)){
            return $this->withError('影院cinemaId不能空');
        }
        if(empty($featureAppNo)){
            return $this->withError('排期编码不能空');
        }

        //校验余额是否足够
        $ticketPrice_arr = explode(',',$ticketPrice);
        if(!empty($ticketPrice_arr)){
            $zongjia_zw = 0;
            foreach ($ticketPrice_arr as $v){
                $zongjia_zw += $v;
            }
            if($userInfo['tongzheng'] < round($zongjia_zw/$tz_beilv,$this->xiaoshudian)){
                return $this->withError('余额不足');
            }
        }


        if(empty($seatNo)){
            return $this->withError('座位编码不能空 例如：01-5-19,02-5-17');
        }
        if(empty($seatPieceName)){
            return $this->withError('座位名称不能空 例如：x排x座,x排x座');
        }
        if(empty($ticketPrice)){
            return $this->withError('票价不能空 例如：5.00,5.00');
        }
        if(empty($serviceAddFee)){
            //return $this->withError('服务费不能空 例如：5.00,5.00');
        }

        $yc_data = DsCinemaPaiqi::query()->where('featureAppNo',$featureAppNo)->first();
        if(!empty($yc_data)){
            $yc_data = $yc_data->toArray();
        }else{
            return $this->withError('没有该排期');
        }

        $url = '/partner/data4/lockSeat';

        $data = [
            'vendor' => $this->vendor,
            'ts' => time(),
            'orderNo' => $orderNo,
            'cinemaId' => $cinemaId,
            'featureAppNo' => $featureAppNo,
            'mobile' => $mobile,
            'areaId' => $areaId,
            'seatNo' => $seatNo,
            'seatPieceName' => $seatPieceName,
            'ticketPrice' => $ticketPrice,
            'serviceAddFee' => $serviceAddFee,
        ];
        $params = [
            'vendor' => $data['vendor'],
            'ts' => $data['ts'],
            'sign' => $this->makeSign($data,$this->apikey),
            'orderNo' => $data['orderNo'],
            'cinemaId' => $data['cinemaId'],
            'featureAppNo' => $data['featureAppNo'],
            'mobile' => $data['mobile'],
            'areaId' => $data['areaId'],
            'seatNo' => $data['seatNo'],
            'seatPieceName' => $data['seatPieceName'],
            'ticketPrice' => $data['ticketPrice'],
            'serviceAddFee' => $data['serviceAddFee'],
        ];

        $cinema_info = $this->http->post($this->url_dy.$url, ['headers' =>['Content-Type' => 'application/x-www-form-urlencoded'], 'form_params' => $params])->getBody()->getContents();
        if(empty($cinema_info))
        {
            return $this->withError('当前获取人数较多，请稍后再试');
        }else{
            $re = json_decode($cinema_info,true);
            if($re['status'] == 0){
                if(!empty($re['data'])){
                    $data2 = [
                        'user_id' => $user_id,
                        'orderNo' => $data['orderNo'],
                        'cinemaId' => $data['cinemaId'],
                        'featureAppNo' => $data['featureAppNo'],
                        'mobile' => $data['mobile'],
                        'areaId' => $data['areaId'],
                        'seatNo' => $data['seatNo'],
                        'seatPieceName' => $data['seatPieceName'],
                        'ticketPrice' => $data['ticketPrice'],
                        'serviceAddFee' => $data['serviceAddFee'],
                        'type' => 0,
                        'orderId' => $re['data']['orderId'],
                        'serialNum' => $re['data']['serialNum']?$re['data']['serialNum']:0,
                        'direct' => $re['data']['direct']?$re['data']['direct']:0,
                    ];
                    $re_sz = DsCinemaSuozuo::query()->insertGetId($data2);
                    if(!$re_sz){
                        DsErrorLog::add_log('锁坐成功-但加入锁坐数据库失败',json_encode($data2),$user_id);
                        return $this->withError('锁座失败，请稍候重新下单!');
                    }else{
                        return $this->withSuccess('锁座成功',200,$data2['orderId']);
                    }
                }else{
                    return $this->withError('锁座失败，请稍候重新下单');
                }
            }else{
                return $this->withError('此座位已经被抢了，请重新进入选座');
            }
        }
    }

    /**
     *释放锁座
     * @RequestMapping(path="dy_api_suozuo_sf",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function dy_api_suozuo_sf(){
        $orderId = $this->request->post('orderId','');

        $user_id = Context::get('user_id');
        if(empty($user_id)){
            return $this->withError('请登录');
        }

        if(empty($orderId)){
            return $this->withError('订单id不能为空');
        }

        $url = '/partner/data4/unLockOrder';

        $data = [
            'vendor' => $this->vendor,
            'ts' => time(),
            'orderId' => $orderId,
        ];
        $params = [
            'vendor' => $data['vendor'],
            'ts' => $data['ts'],
            'sign' => $this->makeSign($data,$this->apikey),
            'orderId' => $data['orderId'],
        ];

        $cinema_info = $this->http->post($this->url_dy.$url, ['headers' =>['Content-Type' => 'application/x-www-form-urlencoded'], 'form_params' => $params])->getBody()->getContents();
        if(empty($cinema_info))
        {
            return $this->withError('当前获取人数较多，请稍后再试');
        }else{
            $re = json_decode($cinema_info,true);
            if($re['status'] == 0){
                if(!empty($re['data'])){
                    DsCinemaSuozuo::query()->where('orderId',$re['data']['orderId'])->update([
                        'type' => 1,
                    ]);
                }
                return $this->withSuccess_ty_null('锁座释放成功');
            }else{
                return $this->withError($re['content']);
            }
        }
    }

    /**
     * 支付初始化
     * @RequestMapping(path="dy_api_csh",methods="post")
     * @return \Psr\Http\Message\ResponseInterface|void
     */
    public function dy_api_csh(){
        return $this->withError('请升级版本！');

        $tz_beilv = $this->tz_beilv();
        $city_id = $this->request->post('city_id','');//城市id
        $pq_id = $this->request->post('featureAppNo','');//排期编码
        $filmCode = $this->request->post('filmCode','');//影片编码
        $cinemaCode = $this->request->post('cinemaCode','');//影城编码
        $orderId = $this->request->post('orderId','');//锁坐订单id

        $num = $this->request->post('num',1);//购票数量
        $seatName = $this->request->post('seatName','');//座位名称，多个座位号使用英文版符合“|”分割，（注：不要复制文档上的“|”），例：1排2座|1排3座

        if(empty($pq_id) || empty($num)  || empty($seatName)){
            return $this->withError('排期编码/购票数量/座位名称必选');
        }

        $user_id = Context::get('user_id');
        $userInfo = Context::get('userInfo');

        $city_id = $this->redis2->get('cityCode_'.$user_id);
        if(empty($city_id)){
            return $this->withError('请进入首页刷新定位后再访问');
        }

        //查排期
        $paiqi = DsCinemaPaiqi::query()->where('featureAppNo',$pq_id)->first();
        if(empty($paiqi)){
            return $this->withError('没有排期，请等待下再下单');
        }
        $paiqi = $paiqi->toArray();

        //查影片
        $data_v = DsCinemaVideo::query()->where('cid',$paiqi['filmNo'])->first();
        if(empty($data_v)){
            return $this->withError('没有影片，请等待下再下单');
        }
        $data_v = $data_v->toArray();

        //查影城
        $data_yc = DsCinema::query()->where('cinemaCode',$paiqi['cinemaCode'])->first();
        if(empty($data_yc)){
            return $this->withError('没有影城，请等待下再下单');
        }
        $data_yc = $data_yc->toArray();

        $data = [];
        $data['seatName'] = $seatName;
        $data['name'] = $data_v['filmName'];
        $data['cover'] = $data_v['cover'];
        $data['type'] = $data_v['type'];
        $data['publishDate'] = $data_v['publishDate']?date('Y.m.d H:i',(int)$data_v['publishDate']):'';
        $data['duration'] = $data_v['duration'].'分钟';
        $data['class'] = ['IMAX 2D','DOlby Cinema'];
        $data['startTime'] = date('m-d H:i',(int)$paiqi['startTime']);

        $data['price2'] = $paiqi['ticketPrice']*$num;//实际总价
        $data['price_danjia'] = $paiqi['ticketPrice'];//实际单价
        $data['price'] = round(($paiqi['ticketPrice']*$num/$tz_beilv),$this->xiaoshudian);//通证总价
        $data['price_danjia_tz'] = round($paiqi['ticketPrice']/$tz_beilv,$this->xiaoshudian);//通证单价

        $data['hallName'] = $paiqi['hallName'];
        $data['mobile'] = $userInfo['mobile'];

        $data['cast'] = '主演：' . $data_v['director']?$data_v['director']:'暂无';

        //是否情侣座
        $is_love = $this->request->post('is_love',0);
        if($is_love > 0){
            if($num > 1){
                return $this->withError('情侣座只能单个下单');
            }
            $data['price2'] = $paiqi['ticketPrice']*$num*2;//实际总价
            $data['price_danjia'] = $paiqi['ticketPrice']*2;//实际单价
            $data['price'] = round(($paiqi['ticketPrice']*$num/$tz_beilv*2),$this->xiaoshudian);//通证总价
            $data['price_danjia_tz'] = round($paiqi['ticketPrice']/$tz_beilv*2,$this->xiaoshudian);//通证单价
        }



        //校验用户能否下单
        $user_pric =DsUser::query()->where('user_id',$user_id)->value('tongzheng');
        if($user_pric < $data['price']){
            return $this->withError('通证不足无法下单');
        }

        //检查是否能预售

        $hui_url = $this->redis0->get('callback_dianying_url');
        if(empty($hui_url)){
            return $this->withError('配置中...');
        }

        $orderId_suozuo = 'MZ'.$this->get_number();
        if(!empty($orderId)){
            $orderId_suozuo = DsCinemaSuozuo::query()->where('orderId',$orderId)->value('orderNo');
            if(empty($orderId_suozuo)){
                return $this->withError('锁座订单不存在，请重新锁座');
            }
        }

        $time = time();
        $params = [
            'user_id' => $user_id,
            'order_status' => 0,
            'payment_type' => 4,
            'status2' => 0,
            'guoqi_time' => $time + 60*15,
            'orderNumber' => $orderId_suozuo,
            'orderNumber2' => $this->get_number(),
            'mobile' => $data['mobile'],
            'buyTime' => $time,
            'movieName' => $data_v['filmName'],
            'cinemaName' => $data_yc['cinemaName'],
            'cinemaAddress' => $data_yc['address'],
            'poster' => $data_v['cover'],
            'language' => $paiqi['copyLanguage'],
            'plan_type' => $data_v['type'],
            'startTime' => $paiqi['startTime'],
            'ticketNum' => $num,
            'hallName' => $paiqi['hallName'],
            'hallType' => $paiqi['copyType'],
            'seatName' => $data['seatName'],
            'cityName' => $data_yc['province'].$data_yc['city'].$data_yc['county'],
            'is_tiaowei' => 1,
            'is_love' => $is_love,
            'yuanjia' => $data['price2'],//总金额元
            'yuanjia_tz' => $data['price'],//总扣除的通证
            'city_id' => $city_id,
            'curl' => $hui_url,
            'featureAppNo' => $pq_id,
            'price_danjia' => $data['price_danjia'],
            'duration' => $data_v['duration'],
            'longitude' => $data_yc['longitude'],
            'latitude' => $data_yc['latitude'],
            'filmCode' => $filmCode,
        ];

        //转换下单时 座位用|隔开
        $seatName = explode(',',$data['seatName']);
        if($seatName){
            $params2 = '';
            foreach ($seatName as $k => $v){
                $params2 .= $v.'|';
            }
            //去掉末尾|字符
            $params['seatName'] = rtrim($params2,'|');
        }

        $re = DsCinemaUserOrder::query()->insertGetId($params);
        if(!$re){
            return $this->withError('当前人数较多，请等待下再下单');
        }
        $data['cinema_order_id'] = $re;
        $data['buyTime'] = $params['buyTime']?date('Y-m-d H:i:s',$params['buyTime']):'';

        $sheng_over = $params['guoqi_time']-time();
        if($sheng_over > 0){
            $data['guoqi_time'] = $params['guoqi_time']-time();
        }else{
            $data['guoqi_time'] = 0;
        }


        return $this->withSuccess('ok',200,$data);
    }

    /**
     * 支付下单
     * @RequestMapping(path="get_dy_qpi_csh_order",methods="post")
     * @return \Psr\Http\Message\ResponseInterface|void
     */
    public function get_dy_qpi_csh_order(){
        //检测版本
        $this->_check_version('1.1.3');
        $cinema_order_id = $this->request->post('cinema_order_id','');//cinema_order_id

        if(empty($cinema_order_id)){
            return $this->withError('订单必选');
        }

        $user_id =  Context::get('user_id');
        $userInfo = Context::get('userInfo');
        if(empty($userInfo)){
            return $this->withError('请登录..');
        }

        $cinema_order_data = DsCinemaUserOrder::query()->where('cinema_order_id',$cinema_order_id)->where('user_id',$user_id)->first();
        if(empty($cinema_order_data)){
            return $this->withError('当前人数较多，请稍后再试');
        }
        $cinema_order_data = $cinema_order_data->toArray();
        if(empty($cinema_order_data)){
            return $this->withError('当前人数较多，请稍后再试!');
        }
        if($cinema_order_data['order_status'] > 0){
            return $this->withError('该订单已经支付，请前往查看详情');
        }

        if($cinema_order_data['status'] > 0){
            return $this->withError('不是待支付状态');
        }

        //订单是否过期
        if(time() > $cinema_order_data['guoqi_time']){
            return $this->withError('订单已过期，请重新下单');
        }

        //已经开始
        if(time() > $cinema_order_data['startTime']){
            return $this->withError('已经开始的电影无法下单');
        }

        //校验用户能否下单
        $user_pric =DsUser::query()->where('user_id',$user_id)->value('tongzheng');
        if($user_pric < $cinema_order_data['yuanjia_tz']){
            return $this->withError('通证不足无法下单');
        }

        $cinema_order_dataok = DsCinemaUserOrder::query()->where('user_id',$user_id)->first();
        if(!empty($cinema_order_dataok)){
            $cinema_order_dataok = $cinema_order_dataok->toArray();
            if($cinema_order_dataok['status'] == '0' && $cinema_order_dataok['order_status'] > 0){
                return $this->withError('你有出票中订单，请稍后完成后再下单！');
            }
        }

        $user_w = DsUserTongzheng::del_tongzheng($user_id,$cinema_order_data['yuanjia_tz'],'购买电影票'.$cinema_order_data['cinema_order_id']);
        if(!$user_w){
            DsErrorLog::add_log('用户下单电影扣通证失败',json_encode($cinema_order_data),$user_id);
            return $this->withError('当前人数较多，请稍后再试！');
        }
        DsCinemaUserOrder::query()->where('cinema_order_id',$cinema_order_id)->where('user_id',$user_id)->update([
            'pay_time' => time(),
            'order_status' => 1,
        ]);

        //异步
        $info = [
            'type' => '39',
            'cinema_order_id' => $cinema_order_id,
        ];
        $this->yibu($info);

        return $this->withSuccess('下单成功',200,$cinema_order_data['cinema_order_id']);

//同步
//        $url = '/partner/data4/GetOrderData';
//        $data = [
//            'vendor' => $this->vendor,
//            'ts' => time(),
//            'orderNumber' => $cinema_order_data['orderNumber'],
//            'mobile' => $cinema_order_data['mobile'],
//            'buyTime' => $cinema_order_data['buyTime'],
//            'movieName' => $cinema_order_data['movieName'],
//            'cinemaName' => $cinema_order_data['cinemaName'],
//            'cinemaAddress' => $cinema_order_data['cinemaAddress'],
//            'poster' => $cinema_order_data['poster'],
//            'language' => $cinema_order_data['language'],
//            'plan_type' => $cinema_order_data['plan_type'],
//            'startTime' => $cinema_order_data['startTime'],
//            'ticketNum' => $cinema_order_data['ticketNum'],
//            'hallName' => $cinema_order_data['hallName'],
//            'hallType' => $cinema_order_data['hallType'],
//            'seatName' => $cinema_order_data['seatName'],
//            'cityName' => $cinema_order_data['cityName'],
//            'is_tiaowei' => $cinema_order_data['is_tiaowei'],
//            'is_love' => $cinema_order_data['is_love'],
//            'yuanjia' => $cinema_order_data['yuanjia'],
//            'curl' => $cinema_order_data['curl'],
//        ];
//        $sign =  $this->makeSign($data,$this->apikey);
//        $data['sign'] = $sign;
//        $cinema_info = $this->http->post($this->url_dy.$url, ['headers' =>['Content-Type' => 'application/x-www-form-urlencoded'], 'form_params' => $data])->getBody()->getContents();
//        if(empty($cinema_info))
//        {
//            return $this->withError('当前获取人数较多，请稍后再试');
//        }else{
//            $re = json_decode($cinema_info,true);
//            if($re['status'] == 0){
//                $user_w = DsUserTongzheng::del_tongzheng($user_id,round($cinema_order_data['yuanjia']/$tz_beilv,$this->xiaoshudian),'购买电影票'.$cinema_order_data['cinema_order_id']);
//                if(!$user_w){
//                    DsErrorLog::add_log('用户下单电影扣通证失败',json_encode($cinema_order_data),$user_id);
//                }
//                DsCinemaUserOrder::query()->where('cinema_order_id',$cinema_order_id)->where('user_id',$user_id)->update([
//                    'pay_time' => time(),
//                    'order_status' => 1,
//                ]);
//                return $this->withSuccess('下单成功',200,$cinema_order_data['cinema_order_id']);
//            }else{
//                return $this->withError($re['content']);
//            }
//        }
    }

    /**
     * 用户主动取消订单
     * @RequestMapping(path="order_quxiao",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function order_quxiao(){
        $user_id = Context::get('user_id');
        $userInfo = Context::get('userInfo');

        $cinema_order_id = $this->request->post('cinema_order_id',0);//cinema_order_id

        $re = DsCinemaUserOrder::query()->where('cinema_order_id',$cinema_order_id)->first();
        if(empty($re)){
            return $this->withError('该订单不存在');
        }
        if(empty($re['status'] == '3')){
            return $this->withError('该订单已经取消');
        }
        if(empty($re['status'] > 0)){
            return $this->withError('该订单不能取消');
        }
        if($re['guoqi_time'] > time()){
            DsCinemaUserOrder::query()->where('cinema_order_id',$cinema_order_id)->update(['status' => 3]);
            return $this->withError('该订单已过期');
        }
        DsCinemaUserOrder::query()->where('cinema_order_id',$cinema_order_id)->update(['status' => 3]);
        return  $this->withSuccess('取消成功');
    }

    /**
     *获取订单详细
     * @RequestMapping(path="dy_get_order_data",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function dy_get_order_data(){
        $tz_beilv = $this->tz_beilv();

         $cinema_order_id = $this->request->post('cinema_order_id','');//cinema_order_id

        $user_id =  Context::get('user_id');
        if(empty($user_id)){
            return $this->withError('登录已过期~');
        }

        if(empty($cinema_order_id)){
            return $this->withError('订单必选');
        }

        $order = DsCinemaUserOrder::query()->where('cinema_order_id',$cinema_order_id)->where('user_id',$user_id)->first();
        if(empty($order)){
            return $this->withError('没有该订单');
        }

        $data = [];
        //开场时间戳
        $kai = $order['startTime']-time();
        if($kai>0){
            $over_time = Chuli::time_have($order['startTime']);
            $tstr = '';
            if($over_time['day']){
                $tstr = $over_time['day'].'天';
            }
            if($over_time['hour']){
                $tstr .= $over_time['hour'].'时';
            }
            if($over_time['minute']){
                $tstr .= $over_time['minute'].'分';
            }
            if($over_time['second']){
                //$tstr .= $over_time['second'].'秒';
            }
            $data['begin_time'] = $tstr.'后开场';
        }else{
            $data['begin_time'] = '已经结束';
        }

        $data['movieName'] = $order['movieName'];
        $data['begin_qujian'] = date('Y-m-d H:i:s',(int)$order['startTime']) . '~' . date('Y-m-d H:i:s',(int)($order['startTime'] + $order['duration']*60));
        $data['language'] = $order['language'];
        $data['plan_type'] = $order['plan_type']?$order['plan_type']:'';
        $data['cinemaName'] = $order['cinemaName'];
        $data['hallName'] = $order['hallName'];
        $data['cinemaAddress'] = $order['cinemaAddress'];
        $data['poster'] = $order['poster'];
        $data['seatName'] = $order['seatName'];
        $data['ticketNum'] = $order['ticketNum'];
        $data['tongzheng'] = round($order['yuanjia']/$tz_beilv,$this->xiaoshudian);
        $data['mobile'] = Chuli::str_hide($order['mobile'],3,4);
        $data['orderNumber2'] = $order['orderNumber2'];
        $data['order_status'] = $order['order_status'];

        $re = [];
        if($order['erweima']){
            $oder_zuowei = json_decode($order['erweima'],true);
            if(is_array($oder_zuowei)){
                $seatName_arr = explode('|',$data['seatName']);
                if(is_array($seatName_arr)){
                    $seatName_arr_count = count($seatName_arr);
                    //判断2个数组
                    $seatName = $data['seatName'];
                    $seatName_status = 0;
                    if($seatName_arr_count > count($oder_zuowei)){
                        $seatName_status = 1;
                    }
                    foreach($oder_zuowei as $k => $v){
                        $re[$k]['ticketCode'] = $v['ticketCode'];
                        $re[$k]['url'] = $v['url'];
                        $re[$k]['yuan_url'] = $v['yuan_url'];
                        if($seatName_status){
                            $re[$k]['seatName'] = $seatName;
                        }else{
                            $re[$k]['seatName'] = $seatName_arr[$k];
                        }
                     }
                }
            }
            $data['erweima'] = $re;
        }else{
            $data['erweima'] = [];
        }

        if($order['pay_time']){
            $data['pay_time'] = date('Y-m-d H:i:s',(int)$order['pay_time']);
        }else{
            $data['pay_time'] = '';
        }

        $data['copyType'] = $order['hallType'];
        $data['status'] = $order['status'];
        $data['yuanjia'] = $order['yuanjia'];

        return $this->withSuccess('ok',200,$data);
    }


    /**
     * 查询订单 查询秒出票订单状态
     * @RequestMapping(path="dy_api_get_order",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function dy_api_get_order(){
        $orderId = $this->request->post('orderId','');//影院cinemaId

        $user_id = Context::get('user_id');
        if(empty($user_id)){
            return $this->withError('请登录');
        }

        if(empty($orderId)){
            return $this->withError('订单id不能为空');
        }

        $ord = DsCinemaUserOrder::query()->where('cinema_order_id',$orderId)->where('user_id',$user_id)->first();
        if(empty($ord)){
            //return $this->withError('订单不存在');
        }

        $url = '/partner/data4/getOrderStatus';

        $data = [
            'vendor' => $this->vendor,
            'ts' => time(),
            'orderId' => $orderId,
        ];
        $params = [
            'vendor' => $data['vendor'],
            'ts' => $data['ts'],
            'sign' => $this->makeSign($data,$this->apikey),
            'orderId' => $data['orderId'],
        ];
        var_dump($this->url_dy.$url);
        var_dump($params);
        $cinema_info = $this->http->post($this->url_dy.$url, ['form_params' => $params])->getBody()->getContents();
        if(empty($cinema_info))
        {
            return $this->withError('当前获取人数较多，请稍后再试');
        }else{
            $re = json_decode($cinema_info,true);
var_dump($re);
            if($re['status'] == 0){
                if(!empty($re['data'])){
                    if($re['data']){

                    }
                    //$or = DsCinemaUserOrder::query()->where('orderNumber',$re['data'][''])->first();

                }
                return $this->withResponse('获取成功',$re);
            }else{
                $or = DsCinemaUserOrder::query()->where('orderNumber',$re['data'][''])->first();

                return $this->withError($re['content']);
            }
        }
    }

    /**
     * 获取电影订单日志
     * @RequestMapping(path="get_user_dy_order_logs",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get_user_dy_order_logs()
    {
        $user_id  = Context::get('user_id');

        if(empty($user_id)){
            return $this->withError('登录已过期');
        }

        $page     = $this->request->post('page',1);
        if($page <= 0)
        {
            $page = 1;
        }
        $data['more']           = '0';
        $data['data']         = [];
        $type = $this->request->post('type',0); //0全部 1待付款 2待观影 3已观影

        $res = DsCinemaUserOrder::query()->where('user_id',$user_id)
            ->where('order_status','>',0)
            ->orderByDesc('cinema_order_id')
            ->forPage($page,10)->get()->toArray();

        if(!empty($type)){
            switch ($type){
                case 1:
                    //$where['order_status'] = ['=',0];
//                    $res = DsCinemaUserOrder::query()->where('user_id',$user_id)
//                        ->where('order_status',0)
//                        ->orderByDesc('cinema_order_id')
//                        ->forPage($page,10)->get()->toArray();

                    $res = DsCinemaUserOrder::query()->where('user_id',$user_id)
                        ->where('order_status',1)
                        ->where('startTime','>',time())
                        ->orderByDesc('cinema_order_id')
                        ->forPage($page,10)->get()->toArray();
                    break;
                case 2:
                    //$where['startTime'] = ['>',time()];
                    //$where['order_status'] = ['=',1];
                    $res = DsCinemaUserOrder::query()->where('user_id',$user_id)
                        ->where('order_status',1)
                        ->where('startTime','>',time())
                        ->orderByDesc('cinema_order_id')
                        ->forPage($page,10)->get()->toArray();
                    break;
                case 3:
                    //$where['startTime'] = ['<',time()];
                    //$where['order_status'] = ['=',1];
                    $res = DsCinemaUserOrder::query()->where('user_id',$user_id)
                        ->where('order_status',1)
                        ->where('startTime','<',time())
                        ->orderByDesc('cinema_order_id')
                        ->forPage($page,10)->get()->toArray();
                    break;
            }
        }


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
                $datas[$k]['name'] = $v['cinemaName'];
                $datas[$k]['type_name'] = '状态';
                if($v['order_status'] < 1){
                    $datas[$k]['type'] = 1;
                    $datas[$k]['type_name'] = '待付款';
                }else{
                    if($v['startTime'] > time()){
                        $datas[$k]['type'] = 2;
                        $datas[$k]['type_name'] = '待观影';
                    }
                    if($v['startTime'] < time()){
                        $datas[$k]['type'] = 3;
                        $datas[$k]['type_name'] = '已观影';
                    }
                    if($v['status'] == '3'){
                        $datas[$k]['type'] = 4;
                        $datas[$k]['type_name'] = '已关闭';
                    }
                }
                $datas[$k]['poster']  = $v['poster'];
                $datas[$k]['movieName']  = $v['movieName'];
                $datas[$k]['startTime']  = $this->replaceTime($v['startTime']);
                $datas[$k]['buyTime']  = $this->replaceTime($v['buyTime']);
                $datas[$k]['hallName']  = $v['hallName'];
                $datas[$k]['seatName']  = $v['seatName'];
                $datas[$k]['ticketNum']  = $v['ticketNum'];
                $datas[$k]['yuanjia']  = $v['yuanjia'];
                $datas[$k]['cinema_order_id']  = $v['cinema_order_id'];
                $datas[$k]['featureAppNo']  = $v['featureAppNo'];
                $datas[$k]['yuanjia_tz']  = $v['yuanjia_tz'];

                $datas[$k]['film_code'] = '';
                $yp = DsCinemaVideo::query()->where('cid',$v['filmCode'])->value('cid');
                if($yp){
                    $datas[$k]['film_code'] = $yp;
                }
            }
            $data['data']           = $datas;
        }

        return $this->withResponse('获取成功',$data);
    }

    /**
     * 影片评论
     * @RequestMapping(path="comment_add",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function comment_add(){
        $user_id  = Context::get('user_id');
        $video_id     = $this->request->post('filmCode','');
        $content     = $this->request->post('content','');

        if(empty($video_id) || empty($content)){
            return $this->withSuccess('影片/内容不能空');
        }

        if(mb_strlen($content) > 500){
            return $this->withSuccess('字数不能超过500');
        }

        $video = DsCinemaVideo::query()->where('cid',$video_id)->value('cid');
        if(empty($video)){
            return $this->withError('影片已下架');
        }

        $time = time();
        $re = DsCinemaVideoComment::query()->insertGetId([
            'user_id' => $user_id,
            'comment_content' => $content,
            'cid' => $video_id,
            'comment_time' => $time,
        ]);
        if(!$re){
            return $this->withError('当前人数较多，请稍后再试');
        }
        DsCinemaVideo::query()->where('cid',$video_id)->increment('comment_num');
        return $this->withResponse('评论成功',[
            'comment_id' =>  $re,
            'content' =>  $content,
            'time' =>  date('Y-m-d H:i:s',$time),
        ]);
    }

    /**
     * 评论点赞
     * @RequestMapping(path="comment_like",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function comment_like(){
        $user_id  = Context::get('user_id');
        $video_id     = $this->request->post('filmCode','');
        $comment_id     = $this->request->post('comment_id','');

        if(empty($video_id) || empty($comment_id)){
            return $this->withSuccess('影片/点赞的评论不能空');
        }

        $this->check_often($user_id);
        $this->start_often($user_id);

        $video = DsCinemaVideo::query()->where('cid',$video_id)->select('cid','comment_num','like_num')->first();
        if(empty($video)){
            return $this->withError('影片已下架');
        }
        $DsCinemaVideoComment = DsCinemaVideoComment::query()->where('comment_id',$comment_id)->select('comment_id','comment_like')->first();
        if(empty($DsCinemaVideoComment)){
            return $this->withError('评论不存在');
        }

        //查询用户是否已经给该影片点赞
        $status = DsCinemaVideoCommentLike::query()->where('user_id',$user_id)->where('comment_id',$comment_id)->value('comment_like_id');
        if(!empty($status)){
            $re = DsCinemaVideoCommentLike::query()->where('user_id',$user_id)->where('comment_id',$comment_id)->delete();
            if(!$re){
                return $this->withError('当前人数较多，请稍后再试');
            }
            if($video['comment_num'] > 0){
                DsCinemaVideo::query()->where('cid',$video_id)->decrement('like_num');
            }
            if($DsCinemaVideoComment['comment_like'] > 0) {
                DsCinemaVideoComment::query()->where('cid', $video_id)->where('comment_id', $comment_id)->decrement('comment_like');
            }
            return $this->withSuccess('取消点赞成功');
        }else{
            $re = DsCinemaVideoCommentLike::query()->insert([
                'user_id' => $user_id,
                'comment_id' => $comment_id,
                'cid' => $video_id,
            ]);
            if(!$re){
                return $this->withError('当前人数较多，点赞失败');
            }
            DsCinemaVideo::query()->where('cid',$video_id)->increment('like_num');
            DsCinemaVideoComment::query()->where('cid',$video_id)->where('comment_id', $comment_id)->increment('comment_like');
            return $this->withSuccess('点赞成功');
        }
    }

    /**
     * 返回用户id
     * @return string
     */
    public static function _key(): string
    {
        $user_id    = Context::get('user_id');
        return strval($user_id);
    }
}
