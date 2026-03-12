<?php
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/photo_feed_service.php';

function app_config(): array
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../config/config.php';
    }
    return $config;
}

function app_json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function app_ok(array $data = [], int $status = 200): void
{
    app_json_response(['success' => true] + $data, $status);
}

function app_fail(string $message, int $status = 400, array $extra = []): void
{
    app_json_response(['success' => false, 'error' => $message] + $extra, $status);
}

function app_require_method(string $method): void
{
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== strtoupper($method)) {
        app_fail('请求方法不正确', 405);
    }
}

function app_request_data(): array
{
    static $data = null;
    if ($data !== null) {
        return $data;
    }

    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw ?: '[]', true);
        $data = is_array($decoded) ? $decoded : [];
        return $data;
    }

    $data = $_POST;
    return $data;
}

function app_require_fields(array $data, array $fields): void
{
    foreach ($fields as $field) {
        if (!array_key_exists($field, $data) || trim((string) $data[$field]) === '') {
            app_fail('缺少必要参数: ' . $field, 422);
        }
    }
}

function app_client_ip(): string
{
    foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
        $value = trim((string) ($_SERVER[$key] ?? ''));
        if ($value === '') {
            continue;
        }
        if ($key === 'HTTP_X_FORWARDED_FOR') {
            $parts = array_map('trim', explode(',', $value));
            return (string) ($parts[0] ?? '');
        }
        return $value;
    }
    return '';
}

function app_bearer_token(): string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(.+)/i', $header, $matches)) {
        return trim($matches[1]);
    }
    return '';
}

function app_hash_token(string $token): string
{
    return hash('sha256', $token);
}

function app_random_token(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}

function app_random_code(int $digits = 6): string
{
    $min = (int) pow(10, $digits - 1);
    $max = (int) pow(10, $digits) - 1;
    return (string) random_int($min, $max);
}

function app_access_token_ttl(): int
{
    return 2 * 60 * 60;
}

function app_refresh_token_ttl(): int
{
    return 30 * 24 * 60 * 60;
}

function app_password_min_length(): int
{
    return 6;
}

function app_normalize_device_meta(array $input): array
{
    $deviceId = trim((string) ($input['device_id'] ?? ''));
    if ($deviceId === '') {
        $deviceId = hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown') . '|' . microtime(true));
    }

    return [
        'device_id' => substr($deviceId, 0, 128),
        'device_name' => substr(trim((string) ($input['device_name'] ?? 'Unknown Device')), 0, 120),
        'platform' => substr(trim((string) ($input['platform'] ?? 'android')), 0, 32),
        'system_version' => substr(trim((string) ($input['system_version'] ?? '')), 0, 64),
        'app_version' => substr(trim((string) ($input['app_version'] ?? '')), 0, 32),
        'push_token' => trim((string) ($input['push_token'] ?? '')),
    ];
}

function app_user_payload(array $user): array
{
    return [
        'id' => (int) $user['id'],
        'username' => (string) $user['username'],
        'email' => (string) $user['email'],
        'email_verified' => !empty($user['email_verified_at']),
        'is_admin' => !empty($user['is_admin']),
        'sys_admin' => !empty($user['sys_admin']),
    ];
}

function app_load_user(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
    $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user ?: null;
}

function app_find_user_by_login(PDO $pdo, string $login): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :login OR email = :login LIMIT 1');
    $stmt->bindValue(':login', $login, PDO::PARAM_STR);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user ?: null;
}

function app_update_user_activity(PDO $pdo, int $userId): void
{
    $stmt = $pdo->prepare('UPDATE users SET last_active = NOW() WHERE id = :id');
    $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
    $stmt->execute();
}

function app_store_push_token(PDO $pdo, int $userId, int $sessionId, string $pushToken, string $platform): void
{
    if ($pushToken === '') {
        return;
    }

    $stmt = $pdo->prepare('INSERT INTO app_push_tokens (user_id, session_id, platform, push_token, created_at, updated_at)
        VALUES (:user_id, :session_id, :platform, :push_token, NOW(), NOW())
        ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), session_id = VALUES(session_id), platform = VALUES(platform), updated_at = NOW()');
    $stmt->execute([
        ':user_id' => $userId,
        ':session_id' => $sessionId,
        ':platform' => $platform,
        ':push_token' => $pushToken,
    ]);
}

