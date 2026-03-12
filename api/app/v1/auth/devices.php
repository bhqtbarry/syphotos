<?php
require dirname(__DIR__) . '/_bootstrap.php';

global $pdo;
app_require_method('GET');
$auth = app_require_auth($pdo);

app_ok([
    'items' => app_list_user_devices($pdo, (int) $auth['user']['id'], (int) $auth['session']['id']),
]);
