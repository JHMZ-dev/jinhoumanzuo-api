<?php

declare(strict_types=1);

namespace App\Controller\v1;

use App\Controller\XiaoController;
use App\Model\DsCinema;
use App\Model\DsCinemaCity;
use App\Model\DsCinemaCityChild;
use App\Model\DsCinemaSuozuo;
use App\Model\DsCinemaUserOrder;
use App\Model\DsCinemaVideo;
use App\Model\DsCinemaPaiqi;
use App\Model\DsCity;
use App\Model\DsErrorLog;
use App\Model\DsUser;
use App\Model\DsUserTongzheng;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;

use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Annotation\Controller;

use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Annotation\Middlewares;
use App\Middleware\SignatureMiddleware;
use App\Middleware\UserMiddleware;
use Hyperf\Utils\Context;

/**
 * @package App\Controller\v1
 * @Controller(prefix="v1/callbacklaquyiqida")
 */
class CallbacklaquYiqida extends XiaoController
{

    /**
     * 回调  亿奇达
     * @RequestMapping(path="callbacklaqu_yiqida",methods="post")
     * @return string
     */
    public function callbacklaqu_yiqida()
    {
        var_dump('yiqida回调');
        var_dump(date('Y-m-d H:i:s',time()));
        $p = $this->request->post();
        var_dump($p);

        return 'ok';
    }

    //退
    protected function tui(){

    }

}
