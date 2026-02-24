<?php
require 'db_connect.php';
require_once __DIR__.'/src/mail.php';
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
        $error = '验证码错误，请重试';
    } elseif($password != $confirm_password) {
        $error = '两次密码输入不一致';
    } else {
        try {
            // 检查用户名是否已存在
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username");
            $stmt->bindParam(':username', $username);
            $stmt->execute();

            if($stmt->fetch(PDO::FETCH_ASSOC)) {
                $error = '用户名已存在';
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
                    $success = '已发送验证邮件，请点击邮件中的链接完成注册';
                    unset($_SESSION['register_code']);
                } else {
                    $error = '注册成功，但发送验证邮件失败，请稍后重试';
                }
            }
        } catch(PDOException $e) {
            $error = "注册失败: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>SY Photos - 注册</title>
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
        <a href="index.php">首页</a>
        <a href="all_photos.php">全部图片</a>
        <a href="login.php">登录</a>
        <a href="register.php">注册</a>
    </div>

    <div class="register-form">
        <h2>用户注册</h2>

        <?php if($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if($success): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>

        <form method="post" action="register.php">
            <div class="form-group">
                <label for="username">用户名</label>
                <input type="text" id="username" name="username" required>
            </div>

            <div class="form-group">
                <label for="email">邮箱</label>
                <input type="email" id="email" name="email" required>
            </div>

            <div class="form-group">
                <label for="password">密码</label>
                <input type="password" id="password" name="password" required>
            </div>

            <div class="form-group">
                <label for="confirm_password">确认密码</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>

            <div class="form-group">
                <label for="verification_code">验证码</label>
                <div class="captcha-row">
                    <input type="text" id="verification_code" name="verification_code" placeholder="请输入图片中的字符" required>
                    <img src="register_captcha.php?ts=<?php echo time(); ?>" alt="验证码" id="captchaImage" title="看不清？点击刷新">
                </div>
                <button type="button" class="captcha-refresh" onclick="refreshCaptcha()">看不清？换一张</button>
            </div>

            <button type="submit" class="btn">注册</button>
        </form>

        <p>已有账号？<a href="login.php">立即登录</a></p>
        <p>忘记密码？<a href="forgot_password.php">找回密码</a></p>
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
