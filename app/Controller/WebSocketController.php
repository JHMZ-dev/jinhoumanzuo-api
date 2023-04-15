<?php
declare(strict_types=1);

namespace App\Controller;

use App\Controller\async\OrderSn;
use App\Job\Async;
use App\Model\DsUser;
use App\Model\DsUserChat;
use App\Model\DsUserChatCont;
use App\Model\DsUserChatGroup;
use App\Model\DsUserChatList;
use App\Model\DsUserToken;
use Hyperf\AsyncQueue\Driver\DriverFactory;
use Hyperf\Contract\OnCloseInterface;
use Hyperf\Contract\OnMessageInterface;
use Hyperf\Contract\OnOpenInterface;
use Hyperf\Redis\RedisFactory;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Context;
use Swoole\Exception;
use Swoole\Http\Request;
use Swoole\Server;
use Swoole\Websocket\Frame;
use Swoole\WebSocket\Server as WebSocketServer;

class WebSocketController implements OnMessageInterface, OnOpenInterface, OnCloseInterface
{
    protected $redis0;
    protected $redis1;
    protected $redis2;
    protected $redis5;
    protected $redis6;
    protected $container;
    public function __construct()
    {
        $this->container = ApplicationContext::getContainer();
        $this->redis0 = ApplicationContext::getContainer()->get(RedisFactory::class)->get('db0');
        $this->redis1 = ApplicationContext::getContainer()->get(RedisFactory::class)->get('db1');
        $this->redis2 = ApplicationContext::getContainer()->get(RedisFactory::class)->get('db2');
        $this->redis5 = ApplicationContext::getContainer()->get(RedisFactory::class)->get('db5');
        $this->redis6 = ApplicationContext::getContainer()->get(RedisFactory::class)->get('db6');
    }

    /**
     * 返回失败信息
     * @param $msg
     * @param int $code
     * @return false|string
     */
    protected function return_error($msg,$code = 10001)
    {
        $message = [
            'code' => $code,
            'msg'   => $msg
        ];
        return json_encode($message);
    }

    /**
     * 返回成功信息
     * @param $msg
     * @param int $code
     * @return false|string
     */
    protected function return_success($msg,$code = 200)
    {
        $message = [
            'code' => $code,
            'msg'  => $msg,
            'result' => []
        ];
        return json_encode($message);
    }
    /**
     * 返回带数据的成功信息
     * @param $msg
     * @param array $data
     * @param int $code
     * @return false|string
     */
    protected function return_success_data($msg,array $data,$code = 200)
    {
        $message = [
            'code'   => $code,
            'msg'    => $msg,
            'result' => $data,
        ];
        return json_encode($message);
    }

