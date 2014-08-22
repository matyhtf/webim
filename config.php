<?php
$config['server'] = array(
    //监听的HOST
    'host' => '0.0.0.0',
    //监听的端口
    'port' => '9503',
    //WebSocket的URL地址，供浏览器使用的
    'url' => 'ws://webim.swoole.com:9503',
);

$config['swoole'] = array(
    'log_file' => __DIR__.'/log/swoole.log',
    'worker_num' => 4,
    'max_request' => 100000,
    'task_worker_num' => 1,
    //'daemonize' => 0,
);

$config['webim'] = array(
    'data_dir' => __DIR__.'/data/',
    'log_file' => __DIR__.'/log/webim.log',
);

return $config;