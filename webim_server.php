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
 * Swoole框架自动载入器初始化
 */
Swoole\Loader::vendorInit();

/**
 * 注册命名空间到自动载入器中
 */
Swoole\Loader::addNameSpace('WebIM', __DIR__.'/src/');
Swoole::getInstance()->config->setPath(__DIR__.'/configs');

//设置PID文件的存储路径
Swoole\Network\Server::setPidFile(__DIR__ . '/log/webim_server.pid');

/**
 * 显示Usage界面
 * php app_server.php start|stop|reload
 */
Swoole\Network\Server::start(function ()
{
    $config = Swoole::getInstance()->config['webim'];
    $webim = new WebIM\Server($config);
    $webim->loadSetting(__DIR__ . "/swoole.ini"); //加载配置文件

    /**
     * webim必须使用swoole扩展
     */
    $server = new Swoole\Network\Server($config['server']['host'], $config['server']['port']);
    $server->setProcessName('webim-server');
    $server->setProtocol($webim);
    $server->run($config['swoole']);
});
