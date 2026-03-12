<?php
require dirname(__DIR__) . '/_bootstrap.php';

global $pdo;
app_require_method('POST');
$data = app_request_data();
app_require_fields($data, ['email']);

$stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
$stmt->bindValue(':email', trim((string) $data['email']), PDO::PARAM_STR);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    app_fail('邮箱不存在', 404);
}

$result = app_password_reset_create($pdo, $user, app_client_ip());
app_ok([
    'message' => '验证码已发送',
    'expires_in' => $result['expires_in'],
]);
