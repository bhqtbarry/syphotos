<?php
require dirname(__DIR__) . '/_bootstrap.php';

global $pdo;
app_require_method('GET');
$auth = app_require_auth($pdo);
$userId = (int) $auth['user']['id'];

$queries = [
    'all_photos' => 'SELECT COUNT(*) FROM photos WHERE user_id = :user_id',
    'approved_photos' => 'SELECT COUNT(*) FROM photos WHERE user_id = :user_id AND approved = 1',
    'pending_photos' => 'SELECT COUNT(*) FROM photos WHERE user_id = :user_id AND approved IN (0, 3)',
    'rejected_photos' => 'SELECT COUNT(*) FROM photos WHERE user_id = :user_id AND approved = 2',
    'liked_photos' => 'SELECT COUNT(*) FROM photo_likes WHERE user_id = :user_id',
    'unread_notifications' => 'SELECT COUNT(*) FROM app_notifications WHERE user_id = :user_id AND is_read = 0',
];

$stats = [];
foreach ($queries as $key => $sql) {
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $stats[$key] = (int) $stmt->fetchColumn();
}

app_ok([
    'user' => app_user_payload($auth['user']),
    'stats' => $stats,
]);
