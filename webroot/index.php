<?php
define('DEBUG', 'on');
define('WEBPATH', __DIR__);
define('ROOT_PATH', dirname(__DIR__));
/**
 * /vendor/autoload.php是Composer工具生成的
 * shell: composer update
 */
require dirname(__DIR__) . '/vendor/autoload.php';
/**
 * Swoole框架自动载入器初始化
 */
Swoole\Loader::vendorInit();
Swoole::$php->config->setPath(ROOT_PATH . '/configs');
Swoole::$php->runMVC();