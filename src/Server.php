<?php
namespace WebIM;
use Swoole;
use SPF\Filter;
use SPF;

class Server
{
    protected $users;
    protected $config;
    protected $connections;
    protected $redis;
    protected $db;
    /**
     * 上一次发送消息的时间
     * @var array
     */
    protected $lastSentTime = array();

    const MESSAGE_MAX_LEN     = 1024; //单条消息不得超过1K
    const WORKER_HISTORY_ID   = 0;
    const PREFIX = 'webim';

    function __construct($config = array())
    {
        $this->config = $config;
    }

    function log($msg)
    {
        SPF\App::getInstance()->log->put($msg);
    }

    function getSession($req, $resp)
    {
        $redis = $this->redis;
        $webim_config = SPF\App::getInstance()->config['webim'];
        $login_config = SPF\App::getInstance()->config['login'];

        //没有 SessionKey
        if (empty($req->cookie['session_key'])) {
            $resp->setcookie("session_key", session_create_id(), time()+86400*30, '/');
            goto login;
        }
        
        $session_key = $req->cookie['session_key'];
        $redis_key = 'session:'.$session_key;
        $session = unserialize($redis->get($redis_key));

        //已登录
        if (!empty($session['isLogin'])) {
            goto _success;
        }

        if (empty($req->get['token'])) {
            login:
            $refer = "http://{$webim_config['server']['host']}:{$webim_config['server']['port']}/";
            $resp->redirect($login_config['passport'] . '?return_token=1&refer=' . urlencode($refer));
            return false;
        } else {
            $user = file_get_contents($login_config['get_user_info'] . '?token=' . urlencode($req->get['token']));
            if (empty($user)) {
                goto login;
            } else {
                $session['isLogin'] = true;
                $session['user'] = json_decode($user, true);
                $redis->set($redis_key, serialize($session));
            }
        }

        _success:
        return unserialize($redis->get($redis_key));
    }

    function run()
    {
        \Co\Run(function () {
            $this->redis = new RedisPool(SPF\App::getInstance()->config['redis']['master']);
            $this->db = new MySQLPool(SPF\App::getInstance()->config['db']['master']);
            $config = $this->config;
            $server = new Swoole\Coroutine\Http\Server($config['server']['host'], $config['server']['port']);
            $server->handle('/', function ($req, $resp) use ($config) {
                $user = $this->getSession($req, $resp);
                if ($user === false) {
                    return;
                } else {
                    $resp->redirect('/chatroom');
                }
            });

            $server->handle('/chatroom', function ($req, $resp) use ($config) {
                $session = $this->getSession($req, $resp);
                if ($session === false) {
                    return;
                } else {
                    ob_start();
                    $debug = true;
                    $user = $session['user'];
                    include dirname(__DIR__).'/resources/templates/chatroom.php';
                    $html = ob_get_clean();
                    $resp->end($html);
                }
            });

            $server->handle('/static', function ($req, $resp) {
                $file = dirname(__DIR__).'/resources/'.$req->server['request_uri'];
                if (is_file($file)) {
                    $resp->sendfile($file);
                } else {
                    $resp->status(404);
                }
            });

            $server->handle('/favicon.ico', function ($req, $resp) {
                $resp->status(404);
            });
            
            $server->handle('/websocket', function ($req, $ws) {
                $ws->upgrade();
                $this->join($ws);
                while (true) {
                    $frame = $ws->recv();
                    if ($frame === false) {
                        echo "error : " . swoole_last_error() . "\n";
                        break;
                    } else if ($frame == '') {
                        break;
                    } else {
                        $this->onMessage($ws, $frame);
                        //$ws->push("Hello {$frame->data}!");
                        //$ws->push("How are you, {$frame->data}?");
                    }
                }
                $this->onExit($ws->fd);
            });

            $server->start();
        });
    }
    
    /**
     * 接收到消息时
     */
    function onMessage($ws, $frame)
    {
        $client_id = $ws->fd;
        $this->log("onMessage #$client_id: " . $frame->data);
        $msg = json_decode($frame->data, true);
        if (empty($msg['cmd']))
        {
            $this->sendErrorMessage($client_id, 101, "invalid command");
            return;
        }
        $func = 'cmd_'.$msg['cmd'];
        if (method_exists($this, $func))
        {
            $this->$func($client_id, $msg);
        }
        else
        {
            $this->sendErrorMessage($client_id, 102, "command $func no support.");
            return;
        }
    }

    /**
     * 下线时，通知所有人
     */
    function onExit($client_id)
    {
        $userInfo = $this->getUser($client_id);
        if ($userInfo)
        {
            $resMsg = array(
                'cmd' => 'offline',
                'fd' => $client_id,
                'from' => 0,
                'channal' => 0,
                'data' => $userInfo['name'] . "下线了",
            );
            $this->logout($client_id);
            unset($this->users[$client_id]);
            //将下线消息发送给所有人
            $this->broadcastJson($client_id, $resMsg);
        }
        unset($this->connections[$client_id]);
        $this->log("onOffline: " . $client_id);
    }

    function isCometClient($fd) {
        return false;
    }

    function onTask($req)
    {
        if ($req)
        {
            switch($req['cmd'])
            {
                case 'getHistory':
                    $history = array('cmd'=> 'getHistory', 'history' => $this->getHistory());
                    if ($this->isCometClient($req['fd']))
                    {
                        return $req['fd'].json_encode($history);
                    }
                    //WebSocket客户端可以task中直接发送
                    else
                    {
                        $this->sendJson(intval($req['fd']), $history);
                    }
                    break;
                case 'addHistory':
                    if (empty($req['msg']))
                    {
                        $req['msg'] = '';
                    }
                    $this->addHistory($req['fd'], $req['msg']);
                    break;
                default:
                    break;
            }
        }
    }

