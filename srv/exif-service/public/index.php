<?php

$config = require __DIR__ . '/../config/config.php';
require __DIR__ . '/../src/ExifService.php';

$service = new ExifService($config);
$service->handle();
