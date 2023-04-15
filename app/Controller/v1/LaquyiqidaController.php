<?php

declare(strict_types=1);

namespace App\Controller\v1;

use App\Controller\XiaoController;
use App\Model\Chuli;
use App\Model\DsCinema;
use App\Model\DsCinemaCity;
use App\Model\DsCinemaCityChild;
use App\Model\DsCinemaUserOrder;
use App\Model\DsCinemaVideo;
use App\Model\DsCinemaPaiqi;
use App\Model\DsCzUserOrder;
use App\Model\DsErrorLog;
use App\Model\DsUserTongzheng;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;



use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Annotation\Middlewares;
use App\Middleware\SignatureMiddleware;
use App\Middleware\UserMiddleware;
use Hyperf\Utils\Context;

use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Annotation\Controller;

/**
 *
 * @Middlewares({
 *     @Middleware(SignatureMiddleware::class),
 *     @Middleware(UserMiddleware::class)
 * })
 * @package App\Controller\v1
 * @Controller(prefix="v1/laquyiqida")
 */
class LaquyiqidaController extends XiaoController
{

    protected $game_url = 'http://open.yiqida.cn';
    protected $game_key = '57c511d824dbf2d2a7b1eae350673397';
    protected $game_username = 'jinhoumanzuo';
    protected $callbackUrl = 'http://xxxxx';//测试回调

    /**
     *获取分组
     * @RequestMapping(path="get_yqd_goods_type",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Swoole\Exception
     */
    public function get_yqd_goods_type(){
        $user_id = Context::get('user_id');
        $userInfo = Context::get('userInfo');
return $this->withResponse('ok',[]);
        $this->check_often($user_id);
        $this->start_often($user_id);

        $time = Chuli::get_time_13number();

        $url2 = $this->game_url.'/api/UserCommdity/GetCatalogList?';
        $pm1 = 'timestamp='.$time.'&userName='.$this->game_username;
        $pm = [

        ];
        $sign = md5($time.json_encode($pm).$this->game_key); //md5(timestamp+data+key)，注意data代表请求json格式参数。

        $p_data =   $url2.$pm1.'&sign='.$sign;//请求url 拼接

        $info = $this->http->post($p_data, ['headers' =>['Content-Type' => 'application/json'], 'json' => $pm])->getBody()->getContents();
        if(empty($info))
        {
            return $this->withError('当前获取人数较多，请稍后再试');
        }else{
            $re = json_decode($info,true);
            if($re['code'] == 200){
                return $this->withSuccess('ok',200,$re['data']);
            }else{
                return $this->withError($re['msg']);
            }
        }
    }

