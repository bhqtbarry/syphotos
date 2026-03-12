<?php
require dirname(__DIR__) . '/_bootstrap.php';

global $pdo;
app_require_method('POST');
$data = app_request_data();
app_require_fields($data, ['email', 'code', 'new_password', 'new_password_confirm']);

if ((string) $data['new_password'] !== (string) $data['new_password_confirm']) {
    app_fail('两次输入的新密码不一致', 422);
}

app_password_reset_consume($pdo, trim((string) $data['email']), trim((string) $data['code']), (string) $data['new_password']);
app_ok(['message' => '密码已重置']);