    public function onMessage(WebSocketServer $server, Frame $frame): void
    {
        //判断是否在更新
//        $ws_status = $this->redis0->get('ws_status');
//        if($ws_status == 1)
//        {
//            $ws_msg = $this->redis0->get('ws_msg');
//            $server->push($frame->fd, $this->return_error($ws_msg));
//            return;
//        }
        //判断是否是json格式
        $message = json_decode($frame->data, true);
        if(empty($message))
        {
            $server->push($frame->fd, $this->return_error('请填写正确内容'));
        }
        if(empty($message['type']))
        {
            $server->push($frame->fd, $this->return_error('请选择类型'));
        }else{
            $message_type = $message['type'];
            $user_id = $this->redis6->get('fd_'.$frame->fd);
            if(!$user_id)
            {
                $server->push($frame->fd, $this->return_error('请先登录！'));
                $server->close($frame->fd);
            }
            $user_id = (int)$user_id;
            switch($message_type)
            {
                case 'ping':
                    //绑定用户id和fd
                    $this->add_fd_uid($user_id,$frame->fd);
                    $server->push($frame->fd, $this->return_success('连接正常',105));
                    break;
                case 'one_chat':
                    //聊天
                    $to_id = $message['data']['to_id'];
                    if(empty($to_id))
                    {
                        $server->push((int)$frame->fd, $this->return_error('对方不存在！'));
                        return;
                    }
                    //判断对方是否存在
                    $to_id = DsUser::query()->where('user_id',$to_id)->value('user_id');
                    if(!$to_id)
                    {
                        $server->push((int)$frame->fd, $this->return_error('对方不存在!'));
                        return;
                    }
                    if($user_id == $to_id)
                    {
                        $server->push((int)$frame->fd, $this->return_error('你不能发送给自己!'));
                        return;
                    }
                    //判断是否是好友
                    $chat_list_id = DsUserChatList::query()->where('user_id',$user_id)->where('to_id',$to_id)
                        ->value('chat_list_id');
                    if(!$chat_list_id)
                    {
                        $server->push((int)$frame->fd, $this->return_error('对方还不是你好友! 请先添加好友'));
                        return;
                    }
                    $room_id = $message['data']['room_id'];
                    if(empty($room_id))
                    {
                        $server->push((int)$frame->fd, $this->return_error('房间不存在！'));
                        return;
                    }
                    //判断房间号是否存在
                    $room = DsUserChatList::creat_room($user_id,$to_id);
                    if($room_id != $room)
                    {
                        $server->push((int)$frame->fd, $this->return_error('房间不存在！'));
                        return;
                    }
                    $to_id          = (int)$to_id;
                    $content        = empty($message['data']['content'])?'':$message['data']['content'];
                    $cont_type      = empty($message['data']['cont_type'])?'':$message['data']['cont_type'];
                    if(empty($content) || empty($cont_type))
                    {
                        $server->push((int)$frame->fd, $this->return_error('缺少发送内容!'));
                        return;
                    }
                    if($cont_type == 5)
                    {
                        $idvv = DsUser::query()->where('user_id',$content)->select('user_id','nickname','avatar')->first();
                        if(empty($idvv))
                        {
                            $server->push((int)$frame->fd, $this->return_error('你选择的名片不存在!'));
                            return;
                        }
                        $idvv = $idvv->toArray();
                    }
                    $time = time();
                    $user_info = DsUser::query()->where('user_id',$user_id)->select('user_id','nickname','avatar')->first()->toArray();
                    //添加到数据库
                    $data = [
                        'room_id'   => $room_id,
                        'form_id'   => $user_id,
                        'to_id'     => $to_id,
                        'content'   => $content,
                        'cont_type' => $cont_type,
                        'time'      => $time,
                        'day'       => strtotime(date('Y-m-d')),
                        'type'      => 1,
                    ];
                    $chat_cont_id = DsUserChatCont::query()->insertGetId($data);
                    if($chat_cont_id)
                    {
                        //添加消息列表
                        $this->add_chat($user_id,$to_id,1);
                        $data['name'] = $user_info['nickname'];
                        $data['img'] = $user_info['avatar'];
                        $data['time_stamp'] = $time;
                        $data['time'] = date("Y-m-d H:i:s", $time);
                        unset($data['day']);
                        $data['chat_cont_id'] = $chat_cont_id;
                        $server->push($frame->fd, $this->return_success_data('发送成功',$data,501));
                        //判断对方是否在线
                        $fd = $this->redis6->get($to_id.'_fd');
                        //推送给指定用户
                        if($fd > 0)
                        {
                            if($cont_type != 5)
                            {
                                $server->push((int)$fd, $this->return_success_data('收到消息',$data,501));
                            }else{
                                $data['mp_id'] = $idvv['user_id'];
                                $data['mp_name'] = $idvv['nickname'];
                                $data['mp_img'] = $idvv['avatar'];
                                $server->push((int)$fd, $this->return_success_data('收到消息',$data,501));
                            }
                        }
                    }
                    break;
                case 'one_revoke_chat':
                    //撤回消息
                    $chat_cont_id = empty($message['data']['chat_cont_id'])? 0:$message['data']['chat_cont_id'];
                    //查找该消息是否存在
                    $chat_info = DsUserChatCont::query()->where('chat_cont_id',$chat_cont_id)
                        ->where('form_id',$user_id)
                        ->select('time','to_id','type')->first();
                    if(empty($chat_info))
                    {
                        $server->push($frame->fd, $this->return_error('消息不存在'));
                        return;
                    }
                    $chat_info = $chat_info->toArray();
                    //判断3分钟
                    if(time()-$chat_info['time'] >= 180)
                    {
                        $server->push($frame->fd, $this->return_error('无法撤回超过3分钟的内容!'));
                        return;
                    }
                    $res = DsUserChatCont::query()->where('chat_cont_id',$chat_cont_id)->update(['status' => 0]);
                    if(!$res)
                    {
                        $server->push($frame->fd, $this->return_error('服务器繁忙,请重试'));
                        return;
                    }
                    $data = [
                        'chat_cont_id' => $chat_cont_id
                    ];
                    $server->push($frame->fd, $this->return_success_data('撤回消息操作成功',$data,601));
                    if($chat_info['type'] == 1)
                    {
                        //判断对方是否在线
                        $fd = $this->redis6->get($chat_info['to_id'].'_fd');
                        //推送给指定用户
                        if($fd > 0)
                        {
                            $server->push((int)$fd, $this->return_success_data('撤回消息',$data,601));
                        }
                    }else{
                        //获取在线群友
                        $arr = $this->redis6->sMembers('group_online_'.$chat_info['to_id']);
                        if(!empty($arr))
                        {
                            //推送给指定用户除了自己
                            foreach ($arr as $v)
                            {
                                if($v != $frame->fd)
                                {
                                    $server->push((int)$v, $this->return_success_data('撤回消息',$data,601));
                                }
                            }
                        }
                    }
                    break;
                case 'group_chat':
                    //聊天
                    $to_id = $message['data']['to_id'];
                    if(empty($to_id))
                    {
                        $server->push((int)$frame->fd, $this->return_error('该群不存在！'));
                        return;
                    }
                    //判断对方是否存在
                    $group_info = DsUserChatGroup::query()->where('num',$to_id)->select('user_id','manage_id')->first();
                    if(empty($group_info))
                    {
                        $server->push((int)$frame->fd, $this->return_error('该群不存在！'));
                        return;
                    }
                    $group_info = $group_info->toArray();
                    //判断是否是群友
                    $chat_list_id = DsUserChatList::query()->where('user_id',$user_id)->where('to_id',$to_id)
                        ->value('chat_list_id');
                    if(!$chat_list_id)
                    {
                        $server->push((int)$frame->fd, $this->return_error('你还未加入该群! 请先申请入群'));
                        return;
                    }
                    $room_id = $message['data']['room_id'];
                    if(empty($room_id))
                    {
                        $server->push((int)$frame->fd, $this->return_error('房间不存在！'));
                        return;
                    }
                    //判断房间号是否存在
                    if($room_id != $to_id)
                    {
                        $server->push((int)$frame->fd, $this->return_error('房间不存在！'));
                        return;
                    }
                    //判断自己是否被禁言
                    if($this->check_fayan($user_id,$room_id)){
                        $server->push((int)$frame->fd, $this->return_error('你已被禁言！'));
                        return;
                    }
                    $content = empty($message['data']['content'])?'':$message['data']['content'];
                    $cont_type    = empty($message['data']['cont_type'])?'':$message['data']['cont_type'];
                    if(empty($content) || empty($cont_type))
                    {
                        $server->push((int)$frame->fd, $this->return_error('缺少发送内容!'));
                        return;
                    }
                    if($cont_type == 5)
                    {
                        $idvv = DsUser::query()->where('user_id',$content)->select('user_id','nickname','avatar')->first();
                        if(empty($idvv))
                        {
                            $server->push((int)$frame->fd, $this->return_error('你选择的名片不存在!'));
                            return;
                        }
                        $idvv = $idvv->toArray();
                    }
                    $time = time();
                    //添加到数据库
                    $data = [
                        'room_id'   => $room_id,
                        'form_id'   => $user_id,
                        'to_id'     => $to_id,
                        'content'   => $content,
                        'cont_type' => $cont_type,
                        'time'      => $time,
                        'day'       => strtotime(date('Y-m-d')),
                        'type'      => 2,
                    ];
                    $chat_cont_id = DsUserChatCont::query()->insertGetId($data);
                    if($chat_cont_id)
                    {
                        if($cont_type != 5)
                        {
                            $data['time_stamp'] = $time;
                            $data['time'] = date("Y-m-d H:i:s", $time);
                            unset($data['day']);
                            $data['chat_cont_id'] = $chat_cont_id;
                        }else{
                            $data['time_stamp'] = $time;
                            $data['time'] = date("Y-m-d H:i:s", $time);
                            unset($data['day']);
                            $data['chat_cont_id'] = $chat_cont_id;
                            $data['mp_id'] = $idvv['user_id'];
                            $data['mp_name'] = $idvv['nickname'];
                            $data['mp_img'] = $idvv['avatar'];
                        }
                        $user_info = DsUser::query()->where('user_id',$user_id)->select('user_id','nickname','avatar','group')->first()->toArray();
                        $data['name'] = $user_info['nickname'];
                        $data['img'] = $user_info['avatar'];
                        $data['group'] = $user_info['group'];
                        $data['qx']   = 0;
                        //自己权限
                        //判断权限
                        if($user_id == $group_info['user_id'])
                        {
                            $data['qx']   = 2;
                        }else{
                            if(!empty($group_info['manage_id']))
                            {
                                $arr = json_decode($group_info['manage_id'],true);
                                if(!empty($arr))
                                {
                                    if(in_array($user_id,$arr))
                                    {
                                        $data['qx']  = 1;
                                    }
                                }
                            }
                        }
                        //禁言状态
                        $data['dui_no_chat'] = 0;
                        $sd = $this->redis6->get('group_'.$room_id.'_no_chat_user_id_'.$user_id);
                        if($sd == 2)
                        {
                            $data['dui_no_chat'] = 1;
                        }
                        //添加消息列表
                        $this->add_chat($user_id,$to_id,2);
                        $server->push($frame->fd, $this->return_success_data('发送成功',$data,501));
                        //获取在线群友
                        $arr = $this->redis6->sMembers('group_online_'.$to_id);
                        if(!empty($arr))
                        {
                            //推送给指定用户除了自己
                            foreach ($arr as $v)
                            {
                                if($v != $frame->fd)
                                {
                                    $server->push((int)$v, $this->return_success_data('收到消息',$data,501));
                                }
                            }
                        }
                    }
                    break;
                case 'group_revoke_chat':
                    //群管理撤回用户消息
                    $room_id = $message['data']['room_id'];
                    if(empty($room_id))
                    {
                        $server->push((int)$frame->fd, $this->return_error('房间不存在！'));
                        return;
                    }
                    $group_info = DsUserChatGroup::query()->where('num',$room_id)->select('user_id','manage_id')->first();
                    if(empty($group_info))
                    {
                        $server->push((int)$frame->fd, $this->return_error('房间不存在！'));
                        return;
                    }
                    $group_info = $group_info->toArray();
                    $chat_cont_id = empty($message['data']['chat_cont_id'])? 0:$message['data']['chat_cont_id'];
                    //查找该消息是否存在
                    $chat_time = DsUserChatCont::query()->where('chat_cont_id',$chat_cont_id)
                        ->where('to_id',$room_id)
                        ->value('time');
                    if(!$chat_time)
                    {
                        $server->push($frame->fd, $this->return_error('消息不存在'));
                        return;
                    }
                    //判断3分钟
//                    if(time()-$chat_time >= 180)
//                    {
//                        $server->push($frame->fd, $this->return_error('无法撤回超过3分钟的内容!'));
//                        return;
//                    }
                    //判断用户权限是否能撤回
                    if(!$this->check_guanliyuan($user_id,$group_info))
                    {
                        $server->push($frame->fd, $this->return_error('权限不足!'));
                        return;
                    }
                    $res = DsUserChatCont::query()->where('chat_cont_id',$chat_cont_id)->update(['status' => 0]);
                    if(!$res)
                    {
                        $server->push($frame->fd, $this->return_error('服务器繁忙,请重试'));
                        return;
                    }
                    $data = [
                        'chat_cont_id' => $chat_cont_id
                    ];
                    $server->push($frame->fd, $this->return_success_data('撤回消息',$data,601));
                    //获取在线群友
                    $arr = $this->redis6->sMembers('group_online_'.$room_id);
                    if(!empty($arr))
                    {
                        //推送给指定用户除了自己
                        foreach ($arr as $v)
                        {
                            if($v != $frame->fd)
                            {
                                $server->push((int)$v, $this->return_success_data('撤回消息',$data,601));
                            }
                        }
                    }
                    break;
                case 'no_chat_user':
                    //单个用户禁言/解言
                    $room_id = $message['data']['room_id'];
                    if(empty($room_id))
                    {
                        $server->push((int)$frame->fd, $this->return_error('房间不存在！'));
                        return;
                    }
                    $group_info = DsUserChatGroup::query()->where('num',$room_id)->select('user_id','manage_id')->first();
                    if(empty($group_info))
                    {
                        $server->push((int)$frame->fd, $this->return_error('房间不存在！'));
                        return;
                    }
                    $group_info = $group_info->toArray();
                    //判断用户权限是否能撤回
                    if(!$this->check_guanliyuan($user_id,$group_info))
                    {
                        $server->push($frame->fd, $this->return_error('权限不足!'));
                        return;
                    }
                    $to_id = empty($message['data']['user_id'])? 0:$message['data']['user_id'];
                    $type = empty($message['data']['type'])? 1:$message['data']['type']; //1禁言 2解除禁言
                    $t  = empty($message['data']['time'])? 1:$message['data']['time']; //1禁言10分钟 2禁言半小时 3 1小时 4 24小时
                    if($user_id == $to_id)
                    {
                        $server->push($frame->fd, $this->return_error('您不能禁言自己!'));
                        return;
                    }
                    //判断是否是超级管理员
                    if($this->check_guanliyuan($to_id,$group_info))
                    {
                        $server->push($frame->fd, $this->return_error('对方是管理员！无法禁言管理员!'));
                        return;
                    }
                    //判断用户是否存在
                    $id = DsUser::query()->where('user_id',$to_id)->value('user_id');
                    if(!$id)
                    {
                        $server->push($frame->fd, $this->return_error('对方不存在!'));
                        return;
                    }
                    //判断对方是否在群里
                    $chat_list_id = DsUserChatList::query()->where('user_id',$to_id)->where('to_id',$room_id)
                        ->value('chat_list_id');
                    if(!$chat_list_id)
                    {
                        $server->push((int)$frame->fd, $this->return_error('对方不是该群成员！'));
                        return;
                    }
                    if($type == 1)
                    {
                        if($t == 1)
                        {
                            $time = 600;
                            $time2 = ' 已被禁言10分钟!';
                        }elseif($t == 2)
                        {
                            $time = 1800;
                            $time2 = ' 已被禁言半小时!';
                        }elseif($t == 3)
                        {
                            $time = 3600;
                            $time2 = ' 已被禁言一小时!';
                        }else{
                            $time = 86400;
                            $time2 = ' 已被禁言一天!';
                        }
                        if($time > 0)
                        {
                            $this->redis6->set('group_'.$room_id.'_no_chat_user_id_'.$to_id,2,$time);
                        }else{
                            $this->redis6->set('group_'.$room_id.'_no_chat_user_id_'.$to_id,2);
                        }
                        $data = ['user_id'=> $to_id ];
                        $code = 604;
                        $msg = 'user_id: '.$to_id.$time2;
                    }else{
                        $this->redis6->del('group_'.$room_id.'_no_chat_user_id_'.$to_id);
                        $data = ['user_id'=>$to_id ];
                        $code = 605;
                        $msg = 'user_id: '.$to_id.' 已解除禁言';
                    }
                    $server->push((int)$frame->fd, $this->return_success('操作成功'));
                    //获取在线群友
                    $arr = $this->redis6->sMembers('group_online_'.$room_id);
                    if(!empty($arr))
                    {
                        //推送给指定用户除了自己
                        foreach ($arr as $v)
                        {
                            if($v != $frame->fd)
                            {
                                $server->push((int)$v, $this->return_success_data($msg,$data,$code));
                            }
                        }
                    }
                    break;
                case 'no_chat_all':
                    //全员禁言/解言
                    //判断是否是超级管理员
                    $set_shouma = $this->redis0->get('set_shouma');
                    $set_shouma = json_decode($set_shouma,true);
                    if(!in_array($user_id,$set_shouma))
                    {
                        $server->push($frame->fd, $this->return_error('权限不足!'));
                        return;
                    }
                    $room_id = empty($message['data']['room_id'])? 0:$message['data']['room_id'];
                    $type = empty($message['data']['type'])? 1:$message['data']['type']; //1禁言 2解除禁言
                    $room = $this->redis6->get('room');
                    $room = json_decode($room,true);
                    //判断房号是否存在
                    if(!in_array($room_id,$room))
                    {
                        $server->push((int)$frame->fd, $this->return_error('房间不存在'));
                        return;
                    }
                    if($type == 1)
                    {
                        $this->redis6->set('room_'.$room_id.'_no_chat',2);
                        $msg = '全员禁言';
                        $code = 602;
                    }else{
                        $this->redis6->del('room_'.$room_id.'_no_chat');
                        $msg = '解除全员禁言';
                        $code = 603;
                    }
                    $server->push((int)$frame->fd, $this->return_success('操作成功'));
                    if($room_id == 1)
                    {
                        $fds = $this->redis6->SMEMBERS('chat_all_ren');
                        if(!empty($fds))
                        {
//                                        //推送不包括自己
//                                        if(in_array($frame->fd,$fds))
//                                        {
//                                            $fds = array_values(array_diff($fds, [$frame->fd]));
//                                        }
                            foreach ($fds as $v)
                            {
                                $server->push((int)$v, $this->return_success($msg,$code));
                            }
                        }
                    }else if($room_id == 2)
                    {
                        $fds = $this->redis6->SMEMBERS('chat_da_ren');
                        if(!empty($fds))
                        {
//                                        //推送不包括自己
//                                        if(in_array($frame->fd,$fds))
//                                        {
//                                            $fds = array_values(array_diff($fds, [$frame->fd]));
//                                        }
                            foreach ($fds as $v)
                            {
                                $server->push((int)$v, $this->return_success($msg,$code));
                            }
                        }
                    }else{
                        $fds = $this->redis6->SMEMBERS('chat_all_ren');
                        if(!empty($fds))
                        {
//                                        //推送不包括自己
//                                        if(in_array($frame->fd,$fds))
//                                        {
//                                            $fds = array_values(array_diff($fds, [$frame->fd]));
//                                        }
                            foreach ($fds as $v)
                            {
                                $server->push((int)$v, $this->return_success($msg,$code));
                            }
                        }
                    }
                    break;
                default:
                    $server->push($frame->fd, $this->return_error('类型选择错误'));
                    break;
            }
        }
    }

