<?php

$smallDb = [
    'host'      => 'localhost', // exec('netstat -rn | grep "^0.0.0.0 " | cut -d " " -f10'),
    'driver'    => 'mysql',
    'database'  => 'juwai_homestead_2',
    'username'  => 'root',
    'password'  => 'secret',
    'charset'   => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix'    => '',
];

$bigDb = array_merge($smallDb, ['database' => 'juwai_homestead']);

return $smallDb;