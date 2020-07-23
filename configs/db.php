<?php
$db['master'] = array(
    'type'       => SPF\Database::TYPE_MYSQLi,
    'host'       => "mysql",
    'port'       => 3306,
    'dbms'       => 'mysql',
    'engine'     => 'InnoDB',
    'user'       => "root",
    'password'     => "root",
    'database'       => "webim",
    'charset'    => "utf8mb4",
    'setname'    => true,
    'persistent' => false, //MySQL长连接
);
return $db;