    protected function add_chat($user_id,$to_id,$type)
    {
        //判断是否存在
        $where = [
            'user_id'   => $user_id,
            'to_id'     => $to_id,
        ];
        $chat_id = DsUserChat::query()->where($where)->value('chat_id');
        if(!$chat_id)
        {
            $data = [
                'user_id'   => $user_id,
                'to_id'     => $to_id,
                'jiao'      => 0,
                'type'      => $type,
            ];
            DsUserChat::query()->insert($data);
        }
        if($type == 1)
        {
            $where2 = [
                'user_id'   => $to_id,
                'to_id'     => $user_id,
            ];
            $chat_id2 = DsUserChat::query()->where($where2)->value('chat_id');
            if(!$chat_id2)
            {
                $data = [
                    'user_id'   => $to_id,
                    'to_id'     => $user_id,
                    'jiao'      => 1,
                    'type'      => $type,
                ];
                DsUserChat::query()->insert($data);
            }else{
                DsUserChat::query()->where('chat_id',$chat_id2)->increment('jiao');
            }
        }else{
            //通知所有人
            DsUserChat::query()->where('type',$type)->where('to_id',$to_id)->increment('jiao');
        }
    }
    /**
     * 拼手气红包生成算法
     * @param $red_total_money  //总金额
     * @param $red_num          //总数量
     * @return array
     */
    protected function red_algorithm($red_total_money, $red_num)
    {
        //1 声明定义最小红包值
        $red_min = 0.01;

        //2 声明定义生成红包结果
        $result_red = array();

        //3 惊喜红包计算
        for ($i = 1; $i < $red_num; $i++) {
            //3.1 计算安全上限 | 保证每个人都可以分到最小值
            $safe_total = ($red_total_money - ($red_num - $i) * $red_min) / ($red_num - $i);
            //3.2 随机取出一个数值
//            $red_money_tmp = mt_rand($red_min * 100, $safe_total * 100) / 100;
            $red_money_tmp = $this->randomFloat($red_min,$safe_total);
            //3.3 将金额从红包总金额减去
            $red_total_money -= $red_money_tmp;
            $result_red[] = strval(round($red_money_tmp,2));
//        $result_red[] = array(
//            'red_code' => $i,
//            'red_title' => '红包' . $i,
//            'red_money' => $red_money_tmp,
//        );
        }

        //4 最后一个红包
        $result_red[] = strval(round($red_total_money,2));
//    $result_red[] = array(
//        'red_code' => $red_num,
//        'red_title' => '红包' . $red_num,
//        'red_money' => $red_total_money,
//    );

        return $result_red;
    }

