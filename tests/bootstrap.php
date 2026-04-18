<?php
$_ENV['APP_ENV'] = 'testing';
$_SERVER['APP_ENV'] = 'testing';
putenv('APP_ENV=testing');

require __DIR__ . '/../vendor/autoload.php';
