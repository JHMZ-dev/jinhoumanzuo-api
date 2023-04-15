<?php

declare(strict_types=1);

namespace App\Controller\v1;

use App\Controller\XiaoController;
use Hyperf\Utils\Context;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;

use Hyperf\HttpServer\Annotation\AutoController;

use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Annotation\Middlewares;
use App\Middleware\SignatureMiddleware;
use App\Middleware\UserMiddleware;
/**
 * @AutoController()
 * @Middlewares({
 *     @Middleware(SignatureMiddleware::class),
 *     @Middleware(UserMiddleware::class)
 * })
 */
class LaquxiaoshuoController extends XiaoController
{
    protected $url_xs = 'https://api.pingcc.cn';

    /**小说搜索
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get_xs_so()
    {
        $option = $this->request->post('option','');//选择搜索项 标题：title ， 作者 ：author ，分类：comicType
        $key = $this->request->post('keyword','');//搜索关键字
        $from = $this->request->post('page',0);//当前页数，留空默认1

        if(empty($option) || empty($key)){
            return $this->withError('类型不能为空');
        }

        $user_id = Context::get('user_id');

        $this->check_often($user_id);
        $this->start_often($user_id);

        $url = $this->url_xs.'/fiction/search/'.$option.'/'.$key.'/'.$from;
        $info = $this->http->get($url)->getBody()->getContents();
        if(empty($info))
        {
            return $this->withError('当前获取人数较多，请稍后再试');
        }else{
            $re = json_decode($info,true);
            if($re['code'] == 0){
                return $this->withSuccess('ok',200,$re['data']);
            }else{
                return $this->withError('当前获取人数较多，请稍后再试');
            }
        }
    }

    /**获取小说章节
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get_xs_zhangjie()
    {
        $fictionId = $this->request->post('fictionId','');//通过小说搜索API获取到fictionId

        if(empty($fictionId)){
            return $this->withError('fictionId不能为空');
        }

        $user_id = Context::get('user_id');

        $this->check_often($user_id);
        $this->start_often($user_id);

        $url = $this->url_xs.'/fictionChapter/search/'.$fictionId;
        $info = $this->http->get($url)->getBody()->getContents();
        if(empty($info))
        {
            return $this->withError('当前获取人数较多，请稍后再试');
        }else{
            $re = json_decode($info,true);
            if($re['code'] == 0){
                return $this->withSuccess('ok',200,$re['data']);
            }else{
                return $this->withError($re['msg']);
            }
        }
    }

    /**获取小说内容
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get_xs_content()
    {
        $chapterId = $this->request->post('chapterId','');//通过小说章节API获取chapterId

        if(empty($chapterId)){
            return $this->withError('chapterId 不能为空');
        }

        $user_id = Context::get('user_id');

        $this->check_often($user_id);
        $this->start_often($user_id);

        $url = $this->url_xs.'/fictionContent/search/'.$chapterId;
        $info = $this->http->get($url)->getBody()->getContents();
        if(empty($info))
        {
            return $this->withError('当前获取人数较多，请稍后再试');
        }else{
            $re = json_decode($info,true);
            if($re['code'] == 0){
                return $this->withSuccess('ok',200,$re['data']);
            }else{
                return $this->withError($re['msg']);
            }
        }
    }

    /**获取漫画搜索
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get_manhua_so()
    {
        $option = $this->request->post('option','');//选择搜索项 标题：title ， 作者 ：author ，分类：comicType
        $key = $this->request->post('keyword','');//搜索关键字
        $from = $this->request->post('page',0);//当前页数，留空默认1

        if(empty($option) || empty($key)){
            return $this->withError('类型不能为空');
        }

        $user_id = Context::get('user_id');

        $this->check_often($user_id);
        $this->start_often($user_id);

        $url = $this->url_xs.'/comic/search/'.$option.'/'.$key.'/'.$from;
        $info = $this->http->get($url)->getBody()->getContents();
        if(empty($info))
        {
            return $this->withError('当前获取人数较多，请稍后再试');
        }else{
            $re = json_decode($info,true);
            if($re['code'] == 0){
                return $this->withSuccess('ok',200,$re['data']);
            }else{
                return $this->withError('当前获取人数较多，请稍后再试');
            }
        }
    }


    /**获取漫画章节
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get_manhua_zhangjie()
    {
        $comicId = $this->request->post('comicId','');//通过漫画搜索API获取到comicId

        if(empty($comicId)){
            return $this->withError('参数不能为空');
        }

        $user_id = Context::get('user_id');

        $this->check_often($user_id);
        $this->start_often($user_id);
        $url = $this->url_xs.'/comicChapter/search/'.$comicId;
        $info = $this->http->get($url)->getBody()->getContents();
        if(empty($info))
        {
            return $this->withError('当前获取人数较多，请稍后再试');
        }else{
            $re = json_decode($info,true);
            if($re['code'] == 0){
                return $this->withSuccess('ok',200,$re['data']);
            }else{
                return $this->withError($re['msg']);
            }
        }
    }

    /**获取漫画内容
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get_manhua_content()
    {
        $chapterId = $this->request->post('chapterId','');//通过小说搜索API获取到fictionId

        if(empty($chapterId)){
            return $this->withError('chapterId 不能为空');
        }

        $user_id = Context::get('user_id');

        $this->check_often($user_id);
        $this->start_often($user_id);

        $url = $this->url_xs.'/comicContent/search/'.$chapterId;
        $info = $this->http->get($url)->getBody()->getContents();
        if(empty($info))
        {
            return $this->withError('当前获取人数较多，请稍后再试');
        }else{
            $re = json_decode($info,true);
            if($re['code'] == 0){
                return $this->withSuccess('ok',200,$re['data']);
            }else{
                return $this->withError($re['msg']);
            }
        }
    }

    /**获取视频搜索
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get_video_so()
    {
        $option = $this->request->post('option','');//选择搜索项 标题：title ， 导演 ：director，主演：actor，地区：region，上映：releaseTime，分类：videoType
        $key = $this->request->post('keyword','');//搜索关键字
        $from = $this->request->post('page',0);//当前页数，留空默认1

        if(empty($option) || empty($key)){
            return $this->withError('类型不能为空');
        }

        $user_id = Context::get('user_id');

        $this->check_often($user_id);
        $this->start_often($user_id);

        $url = $this->url_xs.'/video/search/'.$option.'/'.$key.'/'.$from;
        $info = $this->http->get($url)->getBody()->getContents();
        if(empty($info))
        {
            return $this->withError('当前获取人数较多，请稍后再试');
        }else{
            $re = json_decode($info,true);
            if($re['code'] == 0){
                return $this->withSuccess('ok',200,$re['data']);
            }else{
                return $this->withError('当前获取人数较多，请稍后再试');
            }
        }
    }

    /**获取视频章节
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get_video_zhangjie()
    {
        $videoId = $this->request->post('videoId','');//通过视频搜索API获取到videoId

        if(empty($videoId)){
            return $this->withError('参数不能为空');
        }

        $user_id = Context::get('user_id');

        $this->check_often($user_id);
        $this->start_often($user_id);

        $url = $this->url_xs.'/videoChapter/search/'.$videoId;
        $info = $this->http->get($url)->getBody()->getContents();
        if(empty($info))
        {
            return $this->withError('当前获取人数较多，请稍后再试');
        }else{
            $re = json_decode($info,true);
            if($re['code'] == 0){
                return $this->withSuccess('ok',200,$re['data']);
            }else{
                return $this->withError($re['msg']);
            }
        }
    }


}
