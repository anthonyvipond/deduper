<?php

use Illuminate\Database\Capsule\Manager as Capsule;

$db = new Capsule;

$db->addConnection([
    'host'      => 'localhost', // exec('netstat -rn | grep "^0.0.0.0 " | cut -d " " -f10'),
    'driver'    => 'mysql',
    'database'  => 'homestead',
    'username'  => 'root',
    'password'  => 'secret',
    'charset'   => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix'    => '',
]);

$db->setEventDispatcher(new Illuminate\Events\Dispatcher);
$db->setAsGlobal();
$db->bootEloquent();