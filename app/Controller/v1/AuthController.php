<?php declare(strict_types=1);

namespace App\Controller\v1;

use App\Controller\async\Auth;
use App\Controller\async\OrderSn;
use App\Controller\XiaoController;
use App\Model\DsOrder;
use App\Model\DsUser;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Annotation\Middleware;
use App\Middleware\UserMiddleware;
use Hyperf\HttpServer\Annotation\Middlewares;
use App\Middleware\SignatureMiddleware;
use Hyperf\Utils\Context;
use Hyperf\RateLimit\Annotation\RateLimit;
/**
 * 我的实名认证接口
 * Class AuthController
 * @Middlewares({
 *     @Middleware(SignatureMiddleware::class),
 *     @Middleware(UserMiddleware::class)
 * })
 * @package App\Controller\v1
 * @Controller(prefix="v1/auth")
 */
class AuthController extends XiaoController
{

    /**
     * 实名认证
     * @RequestMapping(path="auth",methods="post")
     * 状态 0未实名认证 1认证成功 2认证失败 3进行身份证提交  4进行扫描人脸  5审核中
     */
    public function auth()
    {
        $is_user_auth = $this->redis0->get('is_user_auth');
        if($is_user_auth != 1)
        {
            return $this->withError('暂未开启实名认证系统,请耐心等待通知！');
        }
        $user_id    = Context::get('user_id');
        $userInfo     = Context::get('userInfo');
        switch ($userInfo['auth'])
        {
            case 0:
                #进行身份证提交
                return $this->auth_sfz($user_id);
                break;
            case 1:
                #认证成功
                return $this->withError('您已认证成功！');
                break;
            case 2:
                #认证失败
                return $this->auth_sfz($user_id);
                break;
            case 3:
                #进行身份证提交
                return $this->auth_sfz($user_id);
                break;
            case 4:
                #进行扫描人脸
                return $this->auth_face($user_id);
                break;
            case 5:
                #审核中
                $CertifyId = $this->redis3->get($user_id.'_CertifyId');
                $infos = [
                    'type'              => '9',
                    'user_id'           => $user_id,
                    'CertifyId'         => $CertifyId,
                    'shenfenzheng_num'  => $userInfo['auth_num']
                ];
                $this->yibu($infos,rand(2, 5));
                return $this->withError('请耐心等待审核预计时间3分钟');
                break;
        }
    }

    /**
     * 支付
     * @param $user_id
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Swoole\Exception
     */
    protected function auth_pay($user_id)
    {
        //判断用户是否支付
        $res = DsOrder::query()->where('user_id',$user_id)->where('order_status',1)
            ->where('order_type',1)->value('order_id');
        if($res)
        {
            DsUser::query()->where('user_id',$user_id)->update(['auth' => 4]);
            return $this->auth_face($user_id);
        }
        $pay_type   = $this->request->post('pay_type',1); //1是支付宝 2是微信
        if($pay_type !=1)
        {
            $pay_type = 2;
        }
        //生成订单
        //生成订单号
        $od = make(OrderSn::class);
        $orderSn = $od->createOrderSN();
        //1.8-1.9随机
        $price = $this->randomFloat(1.8,1.89);
        $orderDatas = [
            'order_sn'          => $orderSn,         // 这里要保证订单的唯一性
            'user_id'           => $user_id,         //用户id
            'to_id'             => 0,                //为谁支付的id
            'payment_type'      => $pay_type,        //支付类型  1.alipay   2.wechat
            'need_money'        => $price,              //需要支付的金额
            'order_status'      => '0',              //订单状态 0-未支付；1-已支付
            'add_time'          => time(),           //订单生成时间
            'order_type'        => 1,              //开通类型 1实名认证
        ];
        //入库
        $insert = DsOrder::query()->insert($orderDatas);
        if($insert)
        {
            //付钱
            $cpay = make(CommonPayController::class);
            $z = $cpay->pay($user_id,$pay_type,$price,$orderSn,'实名认证');
            if($pay_type == 1)
            {
                return $this->withResponse('订单生成成功',$z,201);
            }else{
                return $this->withResponse('订单生成成功',$z,203);
            }
        }else{
            return $this->withError('当前人数较多,请稍后再试');
        }
    }

