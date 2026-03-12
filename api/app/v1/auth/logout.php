<?php
require dirname(__DIR__) . '/_bootstrap.php';

global $pdo;
app_require_method('POST');
$auth = app_require_auth($pdo);
app_revoke_session($pdo, (int) $auth['session']['id'], (int) $auth['user']['id']);
app_ok(['message' => '已退出当前设备']);
