<?php declare(strict_types=1);

namespace App\Controller\v1;


use App\Controller\XiaoController;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Annotation\Middleware;
use App\Middleware\WebMiddleware;
use Swoole\Exception;

/**
 * 电子版
 * Class CommonController
 * @Middleware(WebMiddleware::class)
 * @package App\Controller\v1
 * @Controller(prefix="v1/film")
 */
class DianZiBanController extends XiaoController
{

    /**
     * 获取影票数据
     * @RequestMapping(path="get_piaofang",methods="post")
     * @throws Exception
     */
    public function get_piaofang()
    {
        $dianziban_piaofang = $this->redis0->get('dianziban_piaofang');
        $dianziban_piaofang = json_decode($dianziban_piaofang,true);
        return $this->withResponse('ok',$dianziban_piaofang);
    }

    /**
     * 获取院线票房榜
     * @RequestMapping(path="get_yxpf",methods="post")
     * @throws Exception
     */
    public function get_yxpf()
    {
        $dianziban_yxpf = $this->redis0->get('dianziban_yxpf');
        $dianziban_yxpf = json_decode($dianziban_yxpf,true);
        return $this->withResponse('ok',$dianziban_yxpf);
    }

    /**
     * 获取省份票房榜
     * @RequestMapping(path="get_sfpf",methods="post")
     * @throws Exception
     */
    public function get_sfpf()
    {
        $dianziban_sfpf = $this->redis0->get('dianziban_sfpf');
        $dianziban_sfpf = json_decode($dianziban_sfpf,true);
        return $this->withResponse('ok',$dianziban_sfpf);
    }

    /**
     * 获取排片
     * @RequestMapping(path="get_paipian",methods="post")
     * @throws Exception
     */
    public function get_paipian()
    {
        $dianziban_paipian = $this->redis0->get('dianziban_paipian');
        $dianziban_paipian = json_decode($dianziban_paipian,true);
        return $this->withResponse('ok',$dianziban_paipian);
    }
}