<?php
require dirname(__DIR__) . '/_bootstrap.php';

global $pdo;
app_require_method('POST');
$data = app_request_data();
app_require_fields($data, ['username', 'email', 'password', 'password_confirm']);

$username = trim((string) $data['username']);
$email = trim((string) $data['email']);
$password = (string) $data['password'];
$passwordConfirm = (string) $data['password_confirm'];

if ($password !== $passwordConfirm) {
    app_fail('两次输入的密码不一致', 422);
}
app_validate_password($password);

$check = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = :username OR email = :email');
$check->execute([
    ':username' => $username,
    ':email' => $email,
]);
if ((int) $check->fetchColumn() > 0) {
    app_fail('用户名或邮箱已存在', 422);
}

$verificationToken = bin2hex(random_bytes(32));
$stmt = $pdo->prepare('INSERT INTO users (
    username, email, password, is_admin, verification_token, verification_token_created_at, created_at
) VALUES (
    :username, :email, :password, 0, :verification_token, NOW(), NOW()
)');
$stmt->execute([
    ':username' => $username,
    ':email' => $email,
    ':password' => password_hash($password, PASSWORD_DEFAULT),
    ':verification_token' => $verificationToken,
]);

if (!send_verification_email($email, $verificationToken)) {
    app_fail('注册成功，但验证邮件发送失败，请稍后重试', 500);
}

app_ok([
    'message' => '注册成功，请先验证邮箱',
], 201);
