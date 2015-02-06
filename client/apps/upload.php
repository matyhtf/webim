<?php
if (!defined('SWOOLE_SERVER'))
{
    define('DEBUG', 'on');
    define('WEBPATH', realpath(__DIR__ . '/../../'));
    require_once WEBPATH . '/vendor/autoload.php';
    Swoole\Loader::vendor_init();
    Swoole::$php->config->setPath(__DIR__.'/configs');
}

/**
 * 用flash添加照片
 */
if ($_FILES)
{
    global $php;
    $php->upload->thumb_width = 136;
    $php->upload->thumb_height = 136;
    $php->upload->thumb_qulitity = 100;
    $up_pic = $php->upload->save('Filedata');
    if (empty($up_pic))
    {
        echo '上传失败，请重新上传！ Error:' . $php->upload->error_msg;
    }
    echo json_encode($up_pic);
}
else
{
    echo "Bad Request\n";
}
