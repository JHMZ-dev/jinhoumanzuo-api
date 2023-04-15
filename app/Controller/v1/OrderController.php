<?php declare(strict_types=1);

namespace App\Controller\v1;

use App\Controller\async\OrderSn;
use App\Controller\XiaoController;
use App\Model\DsOrder;
use App\Service\Pay\Alipay\Pay;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Annotation\Middleware;
use App\Middleware\UserMiddleware;
use Hyperf\HttpServer\Annotation\Middlewares;
use App\Middleware\SignatureMiddleware;
use Hyperf\Utils\Context;
use Hyperf\Di\Annotation\Inject;
/**
 * 订单接口
 * Class OrderController
 * @Middlewares({
 *     @Middleware(SignatureMiddleware::class),
 *     @Middleware(UserMiddleware::class)
 * })
 * @package App\Controller\v1
 * @Controller(prefix="v1/order")
 */
class OrderController extends XiaoController
{

    /**
     * 检测订单是否支付
     * @RequestMapping(path="check_order_sn",methods="post")
     */
    public function check_order_sn()
    {
        $order_sn = $this->request->post('order_sn','');
        if(empty($order_sn))
        {
            return $this->withError('还未支付');
        }
        sleep(1);
        $order_status = DsOrder::query()->where('order_sn',$order_sn)->value('order_status');
        if($order_status == '1')
        {
            return $this->withSuccess('支付成功');
        }else{
            return $this->withError('还未支付');
        }
    }
}