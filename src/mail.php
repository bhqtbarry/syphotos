<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../lib/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../lib/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../lib/PHPMailer/src/SMTP.php';

function syphotos_mailer(): PHPMailer
{
    $config = require __DIR__ . '/../config/config.php';

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = $config['smtp_user'];
    $mail->Password = $config['smtp_pass'];
    $mail->Port = 587;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->CharSet = 'UTF-8';
    $mail->setFrom($config['mail_from'], $config['mail_from_name']);
    $mail->isHTML(true);

    return $mail;
}

function send_verification_email(string $email, string $token): bool
{
    $config = require __DIR__ . '/../config/config.php';

    $mail = syphotos_mailer();

    try {
        $mail->addAddress($email);
        $link = $config['base_url'] . '/verify.php?token=' . urlencode($token);
        $mail->Subject = 'Verify your SyPhotos account';
        $mail->Body    = "请点击下面链接完成注册：\n\n <a href=\"{$link}\">{$link}</a>";

        return $mail->send();
    } catch (Exception $e) {
        error_log('Mail error: ' . $mail->ErrorInfo);
        return false;
    }
}
function send_reset_email(string $email, string $token): bool
{
    $config = require __DIR__ . '/../config/config.php';

    $mail = syphotos_mailer();

    try {
        $mail->addAddress($email);
        $link = $config['base_url'] . '/reset.php?token=' . urlencode($token);
        $mail->Subject = 'Reset your SyPhotos password';
        $mail->Body    = "请点击下面链接重置密码：\n\n{$link}";

        return $mail->send();
    } catch (Exception $e) {
        error_log('Mail error: ' . $mail->ErrorInfo);
        return false;
    }
}

function send_password_reset_code_email(string $email, string $code): bool
{
    $mail = syphotos_mailer();

    try {
        $mail->addAddress($email);
        $mail->Subject = 'SY Photos password reset code';
        $mail->Body = '<p>您的 SY Photos 密码重置验证码为：</p>'
            . '<p style="font-size:24px;font-weight:700;letter-spacing:4px;">' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p>验证码 5 分钟内有效。如果这不是您的操作，请忽略此邮件。</p>';

        return $mail->send();
    } catch (Exception $e) {
        error_log('Mail error: ' . $mail->ErrorInfo);
        return false;
    }
}
