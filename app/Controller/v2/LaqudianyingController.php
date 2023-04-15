<?php

declare(strict_types=1);

namespace App\Controller\v2;

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
 * @package App\Controller\v2
 * @Controller(prefix="v2/laqudianying")
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


    /**
     * 支付初始化
     * @RequestMapping(path="dy_api_csh",methods="post")
     * @return \Psr\Http\Message\ResponseInterface|void
     */
    public function dy_api_csh(){
        $tz_beilv = $this->tz_beilv();

        $city_id = $this->request->post('city_id','');//城市id
        $pq_id = $this->request->post('featureAppNo','');//排期编码
        $filmCode = $this->request->post('filmCode','');//影片编码
        $cinemaCode = $this->request->post('cinemaCode','');//影城编码
        $orderId = $this->request->post('orderId','');//锁座订单id

        $user_id = Context::get('user_id');
        $userInfo = Context::get('userInfo');

        $num = $this->request->post('num',1);//购票数量
        $seatName = $this->request->post('seatName','');//座位名称，多个座位号使用英文版符合“|”分割，（注：不要复制文档上的“|”），例：1排2座|1排3座

        if(empty($pq_id) || empty($num)  || empty($seatName)){
            return $this->withError('排期编码/购票数量/座位名称必选');
        }

        $this->check_often($user_id);
        $this->start_often($user_id);

        $redwrap_switch = $this->redis0->get('is_dianying_buy');
        if($redwrap_switch != 1){
            return $this->withError('暂未开放');
        }
        $this->check_futou($user_id);
        if(empty($orderId)){
            return $this->withError('请先锁座');
        }
        //查排期
        $paiqi = DsCinemaPaiqi::query()->where('featureAppNo',$pq_id)->first();
        if(empty($paiqi)){
            return $this->withError('没有排期，请等待下再下单');
        }
        $paiqi = $paiqi->toArray();

        $orderId_suozuo = DsCinemaSuozuo::query()->where('orderId',$orderId)->where('user_id',$user_id)->select('orderNo','areaId','ticketPrice','serviceAddFee')->first();
        if(empty($orderId_suozuo)){
            return $this->withError('锁座订单不存在，请重新锁座');
        }
        $orderId_suozuo = $orderId_suozuo->toArray();

        $city_id = $this->redis2->get('cityCode_'.$user_id);
        if(empty($city_id)){
            return $this->withError('请进入首页刷新定位后再访问');
        }

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

        //获取锁座订单金额
        $jiage_s = $paiqi['ticketPrice'];
        if(!empty($orderId_suozuo['ticketPrice'])){
            $suo_arr = explode(',',$orderId_suozuo['ticketPrice']);
            if(is_array($suo_arr)){
                $jiage_s = 0;
                foreach ($suo_arr as $v){
                    $jiage_s += $v;
                }
                $data['price2'] = round($jiage_s,2);//实际总价
                $data['price_danjia'] = $paiqi['ticketPrice'];//实际单价
                $data['price'] = round(($jiage_s/$tz_beilv),$this->xiaoshudian);//通证总价
                $data['price_danjia_tz'] = round($paiqi['ticketPrice']/$tz_beilv,$this->xiaoshudian);//通证单价
            }
        }else{
            return $this->withError('锁座未成功，请重新下单');
//            $data['price2'] = $paiqi['ticketPrice']*$num;//实际总价
//            $data['price_danjia'] = $paiqi['ticketPrice'];//实际单价
//            $data['price'] = round(($paiqi['ticketPrice']*$num/$tz_beilv),$this->xiaoshudian);//通证总价
//            $data['price_danjia_tz'] = round($paiqi['ticketPrice']/$tz_beilv,$this->xiaoshudian);//通证单价
        }

        $data['hallName'] = $paiqi['hallName'];
        $data['mobile'] = $userInfo['mobile'];

        $data['cast'] = '主演：' . $data_v['director']?$data_v['director']:'暂无';

        //是否情侣座
        $is_love = $this->request->post('is_love',0);
        if($is_love > 0){
            if($num > 1){
                return $this->withError('情侣座只能单个下单');
            }
            $data['price2'] = round($paiqi['ticketPrice']*$num*2,2);//实际总价
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

        $time = time();
        $params = [
            'user_id' => $user_id,
            'order_status' => 0,
            'payment_type' => 4,
            'status2' => 0,
            'guoqi_time' => $time + 60*15,
            'orderNumber' => $orderId_suozuo['orderNo'],
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
            'filmCode' => $paiqi['filmNo'],
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
     * 支付下单
     * @RequestMapping(path="get_dy_qpi_csh_order",methods="post")
     * @return \Psr\Http\Message\ResponseInterface|void
     */
    public function get_dy_qpi_csh_order(){
        $cinema_order_id = $this->request->post('cinema_order_id','');//cinema_order_id

        if(empty($cinema_order_id)){
            return $this->withError('订单必选');
        }

        $user_id =  Context::get('user_id');
        $userInfo = Context::get('userInfo');
        if(empty($userInfo)){
            return $this->withError('请登录..');
        }

        $this->check_often($user_id);
        $this->start_often($user_id);

        //验证码校验
        $code = $this->request->post('code','');
        if(empty($code))
        {
            return $this->withError('验证码不能为空');
        }
        $this->_checkCode($userInfo['mobile'],'dy_order',$code,time(),$user_id);

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