    /**
     * 获取充值商品列表
     * @RequestMapping(path="get_yqd_goods_list",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get_yqd_goods_list(){
        $user_id = Context::get('user_id');
        $userInfo = Context::get('userInfo');

        $page = $this->request->post('page',1);
        $catalogId = $this->request->post('catalogId',0);

        if(empty($catalogId)){
            return $this->withError('请先选择分类');
        }

        $data = [];
        $data['more']=0;

        $this->check_often($user_id);
        $this->start_often($user_id);

        $time = Chuli::get_time_13number();
        $url2 = $this->game_url.'/api/UserCommdity/GetCommodityList?';
        $pm1 = 'timestamp='.$time.'&userName='.$this->game_username;
        $pm = [
            'page' => $page,
            'size' => 20,
            'catalogId' => $catalogId,
        ];
        $sign = md5($time.json_encode($pm).$this->game_key); //md5(timestamp+data+key)，注意data代表请求json格式参数。
        $p_data =   $url2.$pm1.'&sign='.$sign;//请求url 拼接
        $info = $this->http->post($p_data, ['headers' =>['Content-Type' => 'application/json'], 'json' => $pm])->getBody()->getContents();
        if(empty($info))
        {
            return $this->withError('当前获取人数较多，请稍后再试');
        }else{
            $re = json_decode($info,true);
            if($re['code'] == 200){
                $res = [];
                if($re['data']){
                    $data['more']=1;
                    foreach ($re['data'] as $k => $v){
                        $res[$k]['mainId'] = $v['mainId'];
                        $res[$k]['branchId'] = $v['branchId'];
                        $res[$k]['branchName'] = $v['name'];
                        $res[$k]['branchImg'] = $v['branchImg'];
                        $res[$k]['name'] = $v['name'];
                        $res[$k]['price'] = $v['price'];
                        $res[$k]['guidePrice'] = $v['guidePrice'];
                        $res[$k]['catalogId'] = $v['catalogId'];
                    }
                }
                $data['data'] = $res;
                return $this->withSuccess('ok',200,$data);
            }else{
                return $this->withError($re['msg']);
            }
        }
    }

    protected function get_goods($id){
        $time = Chuli::get_time_13number();

        $reids_yqd_goods = $this->redis2->get('yqd_goods_'.$id);
        if(!empty($reids_yqd_goods)){
            return json_decode($reids_yqd_goods,true);
        }else{
            $url2 = $this->game_url.'/api/UserCommdity/GetCommodityInfo?';
            $pm1 = 'timestamp='.$time.'&userName='.$this->game_username;
            $pm = [
                'id' => $id
            ];
            $sign = md5($time.json_encode($pm).$this->game_key); //md5(timestamp+data+key)，注意data代表请求json格式参数。
            $p_data =   $url2.$pm1.'&sign='.$sign;//请求url 拼接
            $info = $this->http->post($p_data, ['headers' =>['Content-Type' => 'application/json'], 'json' => $pm])->getBody()->getContents();
            if(empty($info))
            {
                return false;
            }else{
                $re = json_decode($info,true);
                if($re['code'] == 200){
                    if($re['data'] && is_array($re['data'])){
                        $this->redis2->set('yqd_goods_'.$id,json_encode($re['data']),60*5);
                        return $re['data'];
                    }else{
                        return false;
                    }
                }else{
                    return false;
                }
            }
        }
    }

    /**生成唯一单号
     * @return string
     */
    protected function get_number(){
        return  date('Ymd').substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8) . rand(100000,9999999);
    }

    /**
     *下单
     * @RequestMapping(path="yqd_order_add",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function yqd_order_add(){
        $tz_beilv = $this->tz_beilv();

        $user_id = Context::get('user_id');
        $userInfo = Context::get('userInfo');

        $this->check_often($user_id);
        $this->start_often($user_id);

        return $this->withError('请升级APP版本');

        $commodityId = $this->request->post('commodityId');
        $mobile = $this->request->post('mobile','');

        if(empty($commodityId) || empty($mobile)){
            return $this->withError('请先选择商品/填写手机');
        }
        $re = $this->get_goods($commodityId);
        if(empty($re)){
            return $this->withError('商品上架中，请稍候');
        }

        $callbackUrl = $this->redis0->get('callback_yiqida_url');
        if(empty($callbackUrl)){
            return $this->withError('配置中...');
        }
        if(empty($re['name'])){
            $re['name'] = '充值';
        }

        $buyCount = 1;
        //先记录本地订单
        $data = [
            'user_id' => $user_id,
            'ordersn' => $this->get_number(),
            'commodityId' => $commodityId,
            'external_orderno' => $this->get_number(),
            'buyCount' => $buyCount,
            'remark' => '',
            'callbackUrl' => $callbackUrl,
            'externalSellPrice' => $re['price'],
            'template' => json_encode([urlencode("$mobile")]),
            'mainId' => $re['mainId'],
            'branchId' => $re['branchId'],
            'branchName' => '',
            'branchImg' => $re['branchImg'],
            'MainImg' => $re['MainImg'],
            'name' => $re['name'],
            'guidePrice' => $re['guidePrice'],
            'price_all' => $re['price']*$buyCount,
            'time' => time(),
        ];

        $get_price = round($data['price_all']/$tz_beilv,$this->xiaoshudian);
        if($userInfo['tongzheng'] < $get_price){
            return $this->withError('通证不足，还差：' . round($get_price-$userInfo['tongzheng'],2));
        }

        $kouchu = DsUserTongzheng::del_tongzheng($user_id,$get_price,'购买'.$re['name']);
        if(!$kouchu){
            return $this->withError('当前人数较多，请稍后再试');
        }

        $r_yqd_order = DsCzUserOrder::query()->insertGetId($data);
        if(!$r_yqd_order){
            return $this->withError('当前获取人数较多，请稍后再试!');
        }

        $time = Chuli::get_time_13number();
        $url2 = $this->game_url.'/api/UserOrder/CreateOrder?';
        $pm1 = 'timestamp='.$time.'&userName='.$this->game_username;
        $pm = [
            'commodityId' => $data['commodityId'],
            'external_orderno' => $data['external_orderno'],
            'buyCount' => $buyCount,
            'externalSellPrice' => $data['externalSellPrice'],
            'template' => [urlencode("$mobile")],
        ];
        $sign = md5($time.json_encode($pm).$this->game_key); //md5(timestamp+data+key)，注意data代表请求json格式参数。
        $p_data =   $url2.$pm1.'&sign='.$sign;//请求url 拼接
        $info = $this->http->post($p_data, ['headers' =>['Content-Type' => 'application/json'], 'json' => $pm])->getBody()->getContents();
        if(empty($info))
        {
            $kouchu = DsUserTongzheng::add_tongzheng($user_id,$get_price,'购买'.$re['name'].'退回');
            if(!$kouchu){
                DsErrorLog::add_log('充值退回失败',json_encode($data),'充值退回失败');
            }
            return $this->withError('当前获取人数较多，请稍后再试');
        }else{
            $re2 = json_decode($info,true);
            if($re2['code'] == 200){
                $ru = DsCzUserOrder::query()->where('cz_id',$r_yqd_order)->update([
                   'status' => 2,
                   'orderno' => $re2['data']['orderNo'],
                   'orderid' => $re2['data']['orderId'],
                ]);
                if(!$ru){
                    DsErrorLog::add_log('充值-加入数据失败',json_encode($data),'充值-加入数据失败');
                }
                return $this->withSuccess('充值成功');
            }else{
                $kouchu = DsUserTongzheng::add_tongzheng($user_id,$get_price,'购买'.$re['name'].'退回');
                if(!$kouchu){
                    DsErrorLog::add_log('充值退回失败！',json_encode($data),'充值退回失败');
                }
                return $this->withError($re2['msg']);
            }
        }
    }

}