function app_issue_session(PDO $pdo, array $user, array $deviceMeta): array
{
    $accessToken = app_random_token(24);
    $refreshToken = app_random_token(32);
    $accessExpiresAt = date('Y-m-d H:i:s', time() + app_access_token_ttl());
    $refreshExpiresAt = date('Y-m-d H:i:s', time() + app_refresh_token_ttl());

    $stmt = $pdo->prepare('INSERT INTO app_user_sessions (
            user_id, device_id, device_name, platform, system_version, app_version,
            ip_address, user_agent, access_token_hash, refresh_token_hash,
            access_expires_at, refresh_expires_at, last_seen_at, created_at, updated_at
        ) VALUES (
            :user_id, :device_id, :device_name, :platform, :system_version, :app_version,
            :ip_address, :user_agent, :access_token_hash, :refresh_token_hash,
            :access_expires_at, :refresh_expires_at, NOW(), NOW(), NOW()
        )');
    $stmt->execute([
        ':user_id' => (int) $user['id'],
        ':device_id' => $deviceMeta['device_id'],
        ':device_name' => $deviceMeta['device_name'],
        ':platform' => $deviceMeta['platform'],
        ':system_version' => $deviceMeta['system_version'],
        ':app_version' => $deviceMeta['app_version'],
        ':ip_address' => substr(app_client_ip(), 0, 45),
        ':user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ':access_token_hash' => app_hash_token($accessToken),
        ':refresh_token_hash' => app_hash_token($refreshToken),
        ':access_expires_at' => $accessExpiresAt,
        ':refresh_expires_at' => $refreshExpiresAt,
    ]);

    $sessionId = (int) $pdo->lastInsertId();
    app_store_push_token($pdo, (int) $user['id'], $sessionId, $deviceMeta['push_token'], $deviceMeta['platform']);

    return [
        'session_id' => $sessionId,
        'access_token' => $accessToken,
        'refresh_token' => $refreshToken,
        'access_token_expires_at' => $accessExpiresAt,
        'refresh_token_expires_at' => $refreshExpiresAt,
        'device' => [
            'id' => $sessionId,
            'device_id' => $deviceMeta['device_id'],
            'device_name' => $deviceMeta['device_name'],
            'platform' => $deviceMeta['platform'],
            'system_version' => $deviceMeta['system_version'],
            'app_version' => $deviceMeta['app_version'],
            'is_current' => true,
        ],
    ];
}

function app_session_by_access_token(PDO $pdo, string $accessToken): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM app_user_sessions WHERE access_token_hash = :token_hash LIMIT 1');
    $stmt->bindValue(':token_hash', app_hash_token($accessToken), PDO::PARAM_STR);
    $stmt->execute();
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    return $session ?: null;
}

function app_session_by_refresh_token(PDO $pdo, string $refreshToken): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM app_user_sessions WHERE refresh_token_hash = :token_hash LIMIT 1');
    $stmt->bindValue(':token_hash', app_hash_token($refreshToken), PDO::PARAM_STR);
    $stmt->execute();
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    return $session ?: null;
}

function app_is_session_active(array $session, string $type = 'access'): bool
{
    if (!empty($session['revoked_at'])) {
        return false;
    }

    $key = $type === 'refresh' ? 'refresh_expires_at' : 'access_expires_at';
    return strtotime((string) $session[$key]) >= time();
}

function app_require_auth(PDO $pdo): array
{
    $token = app_bearer_token();
    if ($token === '') {
        app_fail('未登录或登录已失效', 401);
    }

    $session = app_session_by_access_token($pdo, $token);
    if (!$session || !app_is_session_active($session, 'access')) {
        app_fail('未登录或登录已失效', 401);
    }

    $user = app_load_user($pdo, (int) $session['user_id']);
    if (!$user || !empty($user['is_banned'])) {
        app_fail('账号不可用', 403);
    }

    $stmt = $pdo->prepare('UPDATE app_user_sessions SET last_seen_at = NOW(), updated_at = NOW() WHERE id = :id');
    $stmt->bindValue(':id', (int) $session['id'], PDO::PARAM_INT);
    $stmt->execute();
    app_update_user_activity($pdo, (int) $user['id']);

    return [
        'user' => $user,
        'session' => $session,
    ];
}

function app_revoke_session(PDO $pdo, int $sessionId, int $userId): void
{
    $stmt = $pdo->prepare('UPDATE app_user_sessions SET revoked_at = NOW(), updated_at = NOW() WHERE id = :id AND user_id = :user_id');
    $stmt->execute([
        ':id' => $sessionId,
        ':user_id' => $userId,
    ]);
}

function app_revoke_other_sessions(PDO $pdo, int $userId, int $currentSessionId): void
{
    $stmt = $pdo->prepare('UPDATE app_user_sessions SET revoked_at = NOW(), updated_at = NOW() WHERE user_id = :user_id AND id <> :current_id AND revoked_at IS NULL');
    $stmt->execute([
        ':user_id' => $userId,
        ':current_id' => $currentSessionId,
    ]);
}

