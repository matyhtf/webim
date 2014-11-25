<?php
define('DEBUG', 'on');
define('WEBPATH', __DIR__);

/**
 * /vendor/autoload.php是Composer工具生成的
 * shell: composer update
 */
require __DIR__.'/vendor/autoload.php';

/**
 * Swoole框架自动载入器初始化
 */
Swoole\Loader::vendor_init();

/**
 * 注册命名空间到自动载入器中
 */
Swoole\Loader::addNameSpace('WebIM', __DIR__.'/src/');

$config = require __DIR__.'/config.php';

$webim = new WebIM\Server($config);
$webim->loadSetting(__DIR__."/swoole.ini"); //加载配置文件

/**
 * webim必须使用swoole扩展
 */
$server = new Swoole\Network\Server($config['server']['host'], $config['server']['port']);
$server->setProtocol($webim);
$server->run($config['swoole']);