    /**
     * @param int $min
     * @param int $max
     * @return string
     */
    protected function randomFloat($min = 0, $max = 1)
    {
        $num = $min + mt_rand() / mt_getrandmax() * ($max - $min);
        return sprintf("%.2f",$num);  //控制小数后几位
    }

    /**
     * 判断是否是管理员
     * @param $user_id
     * @param $group_info
     * @return bool
     */
    protected function check_guanliyuan($user_id,$group_info)
    {
        //判断用户权限是否能撤回
        if($user_id == $group_info['user_id'])
        {
            return true;
        }else{
            if(empty($group_info['manage_id']))
            {
                return false;
            }else{
                $arr = json_decode($group_info['manage_id'],true);
                if(empty($arr))
                {
                    return false;
                }
                if(in_array($user_id,$arr))
                {
                    return true;
                }else{
                    return false;
                }
            }
        }
    }

    /**
     * 检测是否能发言
     * @param $user_id
     * @param $room_id
     * @return bool
     */
    protected function check_fayan($user_id,$room_id)
    {
        //判断是否被禁言
        $rev = $this->redis6->get('group_'.$room_id.'_no_chat_user_id_'.$user_id);
        if($rev == 2)
        {
            return true;
        }
        return false;
    }

    /**
     * 字符串模糊查找
     * @param $needle   //要包含的字符串
     * @param $str      //要查找的字符串内容
     * @return bool
     */
    protected function check_search_str($needle,$str)
    {
        $needle = strval($needle);
        $str = strval($str);
        if(strpos($str,$needle) !== false)
        {
            return true;
        }else{
            return false;
        }
    }
    /**
     * @param $user_id
     * @param $code
     * @return array|int[]
     */
    protected function _checkCode($user_id,$code)
    {
        //查找用户手机号
        $mobile = DianUser::query()->where('user_id',$user_id)->value('mobile');
        if(!$mobile)
        {
            return ['code'=> 1002,'msg'=>'验证码错误'];
        }
        $condition = [
            'mobile'        => $mobile,
            'code_type'     => 'pay',
            'code_value'    => $code,
            'user_id'       => $user_id,
        ];
        $codeInfo = DianCode::query()->where($condition)->first();
        if (!empty($codeInfo))
        {
            $time = time();
            $info = $codeInfo->toArray();
            if($info['expired_time'] < $time)
            {
                return ['code'=> 1003,'msg'=>'验证码已过期'];
            }
            //修改验证码为已过期
            DianCode::query()->where('code_id',$info['code_id'])->update(['expired_time' =>$time ]);
            return ['code' => 200];
        }else{
            return ['code'=> 1002,'msg'=>'验证码错误'];
        }
    }
    /**
     * 写入数据库
     * @param $user_id
     * @param $content
     * @param $pay_type
     * @return array|bool
     */
    protected function xieru($user_id,$content,$pay_type)
    {
        //把之前生成的过期
        DianOrder2::query()->where('user_id',$user_id)->where('order_status',0)->update(['order_status' => 2]);
        //再添加新的
        $or = make(OrderSn::class);
        $order_sn = $or->createOrderSNWs();
        if(empty($order_sn))
        {
            return false;
        }
        $data = [
            'user_id'       => $user_id,
            'order_sn'      => $order_sn,
            'order_status'  => 0,
            'img'           => $content,
            'payment_type'  => $pay_type,
            'add_time'      => time(),
        ];
        $res = DianOrder2::query()->insertGetId($data);
        if($res)
        {
            return ['order_sn' =>$data['order_sn'],'order_id' =>$res ];
        }else{
            return false;
        }
    }
    public function onClose(Server $server, int $fd, int $reactorId): void
    {
//        var_dump($fd.'断开了'.PHP_EOL);
        $this->del_fd_uid($fd);
    }

