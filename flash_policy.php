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

$serv->on('receive', function (swoole_server $serv, $fd, $from_id, $data) {
    echo "[#".posix_getpid()."]\tClient[$fd]: $data\n";
    $serv->send($fd, "<cross-domain-policy>
<allow-access-from domain=\"*\" to-ports=\"9503\" />
</cross-domain-policy>\n\0");
});

$serv->on('close', function ($serv, $fd, $from_id) {
    echo "[#".posix_getpid()."]\tClient@[$fd:$from_id]: Close.\n";
});

$serv->start();
