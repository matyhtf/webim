<?php
define('DEBUG', 'on');
define("WEBPATH", realpath(__DIR__ . '/../'));

require_once __DIR__.'/../vendor/autoload.php';
Swoole\Loader::vendor_init();
Swoole\Loader::addNameSpace('WebIM', __DIR__.'/src/');
Swoole::$php->config->setPath(__DIR__.'/apps/configs');

$AppSvr = new Swoole\Protocol\HttpServer();
$AppSvr->loadSetting(__DIR__.'/../swoole.ini'); //加载配置文件
$AppSvr->setDocumentRoot(__DIR__);
$AppSvr->setLogger(new \Swoole\Log\EchoLog(true)); //Logger

$server = new \Swoole\Network\Server('0.0.0.0', 8888);
$server->setProtocol($AppSvr);
$server->run(array('worker_num' => 1));