    public function onOpen(WebSocketServer $server, Request $request): void
    {
        var_dump($request->fd.'连接了'.'  '.json_encode($request->header).PHP_EOL);
        if(empty($request->get['admin']))
        {
            if(!empty($request->get['token']))
            {
                $res = $this->get_user_id($request->get['token']);
                if($res['code'] == 0)
                {
                    $server->push($request->fd, $this->return_error('需要登录'));
                    $server->close($request->fd);
//                    var_dump($request->fd. 'token不对：'.$request->get['token']);
                }else{
                    //取消绑定fd
                    $this->del_fd_uid($request->fd);
                    $user_id = $res['user_id'];
                    //绑定用户id
                    $server->bind($request->fd,(int)$user_id);
                    //绑定用户id和fd
                    $this->add_fd_uid($user_id,$request->fd);
                    $server->push($request->fd, $this->return_success('连接成功'));
                }
            }else{
                $server->push($request->fd, $this->return_error('需要登录'));
//                var_dump($request->fd. '需要登录');
                $server->close($request->fd);
            }
        }else{
            if($request->get['admin'] == 'zhongqi')
            {
                //取消绑定fd
                $this->del_fd_uid(10086);
                //绑定用户id
                $server->bind($request->fd,10086);
                //绑定用户id和fd
                $this->add_fd_uid(10086,$request->fd);
                $server->push($request->fd, $this->return_success('连接成功'));
            }else{
                $server->push($request->fd, $this->return_error('需要登录'));
                $server->close($request->fd);
            }
        }
    }

