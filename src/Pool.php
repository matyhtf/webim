<?php
namespace WebIM;
use Swoole;
use RuntimeException;

class Pool
{
    /**
     * @var \Swoole\Coroutine\Channel
     */
    protected $pool;
    protected $config;

    /**
     * @param int $size 连接池的尺寸
     */
    function __construct($config, $size = 100)
    {
        $this->pool = new Swoole\Coroutine\Channel($size);
        $this->config = $config;
        for ($i = 0; $i < $size; $i++)
        {
            $object = $this->create();
            if ($object == false)
            {
                throw new RuntimeException("failed to connect mysql server.");
            }
            else
            {
                $this->_put($object);
            }
        }
    }

    protected function _put($object)
    {
        $this->pool->push($object);
    }

    protected function _get()
    {
        return $this->pool->pop();
    }

    function __call($method, $args)
    {
        $object = $this->_get();
        $retval = $object->$method(...$args);
        $this->_put($object);
        return $retval;
    }
}