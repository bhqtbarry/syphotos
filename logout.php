<?php
require __DIR__ . '/config/config.php';
require 'db_connect.php';

session_start();
session_unset();
session_destroy();

// 清除Cookie
setcookie('remember_me', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
// 清除数据库令牌
$clear_stmt = $pdo->prepare("UPDATE users SET remember_token = NULL WHERE id = :user_id");
$clear_stmt->bindParam(':user_id', $user['id']);
$clear_stmt->execute();

header('Location: index.php');
exit;
