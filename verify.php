<?php
require __DIR__ . '/config/config.php';
require __DIR__ . '/db_connect.php';
require __DIR__ . '/src/i18n.php';
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$token = trim($_GET['token'] ?? '');
$message = t('verify_invalid_link');

if ($token !== '') {
    $stmt = $pdo->prepare('SELECT id, verification_token_created_at, email_verified_at FROM users WHERE verification_token = :token LIMIT 1');
    $stmt->execute(['token' => $token]);
    $user = $stmt->fetch();

    if ($user) {
        if ($user['email_verified_at']) {
            $message = t('verify_already_verified');
        } else {
            $createdAt = new DateTime($user['verification_token_created_at'], new DateTimeZone('UTC'));
            $expiresAt = (clone $createdAt)->modify('+24 hours');
            $now = new DateTime('now', new DateTimeZone('UTC'));

            if ($now > $expiresAt) {
                $message = t('verify_expired');
            } else {
                $update = $pdo->prepare('UPDATE users SET email_verified_at = :verified_at, verification_token = NULL, verification_token_created_at = NULL WHERE id = :id');
                $update->execute([
                    'verified_at' => $now->format('Y-m-d H:i:s'),
                    'id' => $user['id'],
                ]);
                $message = t('verify_success');
            }
        }
    }
}
?>
<!doctype html>
<html lang="<?php echo h(current_locale()); ?>">
<head>
    <meta charset="UTF-8">
    <title><?php echo h(t('verify_page_title')); ?></title>
</head>
<body>
<h1><?php echo h(t('verify_heading')); ?></h1>
<p><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
<a href="/login.php"><?php echo h(t('verify_login_link')); ?></a>
</body>
</html>
