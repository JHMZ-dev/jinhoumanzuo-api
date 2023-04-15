<?php declare(strict_types=1);

namespace App\Controller\v1;

use App\Controller\XiaoController;
use App\Model\DsUser;
use App\Model\DsUserLine;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Annotation\Middleware;
use App\Middleware\UserMiddleware;
use Hyperf\HttpServer\Annotation\Middlewares;
use App\Middleware\SignatureMiddleware;
use Hyperf\Utils\Context;
/**
 * 我的邀请接口
 * Class InviteController
 * @Middlewares({
 *     @Middleware(SignatureMiddleware::class),
 *     @Middleware(UserMiddleware::class)
 * })
 * @package App\Controller\v1
 * @Controller(prefix="v1/invite")
 */
class InviteController extends XiaoController
{

    /**
     * 邀请码
     * @RequestMapping(path="get_code",methods="post")
     */
    public function get_code()
    {
        $type = $this->request->post('type',1);
        if($type == 1)
        {
            return $this->get_extend();
        }else{
            return $this->get_paixian();
        }
    }

    /**
     * 邀请码
     * @RequestMapping(path="get_extend",methods="post")
     */
    public function get_extend()
    {
        $user_id      = Context::get('user_id');
        $userInfo      = Context::get('userInfo');

        $web_url = $this->redis0->get('reg_url');

        if(empty($userInfo['ma_zhitui'])){
            $info = [
                'type' => '42',
                'user_id' => $user_id,
            ];
            $this->yibu($info);
            return $this->withError('正在为你生成二维码，请稍后！');
        }
        $user_id = strtoupper($userInfo['ma_zhitui']);
        //大->小 strtolower 小->大 strtoupper
        $data['user_id']    = $user_id;
        $data['url']     = $web_url.'/#/register?line=0&user_id='.$user_id;
        return $this->withResponse('获取成功',$data);
    }

    /**
     * 排线码
     * @RequestMapping(path="get_paixian",methods="post")
     */
    public function get_paixian()
    {
        $user_id      = Context::get('user_id');
        $userInfo      = Context::get('userInfo');

        $web_url = $this->redis0->get('reg_url');

        if(empty($userInfo['ma_paixian'])){
            $info = [
                'type' => '42',
                'user_id' => $user_id,
            ];
            $this->yibu($info);
            return $this->withError('正在为你生成二维码，请稍后！');
        }
        $user_id = strtoupper($userInfo['ma_paixian']);
        //大->小 strtolower 小->大 strtoupper
        $data['user_id']    = $user_id;
        $data['url']     = $web_url.'/#/register?line=1&user_id='.$user_id;
        return $this->withResponse('获取成功',$data);
    }

//    /**
//     * 获取红包初始化
//     * @RequestMapping(path="get_honbao_index",methods="post")
//     */
//    public function get_honbao_index()
//    {
//        $user_id      = Context::get('user_id');
//        $data['ren'] = $this->redis3->get('zhitui_fanyong_ren'.$user_id)?$this->redis3->get('zhitui_fanyong_ren'.$user_id):0;
//        $data['money'] = round($this->redis3->get('zhitui_fanyong'.$user_id),2);
//        return $this->withResponse('获取成功',$data);
//    }
//
//    /**
//     * 分享好友人数明细
//     * @RequestMapping(path="get_haoyou_log",methods="post")
//     */
//    public function get_haoyou_log()
//    {
//        $user_id      = Context::get('user_id');
//        $page = $this->request->post('page',1); //页码
//        if($page <= 0)
//        {
//            $page = 1;
//        }
//        $data['more']       = '0';
//        $data['data']       = [];
//        $res = DsUserInvite::query()
//            ->leftJoin('ds_user','ds_user.user_id','=','ds_user_invite.son_id')
//            ->where('ds_user_invite.user_id',$user_id)
//            ->select('son_id','time','mobile')
//            ->orderByDesc('user_invite_id')->forPage($page,10)->get()->toArray();
//        if(!empty($res))
//        {
//            if(count($res) == 10)
//            {
//                $data['more']       = '1';
//            }
//            foreach ($res as $k => $v)
//            {
//                $res[$k]['mobile'] = $this->get_mobile_end_4($v['mobile']);
//                $res[$k]['time'] = $this->replaceTime($v['time']);
//            }
//            $data['data']   = $res;
//        }
//        return $this->withResponse('获取成功',$data);
//    }

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