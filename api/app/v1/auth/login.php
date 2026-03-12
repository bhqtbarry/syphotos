<?php
require dirname(__DIR__) . '/_bootstrap.php';

global $pdo;
app_require_method('POST');
$data = app_request_data();
app_require_fields($data, ['login', 'password']);

$user = app_find_user_by_login($pdo, trim((string) $data['login']));
if (!$user || !password_verify((string) $data['password'], (string) $user['password'])) {
    app_fail('用户名/邮箱或密码错误', 401);
}
if (empty($user['email_verified_at'])) {
    app_fail('邮箱尚未验证', 403);
}
if (!empty($user['is_banned'])) {
    app_fail('账号已被禁用', 403);
}

$deviceMeta = app_normalize_device_meta($data);
$session = app_issue_session($pdo, $user, $deviceMeta);
app_update_user_activity($pdo, (int) $user['id']);

app_ok([
    'user' => app_user_payload($user),
    'auth' => $session,
]);
