<?php
require dirname(__DIR__) . '/_bootstrap.php';

global $pdo;
app_require_method('POST');
$auth = app_require_auth($pdo);
app_revoke_other_sessions($pdo, (int) $auth['user']['id'], (int) $auth['session']['id']);
app_ok(['message' => '已退出其他设备']);