    /**
     * 绑定用户id和fd
     * @param $user_id
     * @param $fd
     */
    protected function add_fd_uid($user_id,$fd)
    {
        //添加到缓存信息
        $this->redis6->set($user_id.'_fd',$fd,300);
        //添加到缓存信息
        $this->redis6->set('fd_'.$fd,$user_id,300);
        //增加到集合
        $day = date('Y-m-d');
        $this->redis6->sAdd($day.'_online',$user_id);
        $this->redis6->sAdd($day.'_online_fd',$fd);
        //增加用户到房间
        $groups = DsUserChatList::query()->where('user_id',$user_id)->where('type',2)->pluck('to_id');
        if(!empty($groups))
        {
            foreach ($groups as $v)
            {
                //增加到集合
                $this->redis6->sAdd('group_online_'.$v,$fd);
            }
        }
    }
    /**
     * 取消绑定fd
     * @param $fd
     */
    protected function del_fd_uid($fd)
    {
        $this->redis6->del('fd_'.$fd);
        //减少集合
        $day = date('Y-m-d');
        $this->redis6->sRem($day.'_online_fd',$fd);
        //通过fd找用户id
        $user_id = $this->redis6->get('fd_'.$fd);
        if($user_id)
        {
            $this->redis6->del($user_id.'_fd');
            $this->redis6->sRem($day.'_online',$user_id);
            //减少用户到房间
            $groups = DsUserChatList::query()->where('user_id',$user_id)->where('type',2)->pluck('to_id');
            if(!empty($groups))
            {
                foreach ($groups as $v)
                {
                    $this->redis6->sRem('group_online_'.$v,$fd);
                }
            }
        }
    }
    /**
     * 获取用户id和信息
     * @param $token
     * @param int $type  1只获取id 2获取所有信息
     * @return array
     */
    protected function get_user_id($token,$type =1)
    {
        //验证token是否正确
        $user_id = DsUserToken::query()->where('token',$token)->value('user_id');
        //获取用户信息
        $user_info = DsUser::query()->where('user_id',$user_id)->first();
        if(empty($user_info))
        {
            return ['code'=> 0,'msg'=>'需要登录'];
        }
        $user_info = $user_info->toArray();
        if($type == 1)
        {
            return ['code'=> 1,'user_id'=> $user_id];
        }else{
            return ['code'=> 1,'user_info'=>$user_info,'user_id'=> $user_id];
        }
    }
    /**
     * 异步分离出来
     * @param $info
     * @param int $tt  延时时间
     */
    protected function yibu($info,$tt = 0)
    {
        if($tt == 0)
        {
            //读取配置是否允许执行
            $db = $this->redis0->get('async_db');
            $redis = $this->container->get(\Redis::class);
            $redis->select((int)$db);
            $job = $this->container->get(DriverFactory::class);
            $job->get('async')->push(new Async($info));
        }else
        {
            //读取配置是否允许执行
            $db = $this->redis0->get('async_db');
            $redis = $this->container->get(\Redis::class);
            $redis->select((int)$db);
            $job = $this->container->get(DriverFactory::class);
            $job->get('async')->push(new Async($info),(int)$tt);
        }
    }
}