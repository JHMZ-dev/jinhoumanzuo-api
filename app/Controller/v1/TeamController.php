<?php declare(strict_types=1);

namespace App\Controller\v1;


use App\Controller\XiaoController;
use App\Model\DsUser;
use App\Model\DsUserGroup;
use App\Model\DsUserTaskPack;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Annotation\Middleware;
use App\Middleware\SignatureMiddleware;
use Hyperf\HttpServer\Annotation\Middlewares;
use App\Middleware\UserMiddleware;
use Hyperf\Utils\Context;
use Swoole\Exception;
use Hyperf\RateLimit\Annotation\RateLimit;

/**
 * 团队接口
 * Class CommonController
 * @Middlewares({
 *     @Middleware(SignatureMiddleware::class),
 *     @Middleware(UserMiddleware::class)
 * })
 * @package App\Controller\v1
 * @Controller(prefix="v1/team")
 */
class TeamController extends XiaoController
{

    /**
     * 我的团队顶部
     * @RequestMapping(path="get_info_data_top",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get_info_data_top()
    {
        $user_id    = Context::get('user_id');
        $userInfo    = Context::get('userInfo');

        $data['group']           = $userInfo['group'];//等级
        $data['tuandui_number'] = $this->redis6->sCard($user_id.'_team_user')?$this->redis6->sCard($user_id.'_team_user'):0;//团队总人数
        $data['reg_number'] = $this->redis6->sCard($user_id.'_team_user')?$this->redis6->sCard($user_id.'_team_user'):0;//总注册人数
        $data['shiming_number'] = $this->redis6->sCard($user_id.'_team_user_real')?$this->redis6->sCard($user_id.'_team_user_real'):0;//实名人数

        $data['tiaojian']           = [//目前达到的条件
            'zhitui' => [//直推条件
                'num' => $this->redis5->get($user_id.'_zhi_user')?$this->redis5->get($user_id.'_zhi_user'):0,//直推人数
                'num_all' => $this->redis5->get($user_id.'_zhi_user')?$this->redis5->get($user_id.'_zhi_user'):0,//直推总人数
                'reg_all' => $this->redis5->get($user_id.'_zhi_user_real')?$this->redis5->get($user_id.'_zhi_user_real'):0,//直推总注册人数
            ],
            'tuandui' => [
                'num' => $this->redis5->get($user_id.'_jian_user')?$this->redis5->get($user_id.'_jian_user'):0,//间推人数
                'num_all' => $this->redis5->get($user_id.'_jian_user')?$this->redis5->get($user_id.'_jian_user'):0,//间推总人数
                'reg_all' => $this->redis5->get($user_id.'_jian_user_real')?$this->redis5->get($user_id.'_jian_user_real'):0,//间推总注册人数
            ],
        ];
        $pid_info = DsUser::query()->where('user_id',$userInfo['pid'])->select('avatar','nickname','group','role_id','mobile')
            ->first()->toArray();
        $data['content']['img']           = $pid_info['avatar'];//上级头像
        $data['content']['nickname']           = $pid_info['nickname'];//上级昵称
        $data['content']['user_id']           = $userInfo['pid'];//上级id
        $data['content']['group']           = $pid_info['group'];//上级等级
        $data['content']['is_vip']           = $pid_info['role_id'];//上级会员状态 1是会员 0不是
        $data['content']['mobile']           = $pid_info['mobile'];//上级会员状态 1是会员 0不是
        $data['content']['is_haoyou']           = 0;//上级是否是好友0不是 1是

        return $this->withResponse('ok',$data);
    }


    /**
     * 我的团队
     * @RequestMapping(path="get_info_data",methods="post")
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get_info_data()
    {
        $user_id    = Context::get('user_id');

        //获取团队下的所有用户
        $page = $this->request->post('page',1);
        if($page <= 0)
        {
            $page = 1;
        }
        $keyword    = $this->request->post('keyword','');
        $data['more']           = '0';
        $data['data']           = [];
        if(!empty($keyword))
        {
            if($this->isMobile($keyword))
            {
                //查找
                $id = DsUser::query()->where('mobile',$keyword)->value('user_id');
            }else{
                $id = $keyword;
            }
            if($id)
            {
                $res = $this->redis6->sIsMember($user_id.'_team_user',$id);
                if($res)
                {
                    $info = DsUser::query()->where('user_id',$id)->select('user_id','nickname','mobile','avatar','pid')->get()->toArray();
                }
            }
        }else{
            //获取我的直推
            $info = DsUser::query()->where('pid',$user_id)->select('user_id','nickname','mobile','avatar','pid')
                ->forPage($page,10)
                ->get()->toArray();
            }
        if(!empty($info))
        {
            if(count($info) >= 10)
            {
                $data['more']    = '1';
            }
            $day = date('Y-m-d');
            foreach ($info as $k => $v)
            {
                $info[$k]['mobile2']        = $v['mobile'];
//                $info[$k]['mobile']         = $this->replace_mobile_end($v['mobile']);

                //团队总人数
                $info[$k]['team']       = $this->redis6->sCard($v['user_id'].'_team_user') ? $this->redis6->sCard($v['user_id'].'_team_user')  : 0;
                //团队实名人数
                $info[$k]['team_real']  = $this->redis6->sCard($v['user_id'].'_team_user_real') ? $this->redis6->sCard($v['user_id'].'_team_user_real') : 0;
                $info[$k]['zhitui_number']   =  $this->redis5->get($v['user_id'].'_zhi_user_real')?$this->redis5->get($v['user_id'].'_zhi_user_real'):0;
                $info[$k]['jiantui_number']   =  $this->redis5->get($v['user_id'].'_jian_user_real')?$this->redis5->get($v['user_id'].'_jian_user_real'):0;

                //直推信息
                $user = DsUser::query()->where('user_id',$v['user_id'])->select('nickname','avatar','user_id','mobile')->first();
                $info[$k]['img'] = $user['avatar'];//直推头像

                $info[$k]['is_youxiao'] = 0;//是否有效1有效 0无效
                //判断用户今日是否已领取
                $name = $v['user_id'].'_'.$day.'_app_renwu';
                $namesss = $name.'_status';
                $bao = $this->redis4->get($namesss);
                if($bao == 2)
                {
                    $info[$k]['is_youxiao']  = 1;
                }
                $info[$k]['is_haoyou'] = 0;//是否好友1好友 0不是
//                //邀请人信息
//                $pid_info = DsUser::query()->where('user_id',$userInfo['pid'])->select('user_id','avatar','nickname','group','role_id','mobile')
//                    ->first()->toArray();
//                $info[$k]['yaoqing']['img'] = $pid_info['avatar'];//邀请人头像
//                $info[$k]['yaoqing']['nickname'] = $pid_info['nickname'];//邀请人昵称
//                $info[$k]['yaoqing']['user_id'] = $pid_info['user_id'];//邀请人id
//                $info[$k]['yaoqing']['mobile'] = $pid_info['mobile'];//邀请人id

            }
            $data['data']   = $info;
        }

        return $this->withResponse('ok',$data);
    }


//
//    /**
//     * 获取我的团队
//     * @RequestMapping(path="get_info",methods="post")
//     * @return \Psr\Http\Message\ResponseInterface
//     */
//    public function get_info()
//    {
//
//        $user_id    = Context::get('user_id');
//        $userInfo    = Context::get('userInfo');
//
//
//        $data['team']       = $this->redis6->sCard($user_id.'_team_user') ? $this->redis6->sCard($user_id.'_team_user')  : 0;
//        $data['team_real']  = $this->redis5->get($user_id.'_team_user_real') ? $this->redis5->get($user_id.'_team_user_real') : 0;
//        $data['team_huoyue']       = $this->redis6->sCard($user_id.'_team_is_jihuo') ? $this->redis6->sCard($user_id.'_team_is_jihuo')  : 0;
//
//        //大小区人数
//        $_daqu_id = $this->redis5->get($user_id.'_daqu_id');
//        if($_daqu_id > 0)
//        {
//            $data['daqu_ren'] = $this->redis6->sCard($_daqu_id.'_team_user')+1;
//            $data['xiaoqu_ren'] = $data['team'] - $data['daqu_ren'];
//        }else{
//            $data['daqu_ren']   =  $data['team'];
//            $data['xiaoqu_ren']  =  '0';
//        }
//
//        //大小区实名
//        $_daqu_auth_id = $this->redis5->get($user_id.'_daqu_auth_id');
//        if($_daqu_auth_id > 0)
//        {
//            $data['daqu_real'] = $this->redis5->get($_daqu_auth_id.'_team_user_real');
//            $re = $this->redis5->get($_daqu_auth_id.'_auth');
//            if($re == 1)
//            {
//                $data['daqu_real'] += 1;
//            }
//            $data['xiaoqu_real'] = $data['team_real'] - $data['daqu_real'];
//        }else{
//            $data['daqu_real']  = $data['team_real'];
//            $data['xiaoqu_real']  =  '0';
//        }
//
//        //大小区活跃人数
//        $_daqu_huoyue_user_id = $this->redis5->get($user_id.'_daqu_huoyue_user_id');
//        if($_daqu_huoyue_user_id > 0)
//        {
//            $data['daqu_huoyue'] = $this->redis6->sCard($_daqu_huoyue_user_id.'_team_is_jihuo');
//            $ree = $this->redis5->get($_daqu_huoyue_user_id.'is_jihuo');
//            if($ree == 1)
//            {
//                $data['daqu_huoyue'] +=1;
//            }
//            $data['xiaoqu_huoyue'] = $data['team_huoyue'] - $data['daqu_huoyue'];
//        }else{
//            $data['daqu_huoyue']   =  $data['team_huoyue'];
//            $data['xiaoqu_huoyue']  =  '0';
//        }
//        return $this->withResponse('获取成功',$data);
//    }
//
//    /**
//     * 获取我的团队
//     * @RequestMapping(path="get_info_2",methods="post")
//     * @return \Psr\Http\Message\ResponseInterface
//     */
//    public function get_info_2()
//    {
//
//        $user_id    = Context::get('user_id');
//        $userInfo    = Context::get('userInfo');
//
//
//        $data['team_huoyuezhi']  = $this->redis5->get($user_id.'_team_huoyue') ? $this->redis5->get($user_id.'_team_huoyue')  : 0;
//        $data['team_huoyue']       = $this->redis6->sCard($user_id.'_team_is_jihuo') ? $this->redis6->sCard($user_id.'_team_is_jihuo')  : 0;
//
////        //大小区人数
////        $_daqu_id = $this->redis5->get($user_id.'_daqu_id');
////        if($_daqu_id > 0)
////        {
////            $data['daqu_ren'] = $this->redis6->sCard($_daqu_id.'_team_user')+1;
////            $data['xiaoqu_ren'] = $data['team'] - $data['daqu_ren'];
////        }else{
////            $data['daqu_ren']   =  $data['team'];
////            $data['xiaoqu_ren']  =  '0';
////        }
//
////        //大小区实名
////        $_daqu_auth_id = $this->redis5->get($user_id.'_daqu_auth_id');
////        if($_daqu_auth_id > 0)
////        {
////            $data['daqu_real'] = $this->redis5->get($_daqu_auth_id.'_team_user_real');
////            $re = $this->redis5->get($_daqu_auth_id.'_auth');
////            if($re == 1)
////            {
////                $data['daqu_real'] += 1;
////            }
////            $data['xiaoqu_real'] = $data['team_real'] - $data['daqu_real'];
////        }else{
////            $data['daqu_real']  = $data['team_real'];
////            $data['xiaoqu_real']  =  '0';
////        }
//
//        //大小区活跃人数
//        $_daqu_huoyue_user_id = $this->redis5->get($user_id.'_daqu_huoyue_user_id');
//        if($_daqu_huoyue_user_id > 0)
//        {
//            $daqu_huoyue = $this->redis6->sCard($_daqu_huoyue_user_id.'_team_is_jihuo');
//            $ree = $this->redis5->get($_daqu_huoyue_user_id.'is_jihuo');
//            if($ree == 1)
//            {
//                $daqu_huoyue +=1;
//            }
//            $data['xiaoqu_huoyue'] = $data['team_huoyue'] - $daqu_huoyue;
//        }else{
//            $daqu_huoyue   =  $data['team_huoyue'];
//            $data['xiaoqu_huoyue']  =  '0';
//        }
//
//        //大小区活跃值
//        $_daqu_huoyue_id = $this->redis5->get($user_id.'_daqu_huoyue_id');
//        if($_daqu_huoyue_id > 0)
//        {
//            $daqu_huoyuezhi = $this->redis5->get($_daqu_huoyue_id.'_team_huoyue');
//            $ree = $this->redis5->get($_daqu_huoyue_id.'_huoyue');
//            if($ree >= 0)
//            {
//                $daqu_huoyuezhi += $ree;
//            }
//            $data['xiaoqu_huoyuezhi'] = $data['team_huoyuezhi'] - $daqu_huoyuezhi;
//        }else{
//            $data['xiaoqu_huoyuezhi']  =  '0';
//        }
//
//        $data['zhi_huoyuezhi']  = $this->redis5->get($user_id.'_zhi_huoyue') ? $this->redis5->get($user_id.'_zhi_huoyue')  : 0;
//        $data['zhi_huoyue']       = $this->redis6->sCard($user_id.'_zhi_is_jihuo') ? $this->redis6->sCard($user_id.'_zhi_is_jihuo')  : 0;
//
//        return $this->withResponse('获取成功',$data);
//    }
//
//    /**
//     * 获取团队搜索
//     * @RequestMapping(path="get_team",methods="post")
//     * @return \Psr\Http\Message\ResponseInterface
//     */
//    public function get_team()
//    {
//        $user_id    = Context::get('user_id');
//        $mobile   = $this->request->post("mobile",'');
//        //获取团队下的所有用户
//
//        $page = $this->request->post('page',1);
//        if($page <= 0)
//        {
//            $page = 1;
//        }
//        $data['more']           = '0';
//        $data['data']           = [];
//        if(!empty($mobile))
//        {
//            if($this->isMobile($mobile))
//            {
//                //查找
//                $id = DsUser::query()->where('mobile',$mobile)->value('user_id');
//            }else{
//                $id = $mobile;
//            }
//            if($id)
//            {
//                $res = $this->redis6->sIsMember($user_id.'_team_user',$id);
//                if($res)
//                {
//                    $info = DsUser::query()->where('user_id',$id)->select('user_id','nickname','mobile','auth','avatar','reg_time','group')->first()->toArray();
//                    $info['mobile2']        = $info['mobile'];
//                    $info['mobile']         = $this->replace_mobile_end($info['mobile']);
//                    $info['reg_time']         = $this->replaceTime($info['reg_time']);
//
//                    //团队总人数
//                    $info['team']       = $this->redis6->sCard($info['user_id'].'_team_user') ? $this->redis6->sCard($info['user_id'].'_team_user')  : 0;
//                    //团队实名人数
//                    $info['team_real']  = $this->redis5->get($info['user_id'].'_team_user_real') ? $this->redis5->get($info['user_id'].'_team_user_real') : 0;
//                    //团队活跃人数
//                    $info['team_huoyue']       = $this->redis6->sCard($info['user_id'].'_team_is_jihuo') ? $this->redis6->sCard($info['user_id'].'_team_is_jihuo')  : 0;
//
//
//                    //查询今日是否做任务
//                    $day = date('Y-m-d');
//                    $name = $id.'_'.$day.'_app_renwu';
//                    $namesss = $name.'_status';
//                    $bao = $this->redis4->get($namesss);
//                    if($bao == 2)
//                    {
//                        $info['renwu_type']    = 1;
//                        $info['renwu_cont']    = '今日已经完成任务';
//                    }else{
//                        $info['renwu_type']    = 0;
//                        $tt = DsUser::query()->where('user_id',$id)->value('last_do_time');
//                        $info['renwu_cont']    = '连续'.$this->convert_day($tt).'天未完成任务';
//                    }
//                    $data['data'][0]  = $info;
//                }
//            }
//        }else{
//            //获取我的直推
//            $info = DsUser::query()->where('pid',$user_id)->select('user_id','nickname','mobile','auth','avatar','reg_time','group')
//                ->forPage($page,10)
//                ->get()->toArray();
//            if(!empty($info))
//            {
//                if(count($info) >= 10)
//                {
//                    $data['more']    = '1';
//                }
//                foreach ($info as $k => $v)
//                {
//                    $info[$k]['mobile2']        = $v['mobile'];
//                    $info[$k]['mobile']         = $this->replace_mobile_end($v['mobile']);
//                    $info[$k]['reg_time']         = $this->replaceTime($v['reg_time']);
//
//                    //团队总人数
//                    $info[$k]['team']       = $this->redis6->sCard($v['user_id'].'_team_user') ? $this->redis6->sCard($v['user_id'].'_team_user')  : 0;
//                    //团队实名人数
//                    $info[$k]['team_real']  = $this->redis5->get($v['user_id'].'_team_user_real') ? $this->redis5->get($v['user_id'].'_team_user_real') : 0;
//                    //团队活跃人数
//                    $info[$k]['team_huoyue']       = $this->redis6->sCard($v['user_id'].'_team_is_jihuo') ? $this->redis6->sCard($v['user_id'].'_team_is_jihuo')  : 0;
//
//                    //查询今日是否做任务
//                    $day = date('Y-m-d');
//                    $name = $v['user_id'].'_'.$day.'_app_renwu';
//                    $namesss = $name.'_status';
//                    $bao = $this->redis4->get($namesss);
//                    if($bao == 2)
//                    {
//                        $info[$k]['renwu_type']    = 1;
//                        $info[$k]['renwu_cont']    = '今日已经完成任务';
//                    }else{
//                        $info[$k]['renwu_type']    = 0;
//                        $tt = DsUser::query()->where('user_id',$v['user_id'])->value('last_do_time');
//                        $info[$k]['renwu_cont']    = '连续'.$this->convert_day($tt).'天未完成任务';
//                    }
//                }
//                $data['data']   = $info;
//            }
//        }
//        return $this->withResponse('ok',$data);
//    }
//
//    /**
//     * 获取团队搜索
//     * @RequestMapping(path="get_team_2",methods="post")
//     * @return \Psr\Http\Message\ResponseInterface
//     */
//    public function get_team_2()
//    {
//        $user_id    = Context::get('user_id');
//        $mobile   = $this->request->post("mobile",'');
//        //获取团队下的所有用户
//
//        $page = $this->request->post('page',1);
//        if($page <= 0)
//        {
//            $page = 1;
//        }
//        $data['more']           = '0';
//        $data['data']           = [];
//        if(!empty($mobile))
//        {
//            if($this->isMobile($mobile))
//            {
//                //查找
//                $id = DsUser::query()->where('mobile',$mobile)->value('user_id');
//            }else{
//                $id = $mobile;
//            }
//            if($id)
//            {
//                $res = $this->redis6->sIsMember($user_id.'_team_user',$id);
//                if($res)
//                {
//                    $info = DsUser::query()->where('user_id',$id)->select('user_id','nickname','mobile','auth','avatar','reg_time','group')->first()->toArray();
//                    $info['mobile2']        = $info['mobile'];
//                    $info['mobile']         = $this->replace_mobile_end($info['mobile']);
//                    $info['reg_time']         = $this->replaceTime($info['reg_time']);
//
//                    //团队总人数
//                    $info['team']       = $this->redis6->sCard($info['user_id'].'_team_user') ? $this->redis6->sCard($info['user_id'].'_team_user')  : 0;
//                    //团队活跃人数
//                    $info['team_huoyue']       = $this->redis6->sCard($info['user_id'].'_team_is_jihuo') ? $this->redis6->sCard($info['user_id'].'_team_is_jihuo')  : 0;
//                    //团队贡献值
//                    $info['team_huoyuezhi']       = $this->redis5->get($info['user_id'].'_team_huoyue') ? $this->redis5->get($info['user_id'].'_team_huoyue')  : 0;
//                    //个人贡献值
//                    $info['geren_huoyuezhi']       = $this->redis5->get($info['user_id'].'_huoyue') ? $this->redis5->get($info['user_id'].'_huoyue')  : 0;
//
//
//                    //查询今日是否做任务
//                    $day = date('Y-m-d');
//                    $name = $id.'_'.$day.'_app_renwu';
//                    $namesss = $name.'_status';
//                    $bao = $this->redis4->get($namesss);
//                    if($bao == 2)
//                    {
//                        $info['renwu_type']    = 1;
//                        $info['renwu_cont']    = '今日已经完成任务';
//                    }else{
//                        $info['renwu_type']    = 0;
//                        $tt = DsUser::query()->where('user_id',$id)->value('last_do_time');
//                        $info['renwu_cont']    = '连续'.$this->convert_day($tt).'天未完成任务';
//                    }
//                    $data['data'][0]  = $info;
//                }
//            }
//        }else{
//            //获取我的直推
//            $info = DsUser::query()->where('pid',$user_id)->select('user_id','nickname','mobile','auth','avatar','reg_time','group')
//                ->forPage($page,10)
//                ->get()->toArray();
//            if(!empty($info))
//            {
//                if(count($info) >= 10)
//                {
//                    $data['more']    = '1';
//                }
//                foreach ($info as $k => $v)
//                {
//                    $info[$k]['mobile2']        = $v['mobile'];
//                    $info[$k]['mobile']         = $this->replace_mobile_end($v['mobile']);
//                    $info[$k]['reg_time']         = $this->replaceTime($v['reg_time']);
//
//                    //团队总人数
//                    $info[$k]['team']       = $this->redis6->sCard($v['user_id'].'_team_user') ? $this->redis6->sCard($v['user_id'].'_team_user')  : 0;
//                    //团队活跃人数
//                    $info[$k]['team_huoyue']       = $this->redis6->sCard($v['user_id'].'_team_is_jihuo') ? $this->redis6->sCard($v['user_id'].'_team_is_jihuo')  : 0;
//                    //团队贡献值
//                    $info[$k]['team_huoyuezhi']       = $this->redis5->get($v['user_id'].'_team_huoyue') ? $this->redis5->get($v['user_id'].'_team_huoyue')  : 0;
//                    //个人贡献值
//                    $info[$k]['geren_huoyuezhi']       = $this->redis5->get($v['user_id'].'_huoyue') ? $this->redis5->get($v['user_id'].'_huoyue')  : 0;
//                    //查询今日是否做任务
//                    $day = date('Y-m-d');
//                    $name = $v['user_id'].'_'.$day.'_app_renwu';
//                    $namesss = $name.'_status';
//                    $bao = $this->redis4->get($namesss);
//                    if($bao == 2)
//                    {
//                        $info[$k]['renwu_type']    = 1;
//                        $info[$k]['renwu_cont']    = '今日已经完成任务';
//                    }else{
//                        $info[$k]['renwu_type']    = 0;
//                        $tt = DsUser::query()->where('user_id',$v['user_id'])->value('last_do_time');
//                        $info[$k]['renwu_cont']    = '连续'.$this->convert_day($tt).'天未完成任务';
//                    }
//                }
//                $data['data']   = $info;
//            }
//        }
//        return $this->withResponse('ok',$data);
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