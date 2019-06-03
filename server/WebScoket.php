<?php

/**
 * Description of WebScoket
 * Date 2018/8/29
 * @author xiaoyukarl
 */
class WebScoket
{

    private $port = '4000';
    private $ip = '0.0.0.0';
    private $ws = null;

    private $tableMessage   = null;
    private $tableUser    = null;
    private $tableFd    = null;

    public function __construct()
    {
        //创建一个用户信息表和聊天记录表
        $this->initTable();

        $this->ws = new swoole_websocket_server($this->ip, $this->port);

        $this->ws->set(
            [
                'task_worker_num'=>2,//task_worker进程数量
                'worker_num'=>4,//worker进程数
                'heartbeat_check_interval' => 30, //30秒遍历一次，一个连接如果40秒内未向服务器发送任何数据，此连接将被强制关闭
                'heartbeat_idle_time' => 40, //40秒内未向服务器发送任何数据，此连接将被强制关闭
                //粘包问题解决方式
                'open_package_check' => true,
                'package_length_type' => 'N',
                'package_body_offset' => 4,
                'package_length_offset' => 0,
            ]
        );

        $this->ws->on('open', [$this, 'onOpen']);
        $this->ws->on('request', [$this, 'onRequest']);
        $this->ws->on('message', [$this, 'onMessage']);
        $this->ws->on('close', [$this, 'onClose']);
        $this->ws->on('task', [$this, 'onTask']);
        $this->ws->on('finish', [$this, 'onFinish']);
        $this->ws->start();
    }

    public function initTable()
    {
        $length = 1024 * 128;
        $this->tableUser = new swoole_table($length);
//        $this->tableUser->column('fd', swoole_table::TYPE_INT, 4);
        $this->tableUser->column('phone', swoole_table::TYPE_STRING, 32);
        $this->tableUser->create();

        $this->tableFd = new swoole_table($length);
//        $this->tableFd->column('phone', swoole_table::TYPE_STRING, 32);
        $this->tableFd->column('fd', swoole_table::TYPE_INT, 4);
        $this->tableFd->create();

        $this->tableMessage = new swoole_table($length);
//        $this->tableFd->column('phone', swoole_table::TYPE_STRING, 32);//to_user
        $this->tableMessage->column('content', swoole_table::TYPE_STRING, 128);//message
        $this->tableMessage->create();
    }


    public function onRequest($request, $response)
    {
        foreach ($this->ws->connections as $fd) {
            $this->ws->push($fd, $request->get['message']);
        }
    }

    public function onOpen($server, $request)
    {
        //echo $request->fd.' open';
    }

    public function onMessage($server, $frame)
    {
        echo "receive data:".$frame->data . PHP_EOL;

        $data = json_decode($frame->data, true);
        if(!isset($data['cmd'])){
            $response = $this->responseData('message',['msg' => 'cmd error']);
            return $server->push($frame->fd, $response);
        }
        switch($data['cmd']){
            case 'login':
                //登录
                $phone = $data['data']['phone'];
                //检查这个手机号是否登录, 已经登录的话踢下线
                $oldFd = $this->tableFd->get($phone,'fd');
                if($oldFd){
                    $this->tableFd->del($phone);
                    $this->tableUser->del($oldFd);
                }
                $this->tableUser->set($frame->fd, ['phone' => $phone]);
                $this->tableFd->set($phone,['fd' => $frame->fd]);

                $loginRes = $this->responseData('login',['content' => 'login success']);
                $server->push($frame->fd, $loginRes);

                $jsonMessage = $this->tableMessage->get($phone,'content');
                if(!empty($jsonMessage)){
                    $userMessages = json_decode($jsonMessage, true);
                    foreach($userMessages as $userMessage){
                        $res = $this->responseData('message',['content' => $userMessage]);
                        $server->push($frame->fd, $res);
                    }
                    $this->tableMessage->del($phone);
                }

                break;
            case 'message':
                //消息
                $toUser = $data['data']['toUser'];
                $toUserFd = $this->tableFd->get($toUser,'fd');
                if($toUserFd){
                    $responseMsg = $this->responseData('message', ['content' => $data['data']['content']]);
                    $server->push($toUserFd, $responseMsg);
                }else{
                    if($this->tableMessage->exist($toUser)){
                        $jsonMessage = $this->tableMessage->get($toUser,'content');
                        $messages = json_decode($jsonMessage, true);
                        $messages[] = $data['data']['content'];
                    }else{
                        $messages[] = $data['data']['content'];
                    }
                    $jsonMessage = json_encode($messages, JSON_UNESCAPED_UNICODE);
                    $this->tableMessage->set($toUser,['content' => $jsonMessage]);
                }
                break;
            case 'heart':
                //心跳
                return $server->push($frame->fd,$this->responseData('heart',["fd" => $frame->fd]));
                break;
        }

    }

    public function responseData($cmd, $data, $code = 0)
    {
        $response = ['cmd'=>$cmd, 'data' => $data, 'code' => $code];
        return json_encode($response,JSON_UNESCAPED_UNICODE);
    }


    public function onClose($server,$fd)
    {
        $phone = $this->tableUser->get($fd,'phone');
        if($phone){
            $this->tableFd->del($phone);
        }
        $this->tableUser->del($fd);
        echo $fd.' close';
    }

    public function onTask()
    {

    }

    public function onFinish($ws,$task_id,$data)
    {
        echo "taskId:$task_id finish";
    }
}

$ws = new WebScoket();