function app_list_user_devices(PDO $pdo, int $userId, int $currentSessionId): array
{
    $stmt = $pdo->prepare('SELECT id, device_id, device_name, platform, system_version, app_version, ip_address, created_at, last_seen_at, revoked_at
        FROM app_user_sessions
        WHERE user_id = :user_id
        ORDER BY COALESCE(last_seen_at, created_at) DESC, id DESC');
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();

    return array_map(static function (array $row) use ($currentSessionId): array {
        return [
            'id' => (int) $row['id'],
            'device_id' => (string) $row['device_id'],
            'device_name' => (string) $row['device_name'],
            'platform' => (string) $row['platform'],
            'system_version' => (string) $row['system_version'],
            'app_version' => (string) $row['app_version'],
            'ip_address' => (string) $row['ip_address'],
            'created_at' => (string) $row['created_at'],
            'last_seen_at' => (string) $row['last_seen_at'],
            'revoked_at' => $row['revoked_at'] !== null ? (string) $row['revoked_at'] : null,
            'is_current' => (int) $row['id'] === $currentSessionId,
            'is_active' => $row['revoked_at'] === null,
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function app_create_notification(PDO $pdo, int $userId, string $type, string $title, string $body, array $payload = []): void
{
    $stmt = $pdo->prepare('INSERT INTO app_notifications (user_id, type, title, body, payload_json, is_read, created_at)
        VALUES (:user_id, :type, :title, :body, :payload_json, 0, NOW())');
    $stmt->execute([
        ':user_id' => $userId,
        ':type' => $type,
        ':title' => $title,
        ':body' => $body,
        ':payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
}

function app_validate_password(string $password): void
{
    if (mb_strlen($password) < app_password_min_length()) {
        app_fail('密码长度不能少于 ' . app_password_min_length() . ' 位', 422);
    }
}

function app_password_reset_is_rate_limited(PDO $pdo, string $email, string $ipAddress): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM app_password_reset_codes
        WHERE requested_at >= (NOW() - INTERVAL 1 HOUR)
        AND (email = :email OR ip_address = :ip_address)');
    $stmt->execute([
        ':email' => $email,
        ':ip_address' => $ipAddress,
    ]);
    return (int) $stmt->fetchColumn() >= 10;
}

function app_password_reset_create(PDO $pdo, array $user, string $ipAddress): array
{
    if (app_password_reset_is_rate_limited($pdo, (string) $user['email'], $ipAddress)) {
        app_fail('请求过于频繁，请 1 小时后再试', 429);
    }

    $code = app_random_code(6);
    $stmt = $pdo->prepare('INSERT INTO app_password_reset_codes (
        user_id, email, code_hash, ip_address, requested_at, expires_at, attempt_count
    ) VALUES (
        :user_id, :email, :code_hash, :ip_address, NOW(), DATE_ADD(NOW(), INTERVAL 5 MINUTE), 0
    )');
    $stmt->execute([
        ':user_id' => (int) $user['id'],
        ':email' => (string) $user['email'],
        ':code_hash' => app_hash_token($code),
        ':ip_address' => $ipAddress,
    ]);

    if (!send_password_reset_code_email((string) $user['email'], $code)) {
        app_fail('验证码邮件发送失败，请稍后再试', 500);
    }

    return ['expires_in' => 300];
}

function app_password_reset_consume(PDO $pdo, string $email, string $code, string $newPassword): void
{
    app_validate_password($newPassword);

    $stmt = $pdo->prepare('SELECT * FROM app_password_reset_codes
        WHERE email = :email AND consumed_at IS NULL
        ORDER BY id DESC
        LIMIT 1');
    $stmt->bindValue(':email', $email, PDO::PARAM_STR);
    $stmt->execute();
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        app_fail('验证码无效或已过期', 422);
    }
    if (!empty($record['blocked_until']) && strtotime((string) $record['blocked_until']) > time()) {
        app_fail('验证码已锁定，请 1 小时后再试', 429);
    }
    if (strtotime((string) $record['expires_at']) < time()) {
        app_fail('验证码无效或已过期', 422);
    }

    if (!hash_equals((string) $record['code_hash'], app_hash_token($code))) {
        $attempts = (int) $record['attempt_count'] + 1;
        $blockedUntil = $attempts >= 5 ? date('Y-m-d H:i:s', time() + 3600) : null;
        $update = $pdo->prepare('UPDATE app_password_reset_codes SET attempt_count = :attempt_count, blocked_until = :blocked_until WHERE id = :id');
        $update->bindValue(':attempt_count', $attempts, PDO::PARAM_INT);
        $update->bindValue(':blocked_until', $blockedUntil, $blockedUntil === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $update->bindValue(':id', (int) $record['id'], PDO::PARAM_INT);
        $update->execute();
        app_fail($attempts >= 5 ? '验证码错误次数过多，请 1 小时后再试' : '验证码错误', 422);
    }

    $pdo->beginTransaction();
    try {
        $updateUser = $pdo->prepare('UPDATE users SET password = :password, updated_at = NOW(), remember_token = NULL WHERE id = :id');
        $updateUser->execute([
            ':password' => password_hash($newPassword, PASSWORD_DEFAULT),
            ':id' => (int) $record['user_id'],
        ]);

        $consume = $pdo->prepare('UPDATE app_password_reset_codes SET consumed_at = NOW() WHERE id = :id');
        $consume->bindValue(':id', (int) $record['id'], PDO::PARAM_INT);
        $consume->execute();

        $revoke = $pdo->prepare('UPDATE app_user_sessions SET revoked_at = NOW(), updated_at = NOW() WHERE user_id = :user_id AND revoked_at IS NULL');
        $revoke->bindValue(':user_id', (int) $record['user_id'], PDO::PARAM_INT);
        $revoke->execute();
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        app_fail('重置密码失败: ' . $e->getMessage(), 500);
    }
}
