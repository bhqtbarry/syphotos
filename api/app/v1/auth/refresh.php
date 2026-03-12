<?php
require dirname(__DIR__) . '/_bootstrap.php';

global $pdo;
app_require_method('POST');
$data = app_request_data();
app_require_fields($data, ['refresh_token']);

$session = app_session_by_refresh_token($pdo, (string) $data['refresh_token']);
if (!$session || !app_is_session_active($session, 'refresh')) {
    app_fail('刷新令牌无效或已过期', 401);
}

$user = app_load_user($pdo, (int) $session['user_id']);
if (!$user || !empty($user['is_banned'])) {
    app_fail('账号不可用', 403);
}

app_revoke_session($pdo, (int) $session['id'], (int) $session['user_id']);
$deviceMeta = [
    'device_id' => (string) $session['device_id'],
    'device_name' => (string) $session['device_name'],
    'platform' => (string) $session['platform'],
    'system_version' => (string) $session['system_version'],
    'app_version' => (string) $session['app_version'],
    'push_token' => trim((string) ($data['push_token'] ?? '')),
];
$newSession = app_issue_session($pdo, $user, $deviceMeta);

app_ok([
    'user' => app_user_payload($user),
    'auth' => $newSession,
]);
