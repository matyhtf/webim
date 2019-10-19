<?php
define('DEBUG', 'on');
define('WEBPATH', __DIR__.'/webroot');
define('ROOT_PATH', __DIR__);

/**
 * /vendor/autoload.php是Composer工具生成的
 * shell: composer update
 */
require __DIR__.'/vendor/autoload.php';

/**
 * 注册命名空间到自动载入器中
 */
$app = SPF\App::getInstance(__DIR__);
$app->loader->addNameSpace('WebIM', __DIR__.'/src/');
$app->config->setPath(__DIR__.'/configs');

SPF\App::$enableCoroutine = true;

$server = new WebIM\Server($app->config['webim']);
$server->run();

//设置PID文件的存储路径
//Swoole\Network\Server::setPidFile(__DIR__ . '/log/webim_server.pid');
