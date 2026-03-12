<?php
require dirname(__DIR__) . '/_bootstrap.php';

global $pdo;
app_require_method('POST');
$auth = app_require_auth($pdo);
$data = app_request_data();
app_require_fields($data, ['session_id']);

$targetId = (int) $data['session_id'];
if ($targetId === (int) $auth['session']['id']) {
    app_fail('不能踢下当前设备，请使用退出登录接口', 422);
}

app_revoke_session($pdo, $targetId, (int) $auth['user']['id']);
app_ok(['message' => '设备已下线']);
