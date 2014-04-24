<?php
$serv = new swoole_server("0.0.0.0", 843);
$serv->set(array(
	'worker_num' => 1,
	//'daemonize' => true,
	//'log_file' => '/tmp/swoole.log'
));

$serv->on('connect', function ($serv, $fd, $from_id){
    echo "[#".posix_getpid()."]\tClient@[$fd:$from_id]: Connect.\n";
});

$serv->on('receive', function ($serv, $fd, $from_id, $data) {
	global $xml;
    echo "[#".posix_getpid()."]\tClient[$fd]: $data\n";
    $serv->send($fd, $xml);
    //$serv->close($fd);
});
$serv->on('close', function ($serv, $fd, $from_id) {
    echo "[#".posix_getpid()."]\tClient@[$fd:$from_id]: Close.\n";
});
$serv->start();
