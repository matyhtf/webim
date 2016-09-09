<?php
define('DEBUG', 'on');
define('WEBPATH', __DIR__);
define('ROOT_PATH', dirname(__DIR__));

if (is_file(ROOT_PATH.'/framework/libs/lib_config.php'))
{
    require ROOT_PATH.'/framework/libs/lib_config.php';
}
else
{
    require_once ROOT_PATH.'/vendor/autoload.php';
    Swoole\Loader::vendor_init();
}

Swoole\Loader::addNameSpace('WebIM', __DIR__.'/src/');
Swoole::$php->config->setPath(__DIR__.'/apps/configs');

$AppSvr = new Swoole\Protocol\AppServer();
$AppSvr->loadSetting(ROOT_PATH.'/swoole.ini'); //加载配置文件
$AppSvr->setDocumentRoot(__DIR__);
$AppSvr->setAppPath(__DIR__.'/apps/');
$AppSvr->setLogger(new \Swoole\Log\EchoLog(true)); //Logger

$server = new \Swoole\Network\Server('0.0.0.0', 8888);
$server->setProtocol($AppSvr);
$server->run(array('worker_num' => 1));
