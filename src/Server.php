<?php
namespace WebIM;

class Server extends \Swoole\Network\Protocol\WebSocket
{
    /**
     * @var Store\File;
     */
    protected $store;

    const MESSAGE_MAX_LEN     = 1024; //单条消息不得超过1K
    const WORKER_HISTORY_ID   = 0;

    function __construct($config = array())
    {
        parent::__construct($config);
    }

    function setStore($store)
    {
        $this->store = $store;
    }

    /**
     * 下线时，通知所有人
     */
    function onClose($serv, $client_id, $from_id)
    {
        $userInfo = $this->store->getUser($client_id);
        if (!$userInfo)
        {
            return;
        }
        $resMsg = array(
            'cmd' => 'offline',
            'fd' => $client_id,
            'from' => 0,
            'channal' => 0,
            'data' => $userInfo['name'] . "下线了。。",
        );
        //将下线消息发送给所有人
        $this->log("onOffline: " . $client_id);

        $this->store->logout($client_id);
        $this->broadcastJson($client_id, $resMsg);
        parent::onClose($serv, $client_id, $from_id);
    }

    function onTask($serv, $task_id, $from_id, $data)
    {
        $req = unserialize($data);
        if ($req)
        {
            switch($req['cmd'])
            {
                case 'getHistory':
                    $history = $this->store->getHistory();
                    $this->sendJson($req['fd'], array('cmd'=> 'getHistory', 'history' => $history));
                    break;
                case 'addHistory':
                    $this->store->addHistory($req['fd'], $req['msg']);
                    break;
                default:
                    break;
            }
        }
    }

    function onFinish($serv, $task_id, $data)
    {

    }

    /**
     * 获取在线列表
     */
    function cmd_getOnline($client_id, $msg)
    {
        $resMsg = array(
            'cmd' => 'getOnline',
        );
        $users = $this->store->getOnlineUsers();
        $info = $this->store->getUsers(array_slice($users, 0, 100));
        $resMsg['users'] = $users;
        $resMsg['list'] = $info;
        $this->sendJson($client_id, $resMsg);
    }

    /**
     * 获取历史聊天记录
     */
    function cmd_getHistory($client_id, $msg)
    {
        $task['fd'] = $client_id;
        $task['cmd'] = 'getHistory';
        $task['offset'] = '0,100';
        //在task worker中会直接发送给客户端
        $this->getSwooleServer()->task(serialize($task), self::WORKER_HISTORY_ID);
    }

    /**
     * 登录
     * @param $client_id
     * @param $msg
     */
    function cmd_login($client_id, $msg)
    {
        $info['name'] = $msg['name'];
        $info['avatar'] = $msg['avatar'];

        //回复给登录用户
        $resMsg = array(
            'cmd' => 'login',
            'fd' => $client_id,
            'name' => $msg['name'],
            'avatar' => $msg['avatar'],
        );
        $this->store->login($client_id, $resMsg);
        $this->sendJson($client_id, $resMsg);

        //广播给其它在线用户
        $resMsg['cmd'] = 'newUser';
        //将上线消息发送给所有人
        $this->broadcastJson($client_id, $resMsg);
        //用户登录消息
        $loginMsg = array(
            'cmd' => 'fromMsg',
            'from' => 0,
            'channal' => 0,
            'data' => $msg['name'] . "上线鸟。。",
        );
        $this->broadcastJson($client_id, $loginMsg);
    }

    /**
     * 发送信息请求
     */
    function cmd_message($client_id, $msg)
    {
        $resMsg = $msg;
        $resMsg['cmd'] = 'fromMsg';

        if (strlen($msg['data']) > self::MESSAGE_MAX_LEN)
        {
            $this->sendErrorMessage($client_id, 102, 'message max length is '.self::MESSAGE_MAX_LEN);
            return;
        }

        //表示群发
        if ($msg['channal'] == 0)
        {
            $this->broadcastJson($client_id, $resMsg);
            $this->getSwooleServer()->task(serialize(array(
                'cmd' => 'addHistory',
                'msg' => $msg,
                'fd'  => $client_id,
            )), self::WORKER_HISTORY_ID);
        }
        //表示私聊
        elseif ($msg['channal'] == 1)
        {
            $this->sendJson($msg['to'], $resMsg);
            //$this->store->addHistory($client_id, $msg['data']);
            //$this->sendJson($msg['from'], $resMsg);
        }
    }

    /**
     * 接收到消息时
     * @see WSProtocol::onMessage()
     */
    function onMessage($client_id, $ws)
    {
        $this->log("onMessage: " . $ws['message']);
        $msg = json_decode($ws['message'], true);
        if (empty($msg['cmd']))
        {
            $this->sendErrorMessage($client_id, 101, "invalid command");
            return;
        }
        $func = 'cmd_'.$msg['cmd'];
        $this->$func($client_id, $msg);
    }

    /**
     * 发送错误信息
    * @param $client_id
    * @param $code
    * @param $msg
     */
    function sendErrorMessage($client_id, $code, $msg)
    {
        $this->sendJson($client_id, array('cmd' => 'error', 'code' => $code, 'msg' => $msg));
    }

    /**
     * 发送JSON数据
     * @param $client_id
     * @param $array
     */
    function sendJson($client_id, $array)
    {
        $msg = json_encode($array);
        $this->send($client_id, $msg);
    }

    /**
     * 广播JSON数据
     * @param $client_id
     * @param $array
     */
    function broadcastJson($client_id, $array)
    {
        $msg = json_encode($array);
        $this->broadcast($client_id, $msg);
    }

    function broadcast($client_id, $msg)
    {
        if (extension_loaded('swoole'))
        {
            $sw_serv = $this->getSwooleServer();
            $start_fd = 0;
            while(true)
            {
                $conn_list = $sw_serv->connection_list($start_fd, 10);
                if($conn_list === false)
                {
                    break;
                }
                $start_fd = end($conn_list);
                foreach($conn_list as $fd)
                {
                    if($fd === $client_id) continue;
                    $this->send($fd, $msg);
                }
            }
        }
        else
        {
            foreach ($this->connections as $fd => $info)
            {
                if ($client_id != $fd)
                {
                    $this->send($fd, $msg);
                }
            }
        }
    }
}
