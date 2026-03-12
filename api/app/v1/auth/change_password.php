<?php
require dirname(__DIR__) . '/_bootstrap.php';

global $pdo;
app_require_method('POST');
$auth = app_require_auth($pdo);
$data = app_request_data();
app_require_fields($data, ['old_password', 'new_password', 'new_password_confirm']);

if (!password_verify((string) $data['old_password'], (string) $auth['user']['password'])) {
    app_fail('旧密码错误', 422);
}
if ((string) $data['new_password'] !== (string) $data['new_password_confirm']) {
    app_fail('两次输入的新密码不一致', 422);
}
app_validate_password((string) $data['new_password']);

$stmt = $pdo->prepare('UPDATE users SET password = :password, updated_at = NOW(), remember_token = NULL WHERE id = :id');
$stmt->execute([
    ':password' => password_hash((string) $data['new_password'], PASSWORD_DEFAULT),
    ':id' => (int) $auth['user']['id'],
]);
app_revoke_other_sessions($pdo, (int) $auth['user']['id'], (int) $auth['session']['id']);

app_ok(['message' => '密码修改成功']);
