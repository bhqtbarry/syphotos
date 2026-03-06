<?php

require __DIR__ . '/src/mail.php';
require 'db_connect.php';
require 'src/helpers.php';
require 'src/i18n.php';
session_start();


$message = t('verify_notice_default');
$email = trim($_GET['email'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if ($email !== '') {
        $token = bin2hex(random_bytes(32));
        $tokenCreatedAt = gmdate('Y-m-d H:i:s');

        $stmt = $pdo->prepare('UPDATE users SET verification_token = :token, verification_token_created_at = :token_created_at WHERE email = :email AND email_verified_at IS NULL');
        $stmt->execute([
            'token' => $token,
            'token_created_at' => $tokenCreatedAt,
            'email' => $email,
        ]);

        if ($stmt->rowCount() > 0) {
            send_verification_email($email, $token);
            $message = t('verify_notice_resent');
        } else {
            $message = t('verify_notice_failed');
        }
    } else {
        $message = t('verify_notice_enter_email');
    }
}
?>
<!doctype html>
<html lang="<?php echo h(current_locale()); ?>">
<head>
    <meta charset="UTF-8">
    <title><?php echo h(t('verify_notice_page_title')); ?></title>
</head>
<body>
<h1><?php echo h(t('verify_notice_heading')); ?></h1>
<p><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
<form method="post">
    <input type="email" name="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?php echo h(t('verify_notice_email_placeholder')); ?>" required>
    <button type="submit"><?php echo h(t('verify_notice_resend')); ?></button>
</form>
</body>
</html>
