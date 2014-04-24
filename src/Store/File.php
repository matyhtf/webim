<?php
namespace WebIM\Store;

class File
{
    static $shm_dir = '/dev/shm/swoole_webim/';
    protected $online_dir;
    protected $save_dir;
    protected $history_fp;
    protected $history = array();
    protected $history_max_size = 100;
    protected $history_write_count = 0;

    protected $last_day;

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

    function __construct($save_dir)
    {
        if (!is_dir(self::$shm_dir))
        {
            if (!mkdir(self::$shm_dir, 0777, true))
            {
                rw_deny:
                trigger_error("can not read/write dir[".self::$shm_dir."]", E_ERROR);
                return;
            }
        }
        else
        {
            self::clearDir(self::$shm_dir);
            $this->online_dir = self::$shm_dir.'/online/';
            if (!is_dir($this->online_dir))
            {
                if (!mkdir($this->online_dir, 0777, true))
                {
                    goto rw_deny;
                }
            }
        }
        $this->last_day = date('d');
        $this->save_dir = $save_dir;

        $this->loadHistory();

        $this->history_fp = fopen($save_dir.'/'.date('Ymd').'.log', 'a+');
        if (!$this->history_fp)
        {
            trigger_error("can not write file[".$save_dir."]", E_ERROR);
            return;
        }
    }

    /**
     * 加载历史聊天记录
     */
    protected function loadHistory()
    {
        $file = $this->save_dir.'/'.date('Ymd').'.log';
        if (!is_file($file)) return;
        $handle = fopen($file, "r");
        if ($handle)
        {
            while (($line = fgets($handle, 4096)) !== false)
            {
                $log = json_decode($line);
                if (!$log) continue;
                $this->history[] = $log;
                if (count($this->history) > $this->history_max_size)
                {
                    array_shift($this->history);
                }
            }
            fclose($handle);
        }
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
        $ret = file_get_contents($this->online_dir.$userid);
        $info = unserialize($ret);
        return $info;
    }

    function addHistory($userid, $msg)
    {
        $info = $this->getUser($userid);

        $log['user'] = $info;
        $log['msg'] = $msg;
        $log['time'] = time();

        $this->history[] = $log;

        if (count($this->history) > $this->history_max_size)
        {
            //丢弃历史消息
            array_shift($this->history);
        }
        fwrite($this->history_fp, json_encode($log).PHP_EOL);
        $this->history_write_count ++;

        if ($this->history_write_count % 1000)
        {
            $day = date('d');
            if ($day != $this->last_day)
            {
                fclose($this->history_fp);
                $this->history_write_count = 0;
                $this->history_fp = fopen($this->save_dir.'/'.date('Ymd').'.log', 'a+');
            }
        }
    }

    function getHistory($offset = 0, $num = 100)
    {
        return $this->history;
    }
}