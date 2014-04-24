<?php
namespace WebIM\Store;

class Redis
{
    /**
     * @var \redis
     */
    protected $redis;

    static $prefix = "webim_";

    function __construct($host = '127.0.0.1', $port = 6379, $timeout = 0.0)
    {
        $redis = new \redis;
        $redis->connect($host, $port, $timeout);
        $this->redis = $redis;
    }

    function login($client_id, $info)
    {
        $this->redis->set(self::$prefix.'client_'.$client_id, serialize($info));
        $this->redis->sAdd(self::$prefix.'online', $client_id);
    }

    function logout($client_id)
    {
        $this->redis->del(self::$prefix.'client_', $client_id);
        $this->redis->sRemove(self::$prefix.'online', $client_id);
    }

    function getOnlineUsers()
    {
        return $this->redis->sMembers(self::$prefix.'online');
    }

    function getUsers($users)
    {
        $keys = array();
        $ret = array();

        foreach($users as $v)
        {
            $keys[] = self::$prefix.'client_'.$v;
        }

        $info = $this->redis->mget($keys);
        foreach($info as $v)
        {
            $ret[] = unserialize($v);
        }
        return $ret;
    }

    function getUser($userid)
    {
        $ret = $this->redis->get($userid);
        $info = unserialize($ret);
        return $info;
    }
}