    function onFinish($serv, $task_id, $data)
    {
        $this->send(substr($data, 0, 32), substr($data, 32));
    }

    /**
     * 获取在线列表
     */
    function cmd_getOnline($client_id, $msg)
    {
        $resMsg = array(
            'cmd' => 'getOnline',
        );
        $users = $this->getOnlineUsers();
        $info = $this->getUsers(array_slice($users, 0, 100));
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
        $this->onTask($task);
    }

    /**
     * 登录
     * @param $client_id
     * @param $msg
     */
    function cmd_login($client_id, $msg)
    {
        $info['name'] = Filter::escape(strip_tags($msg['name']));
        $info['avatar'] = Filter::escape($msg['avatar']);

        //回复给登录用户
        $resMsg = array(
            'cmd' => 'login',
            'fd' => $client_id,
            'name' => $info['name'],
            'avatar' => $info['avatar'],
        );

        //把会话存起来
        $this->users[$client_id] = $resMsg;

        $this->login($client_id, $resMsg);
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
            'data' => $info['name'] . "上线了",
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

        $now = time();
        //上一次发送的时间超过了允许的值，每N秒可以发送一次
        if (isset($this->lastSentTime[$client_id]) and 
            $this->lastSentTime[$client_id] > $now - $this->config['webim']['send_interval_limit'])
        {
            $this->sendErrorMessage($client_id, 104, 'over frequency limit');
            return;
        }
        //记录本次消息发送的时间
        $this->lastSentTime[$client_id] = $now;

        //表示群发
        if ($msg['channal'] == 0)
        {
            $this->broadcastJson($client_id, $resMsg);
            $this->onTask(array(
                'cmd' => 'addHistory',
                'msg' => $msg,
                'fd'  => $client_id,
            ));
        }
        //表示私聊
        elseif ($msg['channal'] == 1)
        {
            $this->sendJson($msg['to'], $resMsg);
            //$this->store->addHistory($client_id, $msg['data']);
        }
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
        if ($this->send($client_id, $msg) === false)
        {
            $this->close($client_id);
        }
    }

    /**
     * 广播JSON数据
     * @param $client_id
     * @param $array
     */
    function broadcastJson($sesion_id, $array)
    {
        $msg = json_encode($array);
        $this->broadcast($sesion_id, $msg);
    }

    function join($ws)
    {
        $this->connections[$ws->fd] = $ws;
    }

    function send($fd, $data)
    {
        $ws = $this->connections[$fd];
        $ws->push($data);
    }

    function broadcast($current_session_id, $msg)
    {
        foreach ($this->users as $client_id => $name)
        {
            if ($current_session_id != $client_id)
            {
                $this->send($client_id, $msg);
            }
        }
    }

    function login($client_id, $info)
    {
        $this->redis->set(self::PREFIX . ':client:' . $client_id, json_encode($info));
        $this->redis->sAdd(self::PREFIX . ':online', $client_id);
    }

    function logout($client_id)
    {
        $this->redis->del(self::PREFIX.':client:'.$client_id);
        $this->redis->sRemove(self::PREFIX.':online', $client_id);
    }

    /**
     * 用户在线用户列表
     * @return array
     */
    function getOnlineUsers()
    {
        return $this->redis->sMembers(self::PREFIX . ':online');
    }

    /**
     * 批量获取用户信息
     * @param $users
     * @return array
     */
    function getUsers($users)
    {
        $keys = array();
        $ret = array();

        foreach ($users as $v)
        {
            $keys[] = self::PREFIX . ':client:' . $v;
        }

        $info = $this->redis->mget($keys);
        foreach ($info as $v)
        {
            $ret[] = json_decode($v, true);
        }

        return $ret;
    }

    /**
     * 获取单个用户信息
     * @param $userid
     * @return bool|mixed
     */
    function getUser($userid)
    {
        $ret = $this->redis->get(self::PREFIX . ':client:' . $userid);
        $info = json_decode($ret, true);

        return $info;
    }

    function exists($userid)
    {
        return $this->redis->exists(self::PREFIX . ':client:' . $userid);
    }

    function addHistory($userid, $msg)
    {
        $info = $this->getUser($userid);

        $log['user'] = $info;
        $log['msg'] = $msg;
        $log['time'] = time();
        $log['type'] = empty($msg['type']) ? '' : $msg['type'];

        $_msg = $this->db->escape(json_encode($msg));
        $_type = empty($msg['type']) ? '' : $msg['type'];

        $sql = "insert into ".self::PREFIX."_history(
            name, avatar, msg, type, send_ip) 
            values('{$info['name']}', '{$info['name']}', '{$_msg}', '{$_type}', '')";
        $this->db->query($sql);
    }

    function getHistory($offset = 0, $num = 100)
    {
        $data = array();
        $list = $this->db->query("select * from ".self::PREFIX."_history order by id desc 
            limit $offset, $num");
        foreach ($list as $li)
        {
            $result['type'] = $li['type'];
            $result['user'] = array('name' => $li['name'], 'avatar' => $li['avatar']);
            $result['time'] = strtotime($li['addtime']);
            $result['msg'] = json_decode($li['msg'], true);
            $data[] = $result;
        }

        return array_reverse($data);
    }
}

