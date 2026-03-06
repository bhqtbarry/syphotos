<?php
require 'db_connect.php';
require_once __DIR__.'/src/mail.php';
require_once __DIR__.'/src/helpers.php';
require_once __DIR__.'/src/i18n.php';
session_start();


$error = '';
$success = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $input_code = strtoupper(trim($_POST['verification_code'] ?? ''));
    $stored_code = strtoupper($_SESSION['register_code'] ?? '');

    if ($stored_code === '' || $input_code === '' || !hash_equals($stored_code, $input_code)) {
        $error = t('register_error_captcha');
    } elseif($password != $confirm_password) {
        $error = t('register_error_password_mismatch');
    } else {
        try {
            // 检查用户名是否已存在
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username");
            $stmt->bindParam(':username', $username);
            $stmt->execute();

            if($stmt->fetch(PDO::FETCH_ASSOC)) {
                $error = t('register_error_username_exists');
            } else {
                // 插入新用户
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $verification_token = bin2hex(random_bytes(32));
                $token_created_at = gmdate('Y-m-d H:i:s');
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, is_admin, verification_token, verification_token_created_at) 
                                     VALUES (:username, :email, :password, 0, :verification_token, :token_created_at)");
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':password', $hashed_password);
                $stmt->bindParam(':verification_token', $verification_token);
                $stmt->bindParam(':token_created_at', $token_created_at);
                $stmt->execute();

                if (send_verification_email($email, $verification_token)) {
                    $success = t('register_success_mail_sent');
                    unset($_SESSION['register_code']);
                } else {
                    $error = t('register_success_mail_failed');
                }
            }
        } catch(PDOException $e) {
            $error = t('register_failed') . $e->getMessage();
        }
    }
}
$locale = current_locale();
?>
<!DOCTYPE html>
<html lang="<?php echo h($locale); ?>">
<head>
    <meta charset="UTF-8">
    <title>SY Photos - <?php echo h(t('register_page_title')); ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f0f7ff; }
        .nav { background-color: #165DFF; padding: 10px; margin-bottom: 20px; }
        .nav a { color: white; margin-right: 15px; text-decoration: none; }
        .register-form { max-width: 400px; margin: 0 auto; background-color: white; padding: 20px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; }
        input { width: 100%; padding: 8px; box-sizing: border-box; }
        .btn { background-color: #165DFF; color: white; border: none; padding: 10px; width: 100%; cursor: pointer; border-radius: 3px; }
        .error { color: red; margin-bottom: 15px; }
        .success { color: green; margin-bottom: 15px; }
        .captcha-row { display: flex; align-items: center; gap: 10px; }
        .captcha-row img { border: 1px solid #ccc; border-radius: 3px; height: 40px; cursor: pointer; }
        .captcha-refresh { background: none; border: none; color: #165DFF; padding: 0; cursor: pointer; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="nav">
        <a href="index.php"><?php echo h(t('nav_home')); ?></a>
        <a href="photolist.php"><?php echo h(t('nav_all_photos')); ?></a>
        <a href="login.php"><?php echo h(t('login_submit')); ?></a>
        <a href="register.php"><?php echo h(t('register_submit')); ?></a>
    </div>

    <div class="register-form">
        <h2><?php echo h(t('register_heading')); ?></h2>

        <?php if($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if($success): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>

        <form method="post" action="register.php">
            <div class="form-group">
                <label for="username"><?php echo h(t('register_username')); ?></label>
                <input type="text" id="username" name="username" required>
            </div>

            <div class="form-group">
                <label for="email"><?php echo h(t('register_email')); ?></label>
                <input type="email" id="email" name="email" required>
            </div>

            <div class="form-group">
                <label for="password"><?php echo h(t('register_password')); ?></label>
                <input type="password" id="password" name="password" required>
            </div>

            <div class="form-group">
                <label for="confirm_password"><?php echo h(t('register_confirm_password')); ?></label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>

            <div class="form-group">
                <label for="verification_code"><?php echo h(t('register_verification_code')); ?></label>
                <div class="captcha-row">
                    <input type="text" id="verification_code" name="verification_code" placeholder="<?php echo h(t('register_captcha_placeholder')); ?>" required>
                    <img src="register_captcha.php?ts=<?php echo time(); ?>" alt="<?php echo h(t('register_verification_code')); ?>" id="captchaImage" title="<?php echo h(t('register_captcha_refresh')); ?>">
                </div>
                <button type="button" class="captcha-refresh" onclick="refreshCaptcha()"><?php echo h(t('register_captcha_refresh')); ?></button>
            </div>

            <button type="submit" class="btn"><?php echo h(t('register_submit')); ?></button>
        </form>

        <p><?php echo h(t('register_has_account')); ?><a href="login.php"><?php echo h(t('register_login_now')); ?></a></p>
        <p><?php echo h(t('register_forgot_password')); ?><a href="forgot_password.php"><?php echo h(t('register_recover_password')); ?></a></p>
    </div>
<script>
function refreshCaptcha() {
    const img = document.getElementById('captchaImage');
    const url = new URL(img.src, window.location.origin);
    url.searchParams.set('ts', Date.now());
    img.src = url.toString();
}
</script>
</body>
</html>