    /**
     * 身份证提交
     * @param $user_id
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function auth_sfz($user_id)
    {
        $userInfo       = Context::get('userInfo');

        $name = $this->request->post('name','');
        $card = $this->request->post('card','');
        if(empty($name) || empty($card))
        {
            return $this->withError('请填写完整信息！');
        }
        //检查身份证
        if(!$this->validateIdCard($card))
        {
            return $this->withError('身份证号码有误,请重新填写！！');
        }
        //查找已认证身份证是否重复
        $shenfenzheng_num = DsUser::query()
            ->where('auth_num',$card)
            ->where('auth',1)
            ->value('auth_num');
        if(!empty($shenfenzheng_num))
        {
            return $this->withError('此身份证信息已实名,请填写新的信息');
        }
        $rv = DsUser::query()->where('user_id',$user_id)->update(['auth_name' => $name,'auth_num' => $card,'auth' => 4,'auth_error' => '']);
        if($rv)
        {
            //进行人脸
            return $this->auth_face($user_id);
        }else{
            return $this->withError('当前实名人数较多，请从新打开APP再试');
        }
    }

    /**
     * 进行人脸
     * @param $user_id
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Swoole\Exception
     */
    protected function auth_face($user_id)
    {
        $metaInfo = $this->request->post('metaInfo','');
        //判读用户失败次数
        $ci = $this->redis3->get('auth_shibai_ci_new'.$user_id);
        if($ci >= 3)
        {
            return $this->withError('您实名认证失败次数较多，请联系客服！');
        }
        if(empty($metaInfo))
        {
            return $this->withError('请给与设备信息!');
        }
        $infos = DsUser::query()->where('user_id',$user_id)->select('auth_name','auth_num')->first()->toArray();
        if(empty($infos['auth_name']))
        {
            return $this->auth_sfz($user_id);
        }
        $od = make(OrderSn::class);
        $orderSn = $od->createOrderSN();
        $res = Auth::_shiren_([
            'order_sn'  => $orderSn,
            'name'      => $infos['auth_name'],
            'card'      => $infos['auth_num'],
            'metaInfo'  => $metaInfo,
            'user_id'   => $user_id,
        ]);
        return $this->withResponse('ok',$res);
    }

    /**
     * 查询人脸结果
     * @RequestMapping(path="get_face_info",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Swoole\Exception
     */
    public function get_face_info()
    {

        $user_id    = Context::get('user_id');
        $userInfo     = Context::get('userInfo');
        $CertifyId = $this->request->post('CertifyId',0);
        if(!$CertifyId)
        {
            return $this->withError('缺少参数!');
        }
        //判断id是否重复
        $sd = $this->redis3->sIsMember('auth_all_CertifyId',$CertifyId);
        if($sd)
        {
            return $this->withError('还想来刷？');
        }

        $res = Auth::res_check($CertifyId);

        if(empty($res['body']['Code']))
        {
            return $this->withError('当前实名人数较多，请从新打开APP再试');
        }
        $body = $res['body'];
        if(empty($body['ResultObject']['Passed']))
        {
            //人脸认证失败
            DsUser::query()->where('user_id',$user_id)->update(['auth' => 2,'auth_error' =>$body['Message'],'auth_name' =>'','auth_num' =>''  ]);
            return $this->withError('人脸和身份证不匹配!请从新提交认证!');
        }
        if($body['ResultObject']['Passed'] == 'T')
        {
            //记录人脸token
            //人脸认证成功
            $res2 = DsUser::query()->where('user_id',$user_id)->update(['auth' => 5 ]);
            if($res2)
            {
                $this->redis3->set($user_id.'_CertifyId',$CertifyId);
                $infos = [
                    'type'          => '9',
                    'user_id'       => $user_id,
                    'CertifyId'     => $CertifyId,
                    'shenfenzheng_num'  => $userInfo['auth_num'],
                ];
                $this->yibu($infos,rand(2, 5));
                return $this->withSuccess('人脸扫描成功,请耐心等待审核结果,预计2分钟');
            }else{
                return $this->withError('当前实名人数较多!请从新尝试!');
            }
        }else{
            //记录失败次数
            $this->redis3->incr('auth_shibai_ci_new'.$user_id);
            //人脸认证失败
            DsUser::query()->where('user_id',$user_id)->update(['auth' => 2,'auth_error' =>'人脸和身份证不匹配!请从新提交认证！','auth_name' =>'','auth_num' =>''  ]);
            return $this->withError('人脸认证失败!请从新提交认证!');
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