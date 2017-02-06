<?php
define('DEBUG', 'on');
define('WEBPATH', __DIR__);
define('ROOT_PATH', dirname(__DIR__));

/**
 * /vendor/autoload.php是Composer工具生成的
 * shell: composer update
 */
require ROOT_PATH.'/vendor/autoload.php';
/**
 * Swoole框架自动载入器初始化
 */
Swoole\Loader::vendorInit();
Swoole\Loader::addNameSpace('WebIM', __DIR__.'/src/');
Swoole::$php->config->setPath(ROOT_PATH . '/configs');
$config = Swoole::$php->config['webim'];

$AppSvr = new Swoole\Protocol\AppServer();
$AppSvr->loadSetting(ROOT_PATH.'/swoole.ini'); //加载配置文件
$AppSvr->setDocumentRoot(__DIR__);
$AppSvr->setAppPath(__DIR__.'/apps/');
$AppSvr->setLogger(new \Swoole\Log\EchoLog(true)); //Logger

$origin = parse_url($config['server']['origin']);
if (empty($origin['port']))
{
    $origin['port'] = 80;
}

$server = new \Swoole\Network\Server('0.0.0.0', $origin['port']);
$server->setProtocol($AppSvr);
$server->run(array('worker_num' => 1));
