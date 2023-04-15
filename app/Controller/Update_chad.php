<?php declare(strict_types=1);

namespace App\Controller;


use App\Controller\async\Auth;
use App\Controller\async\Laqudianyingdo;
use App\Controller\async\OrderSn;
use App\Job\Chatimhuanxin;
use App\Job\Register;
use App\Model\DsCinema;
use App\Model\DsCinemaPaiqi;
use App\Model\DsCinemaVideo;
use App\Model\DsCinemaVideoComment;
use App\Model\DsCinemaVideoCommentLike;
use App\Model\DsCity;
use App\Model\DsCodeLog;
use App\Model\DsCzHuafeiUserOrder;
use App\Model\DsErrorLog;
use App\Model\DsOffline;
use App\Model\DsOrder;
use App\Model\DsPrice;
use App\Model\DsRedwrapOut;
use App\Model\DsUser;
use App\Model\DsUserPuman;
use App\Model\DsUserTaskPack;
use App\Model\DsUserTongzheng;
use App\Model\DsViewlog;
use App\Model\SysUser;
use Hyperf\AsyncQueue\Driver\DriverFactory;
use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Utils\Context;


/**
 *
 * @package App\Controller
 * @Controller(prefix="update_chad")
 * Class UpdateController
 */
class Update_chad extends XiaoController
{
    protected $url_dy = 'https://api.nldyp.com';
    protected $apikey = 'ccd78abcd7ec84879ee0598fb813a6c7';
    protected $vendor = '00274';
    protected $c_url = 'http://www.baidu.com';
    protected $hui_url = 'http://xxx:9510/v1/callbacklaqu/callbacklaqu_dy';//回调

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
     * 获取所有影院 并加入数据库
     * @RequestMapping(path="get_dy_cinema",methods="post,get")
     * @return \Psr\Http\Message\ResponseInterface
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

