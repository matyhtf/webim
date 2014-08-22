<?php
define('DEBUG', 'on');
define("WEBPATH", realpath(__DIR__ . '/../'));

require __DIR__.'/vendor/autoload.php';
Swoole\Loader::vendor_init();
Swoole\Loader::setRootNS('WebIM', __DIR__.'/src/');

$config = require __DIR__.'/config.php';

//将配置写入config.js
$config_js = <<<HTML
var webim = {
    'server' : {$config['server']['url']}
}
HTML;
file_put_contents(__DIR__ . '/client/config.js', $config_js);

$webim = new WebIM\Server();
$webim->loadSetting(__DIR__."/swoole.ini"); //加载配置文件
$webim->setLogger(new Swoole\Log\FileLog($config['webim']['log_file']));   //Logger

/**
 * 使用文件或redis存储聊天信息
 */
$webim->setStore(new WebIM\Store\File($config['webim']['data_dir']));

/**
 * webim必须使用swoole扩展
 */
$server = new Swoole\Network\Server($config['server']['host'], $config['server']['port']);
$server->setProtocol($webim);
$server->run($config['swoole']);
