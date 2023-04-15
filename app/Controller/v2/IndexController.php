<?php declare(strict_types=1);

namespace App\Controller\v2;

use App\Controller\XiaoController;
use App\Model\DsCity;
use App\Model\DsNotice;
use App\Model\DsNoticeUser;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Annotation\Middleware;
use App\Middleware\UserMiddleware;
use Hyperf\HttpServer\Annotation\Middlewares;
use App\Middleware\SignatureMiddleware;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Context;
use Hyperf\RateLimit\Annotation\RateLimit;
use App\Middleware\AsyncMiddleware;
/**
 * 主页接口
 * Class UserController
 * @Middleware(SignatureMiddleware::class)
 * @package App\Controller\v2
 * @Controller(prefix="v2/index")
 */
class IndexController extends XiaoController
{
    /**
     *
     * 获取首页信息
     * @RequestMapping(path="get_info",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get_info()
    {

        $user_id = Context::get('user_id',0);

        //获取banbner图
        $data['logos'] = [];
        $get_logos = $this->redis0->get('get_index_banner_data');
        if(!empty($get_logos)){
            $data['logos'] = json_decode($get_logos,true);
        }

        $data['notice'] = DsNotice::query()->limit('6')->orderByDesc('notice_id')->select()->get()->toArray();

        //检查用户是否有未读
        $is_read = 1;
        if(!empty($data['notice'])){
            if(is_array($data['notice'])){
                $re = DsNoticeUser::query()->where('user_id',$user_id)->where('notice_id',$data['notice'][0]['notice_id'])->value('notice_id');
                if(empty($re)){
                    $is_read = 0;
                }
            }
        }

        $data['is_read'] = $is_read;
        $data['get_jishi_url'] = $this->redis0->get('get_jishi_url').'?token='.Context::get('token');
        $data['sy_nav_yx_url'] = $this->redis0->get('sy_nav_yx_url')?$this->redis0->get('sy_nav_yx_url'):'';

        return $this->withResponse('ok',$data);
    }

    /**
     * 获取城市详细
     * @RequestMapping(path="get_city_data",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get_city_data()
    {
        $name = $this->request->post('name',0);
        $data = [];
        if(!empty($name)){
            if(is_numeric($name)){
                $data = DsCity::query()->where('city_id',$name)
                    ->first();
                if(!empty($data)){
                    $data =   $data->toArray();
                    $data['ppid'] = 0;
                    if($data['level'] == '3'){
                        //返回省 市 id
                        $dingji = DsCity::query()->where('city_id',$data['pid'])->value('pid');
                        if(!empty($dingji)){
                            $data['ppid'] = $dingji;
                        }
                    }
                }
            }else{
                $data = DsCity::query()->where('name','like',$name.'%')
                    ->orderByDesc('level')
                    ->first();
                $data =   $data->toArray();
                $data['ppid'] = 0;
                if($data['level'] == '3'){
                    //返回省 市 id
                    $dingji = DsCity::query()->where('city_id',$data['pid'])->value('pid');
                    if(!empty($dingji)){
                        $data['ppid'] = $dingji;
                    }
                }
            }
        }
        return $this->withSuccess('ok',200,$data);
    }


}