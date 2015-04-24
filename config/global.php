<?php
$config = array (
    'db' => array (
        'dsn' => 'mysql:127.0.0.1&dbname=test',
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
