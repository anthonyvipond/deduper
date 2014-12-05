<?php

$smallDb = [
    'host'      => exec('netstat -rn | grep "^0.0.0.0 " | cut -d " " -f10'),
    'driver'    => 'mysql',
    'database'  => 'testdb',
    'username'  => 'root',
    'password'  => '',
    'charset'   => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix'    => '',
];

$bigDb = array_merge($smallDb, ['database' => 'juwai_staging']);

return $smallDb;