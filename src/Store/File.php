<?php
namespace WebIM\Store;

class File
{
    protected $online_dir;
    protected $save_dir;
    protected $last_day;

    const PREFIX = 'webim_';

    static function clearDir($dir)
    {
        $n = 0;
        if ($dh = opendir($dir))
        {
            while (($file = readdir($dh)) !== false)
            {
                if ($file == '.' or $file == '..')
                {
                    continue;
                }
                if (is_file($dir . $file)) {
                    unlink($dir . $file);
                    //echo "delete ".$dir . $file.PHP_EOL;
                    $n++;
                }
                if (is_dir($dir . $file)) {
                    self::clearDir($dir . $file . '/');
                    $n++;
                    //echo "rmdir ".$dir . $file . PHP_EOL;
                    //rmdir($dir . $file . '/');
                }
            }
        }
        closedir($dh);
        return $n;
    }

    function checkDir($dir, $clear_file = false)
    {
        if (!is_dir($dir))
        {
            if (!mkdir($dir, 0777, true))
            {
                rw_deny:
                trigger_error("can not read/write dir[".$dir."]", E_ERROR);
                return;
            }
        }
        else if ($clear_file)
        {
            self::clearDir($dir);
        }
    }

    function __construct($save_dir, $online_dir = '/dev/shm/swoole_webim/')
    {
        $this->online_dir = $online_dir;
        $this->checkDir($this->online_dir, true);

        $this->last_day = date('d');
        $this->save_dir = $save_dir;

        $this->checkDir($save_dir);

    }

    function login($client_id, $info)
    {
        file_put_contents($this->online_dir.$client_id, serialize($info));
    }

    function logout($client_id)
    {
        unlink($this->online_dir.$client_id);
    }

    function getOnlineUsers()
    {
        $online_users = array_slice(scandir($this->online_dir), 2);
        return $online_users;
    }

    function getUsers($users)
    {
        $ret = array();
        foreach($users as $v)
        {
            $ret[] = $this->getUser($v);
        }
        return $ret;
    }

    function getUser($userid)
    {
        if (!is_file($this->online_dir.$userid))
        {
            return false;
        }
        $ret = file_get_contents($this->online_dir.$userid);
        $info = unserialize($ret);
        return $info;
    }

    function exists($userid)
    {
        return is_file($this->online_dir.$userid);
    }

    function addHistory($userid, $msg)
    {
        $info = $this->getUser($userid);

        $log['user'] = $info;
        $log['msg'] = $msg;
        $log['time'] = time();
        $log['type'] = empty($msg['type']) ? '' : $msg['type'];

        table(self::PREFIX.'history')->put(array(
            'name' => $info['name'],
            'avatar' => $info['avatar'],
            'msg' => json_encode($msg),
            'type' => empty($msg['type']) ? '' : $msg['type'],
        ));
    }

    function getHistory($offset = 0, $num = 100)
    {
        $data = array();
        $list = table(self::PREFIX.'history')->gets(array('limit' => 100,));
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