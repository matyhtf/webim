<?php
namespace App\Controller;

use Swoole\Client\CURL;

class Page extends \Swoole\Controller
{
    function index()
    {
        $this->session->start();
        if (!empty($_SESSION['isLogin']))
        {
            chatroom:
            $this->http->redirect('/page/chatroom/');
            return;
        }

        if (!empty($_GET['token']))
        {
            $curl = new CURL();
            $user = $curl->get($this->config['login']['get_user_info'] . '?token=' . urlencode($_GET['token']));
            if (empty($user))
            {
                login:
                $config = require ROOT_PATH.'/config.php';
                $this->http->redirect($this->config['login']['passport'].'?return_token=1&refer='.urlencode($config['server']['origin']));
            }
            else
            {
                $_SESSION['isLogin'] = 1;
                $_SESSION['user'] = json_decode($user, true);
                goto chatroom;
            }
        }
        else
        {
            goto login;
        }
    }

    function chatroom()
    {
        $this->session->start();
        if (empty($_SESSION['isLogin']))
        {
            $this->http->redirect('/page/index/');
            return;
        }
        $user = $_SESSION['user'];
        $this->assign('user', $user);
        $this->assign('debug', 'true');
        $this->display('page/chatroom.php');
    }
}