<?php declare(strict_types=1);

namespace App\Controller\async;

use App\Model\Chuli;
use App\Model\DsCinema;
use App\Model\DsCinemaPaiqi;
use App\Model\DsCinemaUserOrder;
use App\Model\DsCity;
use App\Model\DsCzHuafeiUserOrder;
use App\Model\DsErrorLog;
use App\Model\DsUser;
use App\Model\DsUserTongzheng;
use Hyperf\Guzzle\ClientFactory;
use Hyperf\Redis\RedisFactory;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Context;
use Swoole\Exception;

/**
 * 异步 电影
 * @package App\Controller
 */
class Laqudianyingdo
{
    protected $url_dy = 'https://api.nldyp.com';
    protected $apikey = 'ccd78abcd7ec84879ee0598fb813a6c7';
    protected $vendor = '00274';

    protected $redis2;
    protected $http;

    public function __construct()
    {
        $this->url_dy = 'https://api.nldyp.com';
        $this->apikey = 'ccd78abcd7ec84879ee0598fb813a6c7';
        $this->vendor = '00274';

        $this->container = ApplicationContext::getContainer();
        $this->redis2 = $this->container->get(RedisFactory::class)->get('db2');
        $this->http   = ApplicationContext::getContainer()->get(ClientFactory::class)->create();
    }

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

    public function order_add($cinema_order_id){
        if(empty($cinema_order_id)){
            return false;
        }
        $cinema_order_data = DsCinemaUserOrder::query()->where('cinema_order_id',$cinema_order_id)->first();
        if(empty($cinema_order_data)){
            return false;
        }
        $url = '/partner/data4/GetOrderData';
        $data = [
            'vendor' => $this->vendor,
            'ts' => time(),
            'orderNumber' => $cinema_order_data['orderNumber'],
            'mobile' => $cinema_order_data['mobile'],
            'buyTime' => $cinema_order_data['buyTime'],
            'movieName' => $cinema_order_data['movieName'],
            'cinemaName' => $cinema_order_data['cinemaName'],
            'cinemaAddress' => $cinema_order_data['cinemaAddress'],
            'poster' => $cinema_order_data['poster'],
            'language' => $cinema_order_data['language'],
            'plan_type' => $cinema_order_data['plan_type'],
            'startTime' => $cinema_order_data['startTime'],
            'ticketNum' => $cinema_order_data['ticketNum'],
            'hallName' => $cinema_order_data['hallName'],
            'hallType' => $cinema_order_data['hallType'],
            'seatName' => $cinema_order_data['seatName'],
            'cityName' => $cinema_order_data['cityName'],
            'is_tiaowei' => $cinema_order_data['is_tiaowei'],
            'is_love' => $cinema_order_data['is_love'],
            'yuanjia' => $cinema_order_data['yuanjia'],
            'curl' => $cinema_order_data['curl'],
        ];
        $sign =  $this->makeSign($data,$this->apikey);
        $data['sign'] = $sign;

        try {
            $cinema_info = $this->http->post($this->url_dy.$url, ['headers' =>['Content-Type' => 'application/x-www-form-urlencoded'], 'form_params' => $data])->getBody()->getContents();
            if(empty($cinema_info))
            {
                DsCinemaUserOrder::query()->where('cinema_order_id',$cinema_order_id)->where('user_id',$cinema_order_data['user_id'])->update([
                    'status' => 3,//状态改为已关闭
                ]);
                $user_w = DsUserTongzheng::add_tongzheng($cinema_order_data['user_id'],$cinema_order_data['yuanjia_tz'],'购买电影票退回'.$cinema_order_data['cinema_order_id']);
                if(!$user_w){
                    DsErrorLog::add_log('用户下单退回失败',json_encode($cinema_order_data),$cinema_order_data['user_id']);
                }
            }else{
                $re = json_decode($cinema_info,true);
                if($re['status'] == 0){
                    DsCinemaUserOrder::query()->where('cinema_order_id',$cinema_order_id)->where('user_id',$cinema_order_data['user_id'])->update([
                        'beizhu' => '下单成功等待处理',
                    ]);
                }else{
                    DsCinemaUserOrder::query()->where('cinema_order_id',$cinema_order_id)->where('user_id',$cinema_order_data['user_id'])->update([
                        'status' => 3,//状态改为已关闭
                        'beizhu' => '非200状态',
                    ]);
                    $user_w = DsUserTongzheng::add_tongzheng($cinema_order_data['user_id'],$cinema_order_data['yuanjia_tz'],'购买电影票退回'.$cinema_order_data['cinema_order_id']);
                    if(!$user_w){
                        DsErrorLog::add_log('用户下单异常退回失败',json_encode($cinema_order_data),$cinema_order_data['user_id']);
                    }
                }
            }
        } catch (\Throwable $throwable) {
            DsCinemaUserOrder::query()->where('cinema_order_id',$cinema_order_id)->where('user_id',$cinema_order_data['user_id'])->update([
                'status' => 3,//状态改为已关闭
                'beizhu' => json_encode($throwable->getMessage()),
            ]);
            $user_w = DsUserTongzheng::add_tongzheng($cinema_order_data['user_id'],$cinema_order_data['yuanjia_tz'],'购买电影票退回'.$cinema_order_data['cinema_order_id']);
            if(!$user_w){
                DsErrorLog::add_log('用户下单异常退回失败',json_encode($cinema_order_data),$cinema_order_data['user_id']);
            }
        }
    }

    /**
     * 更新所有影城的排期 每隔时间执行就行
     * @return void
     */
    public function get_dy_paiqi_do($yingcheng_list)
    {

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

                $cinema_info = $this->http->post($url, ['headers' =>['Content-Type' => 'application/x-www-form-urlencoded'], 'form_params' => $params])->getBody()->getContents();
                if($cinema_info)
                {
                    $re = json_decode($cinema_info,true);
                    if($re['code'] == 0){
                        if(!empty($re['data'])){
                            if(is_array($re['data'])){
                                $city_id = 0;

                                if(!empty($v['city_id'])){
                                    $city_id = $v['city_id'];
                                }else{
                                    $city_id = 110100;
                                }

//                                $city_data = DsCity::query()->where('name','like',$v['city'].'%')->select('city_id')->orderByDesc('level')->first();
//                                if(!empty($city_data)){
//                                    $city_data = $city_data->toArray();
//                                    if(!empty($city_data)){
//                                        $city_id = $city_data['city_id'];
//                                    }else{
//                                        continue;
//                                    }
//                                }else{
//                                    continue;
//                                }


                                DsCinemaPaiqi::query()->where('cinemaCode',$v['cinemaCode'])->delete();
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
                    DsCinema::query()->where('cid',$v['cid'])->update(['type'=>1]);
                }
            }
        }
        var_dump(date('Y-m-d H:i:s').':本次更新成功影院个：'.$num1);
        var_dump(date('Y-m-d H:i:s').':本次更新成功影院排期个：'.$num2);
        //return $this->withSuccess('ok影院：'.$num1.'、排期：'.$num2);
    }
}