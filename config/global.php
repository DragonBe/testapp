<?php
$config = array (
    'db' => array (
        'dsn' => 'mysql:host=127.0.0.1;port=3306;dbname=test;charset=utf8',
        'username' => '',
        'password' => '',
    ),
    'github' => array (
        'api_key' => '',
        'api_secret' => '',
    ),
);

if (file_exists(__DIR__ . '/local.php')) {
    require_once __DIR__ . '/local.php';
}
