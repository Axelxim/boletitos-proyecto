<?php

// Fuerza IPv4 antes de que CodeIgniter cargue el .env en cada peticion local.
putenv('database.default.hostname=31.97.208.123');
$_ENV['database.default.hostname'] = '31.97.208.123';
$_SERVER['database.default.hostname'] = '31.97.208.123';

require __DIR__ . '/vendor/codeigniter4/framework/system/rewrite.php';