        try {
            $cinema_info = $this->http->post($this->url_dy.$url, ['headers' =>['Content-Type' => 'application/x-www-form-urlencoded'], 'form_params' => $params])->getBody()->getContents();
            if(empty($cinema_info))
            {
                return $this->withError('当前获取人数较多，请稍后再试');
            }else{
                $re = json_decode($cinema_info,true);

                if($re['code'] == 0){
                    if(!empty($re['data'])){

                        DsCinema::query()->delete();
                        foreach ($re['data'] as $k => $v){
                            $city_data = DsCity::query()->where('name','like',$v['city'].'%')->select('city_id')->orderByDesc('level')->first();
                            if(empty($city_data)){
                                continue;
                            }
                            $city_data = $city_data->toArray();
                            if(empty($city_data)){
                                continue;
                                //$city_id = 110100;
                            }else{
                                if(empty($city_data['city_id'])){
                                    continue;
                                    //$city_id = 110100;
                                }else{
                                    $city_id =$city_data['city_id'];
                                }
                            }

                            $data = [
                                'cid' => $v['id'],
                                'cinemaId' => $v['cinemaId'],
                                'cinemaCode' => $v['cinemaCode'],
                                'cinemaName' => $v['cinemaName'],
                                'provinceId' => $v['provinceId'],
                                'cityId' => $v['cityId'],
                                'countyId' => $v['countyId'],
                                'address' => $v['address'],
                                'longitude' => $v['longitude'],
                                'latitude' => $v['latitude'],
                                'province' => $v['province'],
                                'city' => $v['city'],
                                'county' => $v['county'],
                                'stopSaleTime' => $v['stopSaleTime'],
                                'direct' => $v['direct'],
                                'backTicketConfig' => $v['backTicketConfig'],
                                'city_id' => $city_id,//本地系统的城市id
                            ];
                            DsCinema::query()->insert($data);
                        }

                    }

                    return $this->withSuccess('获取所有影院ok,更新个：'.count($re['data']));
                    //return $this->withResponse('获取所有影院ok',$re);
                }else{
                    return $this->withError($re['msg']);
                }
            }
        }catch (\Exception $e)
        {
            return $this->withError($e->getMessage());
        }
    }

    /**
     *获取所有影片 并加入数据库
     * @RequestMapping(path="get_dy_video",methods="post,get")
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
                }

                return $this->withSuccess('获取所有影片ok个：'.count($re['data']));
            }else{
                return $this->withError($re['msg']);
            }
        }
    }

    /**
    更新所有影城的排期 每隔时间执行就行
     * @RequestMapping(path="get_dy_paiqi_do",methods="post,get")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get_dy_paiqi_do()
    {
        $pq_num_update = $this->redis0->get('pq_num_update');
        if(empty($pq_num_update)){
            $pq_num_update = 20;
        }

        $yingcheng_list = DsCinema::query()
            ->where('type',0)
            ->inRandomOrder()
            ->select('cid','city','cinemaCode','city_id')
            ->limit($pq_num_update)
            ->get()->toArray();

        //make before
        //        $re = make(Laqudianyingdo::class);
        //        $re->get_dy_paiqi_do($yingcheng_list);
        //        return $this->withSuccess('ok');
        //make after


        //yibu before
        //        $info = [
        //            'type' => 40,
        //            'list' => $yingcheng_list,
        //        ];
        //        $this->yibu($info);
        //        return $this->withSuccess('ok');
        //yibu after

        //同 before
        ini_set('memory_limit','3000M');
        var_dump(date('Y-m-d H:i:s').':更新开始');
        $url = $this->url_dy . '/partner/data4/getPlan';
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
                //var_dump($params);
                $cinema_info = $this->http->post($url, ['headers' =>['Content-Type' => 'application/x-www-form-urlencoded'], 'form_params' => $params])->getBody()->getContents();

                if($cinema_info)
                {
                    $re = json_decode($cinema_info,true);
                    if($re['code'] == 0){
                        if(!empty($re['data'])){
                            if(is_array($re['data'])){
                                if(!empty($v['city_id'])){
                                    $city_id = $v['city_id'];
                                }else{
                                    $city_id = 110100;
                                }
                                DsCinemaPaiqi::query()->where('cinemaCode',$v['cinemaCode'])->delete();
                                $insertData = [];
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
                                    $insertData[] = $data2;//拼装2维数组
                                }
                                DsCinemaPaiqi::query()->insert($insertData);
                            }
                        }
                    }
                    DsCinema::query()->where('cid',$v['cid'])->update(['type'=>1]);
                }
            }
        }
        var_dump(date('Y-m-d H:i:s').':本次更新成功影院个：'.$num1);
        var_dump(date('Y-m-d H:i:s').':本次更新成功影院排期个：'.$num2);
        return $this->withSuccess('ok');
        //同 after





//        $url = $this->url_dy . '/partner/data4/getPlan';
//        DsCinemaPaiqi::query()->delete();
//        $yingcheng_list = DsCinema::query()
//            ->inRandomOrder()
//            ->select('cid','city')->get()->toArray();
//
//        $num1 = 0;
//        $num2 = 0;
//        if($yingcheng_list){
//            foreach ($yingcheng_list as $k => $v){
//
//                $num1 += 1;
//                //查询单个影城的排期并加入数据库
//                $data = [
//                    'vendor' => $this->vendor,
//                    'ts' => time(),
//                    'cinemaId' => $v['cid'],
//                ];
//                $params = [
//                    'vendor' => $data['vendor'],
//                    'ts' => $data['ts'],
//                    'cinemaId' => $data['cinemaId'],
//                    'sign' => $this->makeSign($data,$this->apikey),
//                ];
//
//                $cinema_info = $this->http->post($url, ['headers' =>['Content-Type' => 'application/x-www-form-urlencoded'], 'form_params' => $params])->getBody()->getContents();
//                if($cinema_info)
//                {
//                    $re = json_decode($cinema_info,true);
//                    if($re['code'] == 0){
//                        if(!empty($re['data'])){
//                            if(is_array($re['data'])){
//                                $city_data = DsCity::query()->where('name','like',$v['city'].'%')->select('city_id')->orderByDesc('level')->first();
//                                if(empty($city_data['city_id'])){
//                                    $city_id = 110100;
//                                }else{
//                                    $city_id = $city_data['city_id'];
//                                }
//                                foreach ($re['data'] as $vv){
//                                    $num2 += 1;
//                                    $data2 = [
//                                        'featureAppNo' => $vv['featureAppNo'],
//                                        'cinemaCode' => $vv['cinemaCode'],
//                                        'sourceFilmNo' => $vv['sourceFilmNo'],
//                                        'filmNo' => $vv['filmNo'],
//                                        'filmName' => $vv['filmName'],
//                                        'hallNo' => $vv['hallNo'],
//                                        'hallName' => $vv['hallName'],
//                                        'startTime' => $vv['startTime'],
//                                        'copyType' => $vv['copyType'],
//                                        'copyLanguage' => $vv['copyLanguage'],
//                                        'totalTime' => $vv['totalTime'],
//                                        'listingPrice' => $vv['listingPrice'],
//                                        'ticketPrice' => $vv['ticketPrice'],
//                                        'serviceAddFee' => $vv['serviceAddFee'],
//                                        'lowestPrice' => $vv['lowestPrice'],
//                                        'thresholds' => $vv['thresholds'],
//                                        'areas' => $vv['areas'],
//                                        'marketPrice' => $vv['marketPrice'],
//                                        'city_id' => $city_id,
//                                        'update_time' => date('Y-m-d H:i:s',time()),
//                                    ];
//                                    DsCinemaPaiqi::query()->insert($data2);
//                                }
//                            }
//                        }
//                    }
//                    //DsCinema::query()->where('cid',$v['cid'])->update(['type'=>1]);
//                }
//            }
//        }
//        var_dump('本次更新成功影院个：'.$num1);
//        var_dump('本次更新成功影院排期个：'.$num2);

        //return $this->withSuccess('ok影院：'.$num1.'、排期：'.$num2);
    }

    /**
     * 将所有影院设置成需要更新状态
     * @RequestMapping(path="dy_set_gengxin_type0",methods="post,get")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function dy_set_gengxin_type0(){
        DsCinema::query()->update(['type'=>0]);
        return $this->withSuccess('将所有影院设置成需要更新状态成功');
    }

    /**
     * 福禄 订单查询
     * @RequestMapping(path="fulu_order_select",methods="post,get")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function fulu_order_select(){
        $cz_huafei_user_order_id = DsCzHuafeiUserOrder::query()->where('status',2)->limit(50)->select()->get()->toArray();
        if(is_array($cz_huafei_user_order_id) && !empty($cz_huafei_user_order_id)){
            foreach ($cz_huafei_user_order_id as $k => $v){
                $info = [
                    'type' => 38,
                    'order_id' => $v['cz_huafei_user_order_id'],
                ];
                $this->yibu($info);
            }
        }else{
            return $this->withError('没有处理中订单');
        }

        return $this->withSuccess('ok');
    }


    /**
     * 生成直推排线码
     * @RequestMapping(path="ma_add_yibu",methods="post,get")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function ma_add_yibu(){
        $user_list = DsUser::query()->whereNull('ma_zhitui')->select('user_id')->limit(2000)->get()->toArray();
        if(!empty($user_list)){
            $info = [
                'type' => '41',
                'user_list' => $user_list,
            ];
            $this->yibu($info);
        }
        return $this->withSuccess('ok');
    }

    /**
     *
     * 绑定用户排线码直推码
     * @RequestMapping(path="ma_bangding_yibu",methods="post,get")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function ma_bangding_yibu()
    {
        $num = $this->request->input('num',200);
        $user_list = DsUser::query()->whereNull('ma_zhitui')
            ->limit((int)$num)->pluck('user_id')->toArray();
        if(!empty($user_list))
        {
            foreach ($user_list as $v)
            {
                $info = [
                    'type' => '42',
                    'user_list' => $v,
                ];
                $this->yibu($info);
            }
        }
        return $this->withSuccess('ok');
    }

    /**
     *
     * huanxin token pilaing shengcheng
     * @RequestMapping(path="huanxin_token_yibu",methods="post,get")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function huanxin_token_yibu()
    {
        $num = $this->request->input('num',200);
        $user_list = DsUser::query()->whereNull('huanxin_token')
            ->inRandomOrder()
            ->limit((int)$num)->pluck('user_id')->toArray();
        //$user_list = [681376,681377,681378,681379,681380,681381,681382,681383];//测试
        //$user_list = [660006,660008];//测试
        if(!empty($user_list))
        {
            foreach ($user_list as $v)
            {
                $info = [
                    'user_id'        => $v,
                ];
                $job = ApplicationContext::getContainer()->get(DriverFactory::class);
                $job->get('async')->push(new Chatimhuanxin($info));
            }
        }

        return $this->withSuccess('ok');
    }


    /**
     * 过期红包检测
     * @RequestMapping(path="check_bao_endtime",methods="post,get")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function check_bao_endtime(){
//        if($redwrap_out_array['time_end'] < time()){
//            $u_end_time = DsRedwrapOut::query()->where('redwrap_out_id',$redwrap_out_id)->update([
//                'status' => 3,
//            ]);
//            if(empty($u_end_time)){
//                DsErrorLog::add_log('红包-过期-重置过期状态失败',json_encode($redwrap_out_array),'红包-过期-重置过期状态失败');
//            }else{
//                //退
//                switch ($redwrap_out_array['class']){
//                    case '1':
//                        $get_yue = DsUserTongzheng::add_tongzheng($redwrap_out_array['user_id'],$redwrap_out_array['money'],'红包过期退回【'.$redwrap_out_array['redwrap_out_id'].'】');
//                        if(empty($get_yue)){
//                            DsErrorLog::add_log('红包-通证-领取失败',json_encode($data),$user_id);
//                            return $this->withError('当前领取人数较多，请稍后再试！');
//                        }
//                        break;
//                    case '2':
//                        $get_yue = DsUserPuman::add_puman($user_id,$redwrap_out_array['money'],'获得红包【'.$redwrap_out_array['redwrap_out_id'].'】');
//                        if(empty($get_yue)){
//                            DsErrorLog::add_log('红包-扑满-领取失败',json_encode($data),$user_id);
//                            return $this->withError('当前领取人数较多，请稍后再试！！');
//                        }
//                        break;
//                    default:
//                        return $this->withError('没有该过期退回类型！');
//                        break;
//                }
//            }
//            return $this->withError('已经过期了，无法领取');
//        }
    }

    /**
     *
     * 批量删除环信用户
     * @RequestMapping(path="del_user_do",methods="post,get")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function del_user_do(){
        return 1;
        $num = $this->request->input('num',200);
        $user_list = DsUser::query()->whereNull('huanxin_token')
            ->inRandomOrder()
            ->limit((int)$num)->pluck('user_id')->toArray();

        $data = [
                    [
                        'username' => "4",
                        'nickname' => "mz-用户",
                    ]
        ];

        $coin = make(ChatIm::class);
        $res = $coin->del_user($data);

//        if(!empty($user_list))
//        {
//            foreach ($user_list as $v)
//            {
//
//
////                $info = [
////                    'user_id'        => $v,
////                ];
////                $job = ApplicationContext::getContainer()->get(DriverFactory::class);
////                $job->get('async')->push(new Chatimhuanxin($info));
//            }
//        }

        return $this->withSuccess('ok');
    }

    /**
     * 更新统计
     * @RequestMapping(path="update_chad_tongji",methods="get,post")
     */
    public function update_chad_tongji()
    {
        $before_time = 0;
        $after_time = 0;
        $val = 0;
        //商家_总_入驻数量
        $data['tj_offline_all_num'] = $val= DsOffline::query()->where('offline_status', 1)->count();
        $this->redis0->set('tj_offline_all_num',strval($val));

        //商家_今日_入驻数量
        $val = 0;
        $before_time =  strtotime(date('Y-m-d 00:00:00',time()));
        $after_time = strtotime(date('Y-m-d 23:59:59',time()));
        $data['tj_offline_day_num'] = $val= DsOffline::query()->where('offline_status', 1)->whereBetween('offline_in_time',[$before_time,$after_time])->count();
        $this->redis0->set('tj_offline_day_num',strval($val));

        //昨日商家入驻
        $val = 0;
        $before_time =  strtotime(date("Y-m-d",strtotime("-1 day"))." 0:0:0");
        $after_time = strtotime(date("Y-m-d",strtotime("-1 day"))." 23:59:59");
        $data['tj_offline_yesterday_num'] = $val = DsOffline::query()->where('offline_status', 1)->whereBetween('offline_in_time',[$before_time,$after_time])->count();
        $this->redis0->set('tj_offline_yesterday_num',strval($val));

        //本月商家入驻
        $val = 0;
        $BeginDate_string = date('Y-m-01 00:00:00', strtotime(date("Y-m-d"))); //这个月第一天时间字符串
        $before_time = strtotime(date('Y-m-01 00:00:00', strtotime(date("Y-m-d")))); //这个月第一天
        $after_time = strtotime(date('Y-m-d', strtotime("$BeginDate_string +1 month -1 day")));//这个月最后一天
        $data['tj_offline_yue_num'] = $val = DsOffline::query()->where('offline_status', 1)->whereBetween('offline_in_time',[$before_time,$after_time])->count();
        $this->redis0->set('tj_offline_yue_num',strval($val));

        //上月商家入驻
        $val = 0;
        $before_time = strtotime(date('Y-m-01 00:00:00',strtotime('-1 month')));
        $after_time = strtotime(date("Y-m-d 23:59:59", strtotime(-date('d').'day')));
        $data['tj_offline_yue_top_num'] = $val = DsOffline::query()->where('offline_status', 1)->whereBetween('offline_in_time',[$before_time,$after_time])->count();
        $this->redis0->set('tj_offline_yue_top_num',strval($val));

        //昨日新增人数
        $val = 0;
        $before_time =  strtotime(date("Y-m-d",strtotime("-1 day"))." 0:0:0");
        $after_time = strtotime(date("Y-m-d",strtotime("-1 day"))." 23:59:59");
        $data['tj_user_yesterday_num'] = $val = DsUser::query()->whereBetween('reg_time',[$before_time,$after_time])->count();
        $this->redis0->set('tj_user_yesterday_num',strval($val));

        //总支付宝开通VIP人数
        $val = 0;
        $data['tj_user_vip_alipay_all_num'] = $val = DsOrder::query()->where('payment_type',1)->where('order_status',1)->where('order_type',2)->count();
        $this->redis0->set('tj_user_vip_alipay_all_num',strval($val));

        //今日支付宝开通VIP人数
        $val = 0;
        $before_time =  strtotime(date('Y-m-d 00:00:00',time()));
        $after_time = strtotime(date('Y-m-d 23:59:59',time()));
        $data['tj_user_vip_alipay_day_num'] = $val = DsOrder::query()->where('payment_type',1)->where('order_status',1)->where('order_type',2)->whereBetween('pay_time',[$before_time,$after_time])->count();
        $this->redis0->set('tj_user_vip_alipay_day_num',strval($val));

        //昨日支付宝开通VIP人数
        $val = 0;
        $before_time =  strtotime(date("Y-m-d",strtotime("-1 day"))." 0:0:0");
        $after_time = strtotime(date("Y-m-d",strtotime("-1 day"))." 23:59:59");
        $data['tj_user_vip_alipay_yesterday_num'] = $val = DsOrder::query()->where('payment_type',1)->where('order_status',1)->where('order_type',2)->whereBetween('pay_time',[$before_time,$after_time])->count();
        $this->redis0->set('tj_user_vip_alipay_yesterday_num',strval($val));

        //总支付宝开通VIP金额
        $val = 0;
        $data['tj_user_vip_alipay_all_price'] = $val = DsOrder::query()->where('payment_type',1)->where('order_status',1)->whereIn('order_type',[2,3])->sum('money');
        $this->redis0->set('tj_user_vip_alipay_all_price',strval($val));

        //今日支付宝开通VIP金额
        $val = 0;
        $before_time =  strtotime(date('Y-m-d 00:00:00',time()));
        $after_time = strtotime(date('Y-m-d 23:59:59',time()));
        $data['tj_user_vip_alipay_day_price'] = $val = DsOrder::query()->where('payment_type',1)->where('order_status',1)->whereIn('order_type',[2,3])->whereBetween('pay_time',[$before_time,$after_time])->sum('money');
        $this->redis0->set('tj_user_vip_alipay_day_price',strval($val));

        //昨日支付宝开通VIP金额
        $val = 0;
        $before_time =  strtotime(date("Y-m-d",strtotime("-1 day"))." 0:0:0");
        $after_time = strtotime(date("Y-m-d",strtotime("-1 day"))." 23:59:59");
        $data['tj_user_vip_alipay_yesterday_price'] = $val = DsOrder::query()->where('payment_type',1)->where('order_status',1)->whereIn('order_type',[2,3])->whereBetween('pay_time',[$before_time,$after_time])->sum('money');
        $this->redis0->set('tj_user_vip_alipay_yesterday_price',strval($val));

        //总通证开通VIP人数
        $val = 0;
        $data['tj_user_vip_tz_all_num'] = $val = DsUserTongzheng::query()->where('user_tongzheng_type',2)->where('user_tongzheng_cont','开通会员')->count();
        $this->redis0->set('tj_user_vip_tz_all_num',strval($val));

        //今日通证开通VIP人数
        $val = 0;
        $before_time =  strtotime(date('Y-m-d 00:00:00',time()));
        $after_time = strtotime(date('Y-m-d 23:59:59',time()));
        $data['tj_user_vip_tz_day_num'] = $val = DsUserTongzheng::query()->where('user_tongzheng_type',2)->where('user_tongzheng_cont','开通会员')->whereBetween('user_tongzheng_time',[$before_time,$after_time])->count();
        $this->redis0->set('tj_user_vip_tz_day_num',strval($val));

        //昨日通证开通VIP人数
        $val = 0;
        $before_time =  strtotime(date("Y-m-d",strtotime("-1 day"))." 0:0:0");
        $after_time = strtotime(date("Y-m-d",strtotime("-1 day"))." 23:59:59");
        $data['tj_user_vip_tz_yesterday_num'] = $val = DsUserTongzheng::query()->where('user_tongzheng_type',2)->where('user_tongzheng_cont','开通会员')->whereBetween('user_tongzheng_time',[$before_time,$after_time])->count();
        $this->redis0->set('tj_user_vip_tz_yesterday_num',strval($val));

        //总通证开通VIP金额
        $val = 0;
        $data['tj_user_vip_tz_all_price'] = $val = DsUserTongzheng::query()->where('user_tongzheng_type',2)->where('user_tongzheng_cont','like','%会员')->sum('user_tongzheng_change');
        $this->redis0->set('tj_user_vip_tz_all_price',strval($val));

        //今日通证开通VIP金额
        $val = 0;
        $before_time =  strtotime(date('Y-m-d 00:00:00',time()));
        $after_time = strtotime(date('Y-m-d 23:59:59',time()));
        $data['tj_user_vip_tz_day_price'] = $val = DsUserTongzheng::query()->where('user_tongzheng_type',2)->where('user_tongzheng_cont','like','%会员')->whereBetween('user_tongzheng_time',[$before_time,$after_time])->sum('user_tongzheng_change');
        $this->redis0->set('tj_user_vip_tz_day_price',strval($val));

        //昨日通证开通VIP金额
        $val = 0;
        $before_time =  strtotime(date("Y-m-d",strtotime("-1 day"))." 0:0:0");
        $after_time = strtotime(date("Y-m-d",strtotime("-1 day"))." 23:59:59");
        $data['tj_user_vip_tz_yesterday_price'] = $val = DsUserTongzheng::query()->where('user_tongzheng_type',2)->where('user_tongzheng_cont','like','%会员')->whereBetween('user_tongzheng_time',[$before_time,$after_time])->sum('user_tongzheng_change');
        $this->redis0->set('tj_user_vip_tz_yesterday_price',strval($val));

        //总发红包-通证金额
        $val = 0;
        $data['tj_user_hongbao_tz_all_price'] = $val = DsRedwrapOut::query()->where('status',2)->where('class',1)->sum('money');
        $this->redis0->set('tj_user_hongbao_tz_all_price',strval($val));

        //总发红包-扑满金额
        $val = 0;
        $data['tj_user_hongbao_pumna_all_price'] = $val = DsRedwrapOut::query()->where('status',2)->where('class',2)->sum('money');
        $this->redis0->set('tj_user_hongbao_pumna_all_price',strval($val));

        //总发红包-通证金额-今日
        $val = 0;
        $before_time =  strtotime(date('Y-m-d 00:00:00',time()));
        $after_time = strtotime(date('Y-m-d 23:59:59',time()));
        $data['tj_user_hongbao_tz_day_price'] = $val = DsRedwrapOut::query()->where('status',2)->where('class',1)->whereBetween('time',[$before_time,$after_time])->sum('money');
        $this->redis0->set('tj_user_hongbao_tz_day_price',strval($val));

        //总发红包-扑满金额-今日
        $val = 0;
        $before_time =  strtotime(date('Y-m-d 00:00:00',time()));
        $after_time = strtotime(date('Y-m-d 23:59:59',time()));
        $data['tj_user_hongbao_pumna_day_price'] = $val = DsRedwrapOut::query()->where('status',2)->where('class',2)->whereBetween('time',[$before_time,$after_time])->sum('money');
        $this->redis0->set('tj_user_hongbao_pumna_day_price',strval($val));

        //总扑满金额
        $val = 0;
        $data['tj_user_pumna_all_price'] = $val = DsUser::query()->sum('puman');
        $this->redis0->set('tj_user_pumna_all_price',strval($val));

        //总影票金额
        $val = 0;
        $data['tj_user_yingpiao_all_price'] = $val = DsUser::query()->sum('yingpiao');
        $this->redis0->set('tj_user_yingpiao_all_price',strval($val));

        //总通证金额
        $val = 0;
        $data['tj_user_tz_all_price'] = $val = DsUser::query()->sum('tongzheng');
        $this->redis0->set('tj_user_tz_all_price',strval($val));

        return $this->withResponse('ok',$data);
    }

    /**
     * 星级群聊拉人
     * @RequestMapping(path="qun_xingji_add_user",methods="get,post")
     */
    public function qun_xingji_add_user(){
        $user_ids = DsUser::query()->where('group','>',0)->whereNotNull('huanxin_token')->pluck('user_id');

        $group_id = $this->redis0->get('im_xingji_number');
        if(empty($group_id)){
            return $this->withError('群id不能为空');
        }
        if(empty($user_ids)){
            return $this->withError('没有用户');
        }
        foreach ($user_ids as $v){
            $user_id = strval($v);
            $is_jin = $this->redis2->get('user_qun_in_'.$group_id.'_'.$user_id);
            if(empty($is_jin)){
                $info = [
                    'type' => '45',
                    'group_id' => strval($group_id),
                    'user_ids' => [$user_id],
                ];
                $this->yibu($info);
                $this->redis2->set('user_qun_in_'.$group_id.'_'.$user_id,1,86400*7);
            }
        }

        return $this->withSuccess('执行成功');
    }

    /**
     * vip群聊拉人
     * @RequestMapping(path="qun_vip_add_user",methods="get,post")
     */
    public function qun_vip_add_user(){
        $user_ids = DsUser::query()->where('role_id','>',0)->where('vip_end_time','>',time())->pluck('user_id');

        $group_id = $this->redis0->get('im_vip_number');
        if(empty($group_id)){
            return $this->withError('群id不能为空');
        }
        if(empty($user_ids)){
            return $this->withError('没有用户');
        }

        foreach ($user_ids as $v){
            $user_id = strval($v);
            $is_jin = $this->redis2->get('user_qun_in_'.$group_id.'_'.$user_id);
            if(empty($is_jin)){
                $info = [
                    'type' => '45',
                    'group_id' => strval($group_id),
                    'user_ids' => [$user_id],
                ];
                $this->yibu($info);
                $this->redis2->set('user_qun_in_'.$group_id.'_'.$user_id,1,86400*7);
            }
        }

        return $this->withSuccess('执行成功');
    }

}