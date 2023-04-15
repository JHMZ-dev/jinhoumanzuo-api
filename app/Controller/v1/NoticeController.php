<?php declare(strict_types=1);

namespace App\Controller\v1;

use App\Controller\XiaoController;
use App\Model\DsNotice;
use App\Model\DsNoticeUser;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Annotation\Middleware;
use App\Middleware\UserMiddleware;
use Hyperf\HttpServer\Annotation\Middlewares;
use App\Middleware\SignatureMiddleware;
use Hyperf\Utils\Context;
/**
 * 通知接口
 * Class NoticeController
 * @Middlewares({
 *     @Middleware(SignatureMiddleware::class),
 *     @Middleware(UserMiddleware::class)
 * })
 * @package App\Controller\v1
 * @Controller(prefix="v1/notice")
 */
class NoticeController extends XiaoController
{
    /**
     * 获取一条通知
     * @RequestMapping(path="get_one_notice",methods="post,get")
     */
    public function get_one_notice()
    {
        $user_id  = Context::get('user_id');
        $res = DsNotice::query()->orderByDesc('notice_id')->first();
        $data = [
            'notice_id' => 0,
            'cont'      => '',
            'img'       => '',
            'img_url'   => '',
            'type'      => 0,
        ];
        if(!empty($res))
        {
            $res = $res->toArray();
            $data['cont']       = $res['cont'];
            $data['notice_id']  = $res['notice_id'];
            $data['img']      = $res['img'];
            $asd = DsNoticeUser::query()->where(['notice_id' => $res['notice_id'],'user_id' => $user_id])->value('notice_user_id');
            if($asd)
            {
                $data['type']    = 1;
            }else{
                $data['type']    = 0;
            }
        }
        return $this->withResponse('获取成功',$data);
    }

    /**
     * 获取通知
     * @RequestMapping(path="get_notice",methods="post,get")
     */
    public function get_notice()
    {
        $user_id  = Context::get('user_id');
        $page     = $this->request->post('page',1);
        if($page <= 0)
        {
            $page = 1;
        }
        $data['more']           = '0';
        $data['data']           = [];
        $res = DsNotice::query()->orderByDesc('notice_id')->forPage($page,10)->get()->toArray();
        if(!empty($res))
        {
            //查找是否还有下页数据
            if(count($res) == 10)
            {
                $data['more'] = '1';
            }
            foreach ($res as $k => $v)
            {
                $res[$k]['time'] = $this->replaceTime($v['time']);
                $asd = DsNoticeUser::query()->where(['notice_id' => $v['notice_id'],'user_id' => $user_id])->first();
                if(empty($asd))
                {
                    $res[$k]['type'] = '0';
                }else{
                    $res[$k]['type'] = '1';
                }
            }
            $data['data']           = $res;
        }
        return $this->withResponse('获取成功',$data);
    }

    /**
     * 观看通知
     * @RequestMapping(path="watch_notice",methods="post")
     */
    public function watch_notice()
    {
        $user_id      = Context::get('user_id');
        $notice_id     = $this->request->post('notice_id',0);
        $sd = DsNotice::query()->where('notice_id',$notice_id)->first();
        if(empty($sd))
        {
            return $this->withError('该条通知不存在！');
        }
        $ssvv = DsNoticeUser::query()->where(['notice_id' => $notice_id,'user_id' => $user_id])->first();
        if(!empty($ssvv))
        {
            return $this->withSuccess('操作成功');
        }
        //写入数据
        $data = [
            'notice_id' => $notice_id,
            'user_id' => $user_id,
            'watch_time' => time(),
        ];
        $res = DsNoticeUser::query()->insert($data);
        if($res)
        {
            return $this->withSuccess('操作成功');
        }else{
            return $this->withError('您的操作过于频繁,请稍候再试');
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