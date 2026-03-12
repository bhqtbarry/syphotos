<?php
require dirname(__DIR__) . '/_bootstrap.php';

global $pdo;
$auth = app_require_auth($pdo);

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $data = app_request_data();
    $notificationId = isset($data['notification_id']) ? (int) $data['notification_id'] : 0;
    if ($notificationId > 0) {
        $stmt = $pdo->prepare('UPDATE app_notifications SET is_read = 1, read_at = NOW() WHERE id = :id AND user_id = :user_id');
        $stmt->execute([
            ':id' => $notificationId,
            ':user_id' => (int) $auth['user']['id'],
        ]);
    } else {
        $stmt = $pdo->prepare('UPDATE app_notifications SET is_read = 1, read_at = NOW() WHERE user_id = :user_id AND is_read = 0');
        $stmt->bindValue(':user_id', (int) $auth['user']['id'], PDO::PARAM_INT);
        $stmt->execute();
    }

    app_ok(['message' => '通知已标记已读']);
}

app_require_method('GET');
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$perPage = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 20;
$page = max(1, $page);
$perPage = max(1, min(50, $perPage));
$offset = ($page - 1) * $perPage;

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM app_notifications WHERE user_id = :user_id');
$countStmt->bindValue(':user_id', (int) $auth['user']['id'], PDO::PARAM_INT);
$countStmt->execute();
$total = (int) $countStmt->fetchColumn();

$stmt = $pdo->prepare('SELECT * FROM app_notifications WHERE user_id = :user_id ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset');
$stmt->bindValue(':user_id', (int) $auth['user']['id'], PDO::PARAM_INT);
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$items = array_map(static function (array $row): array {
    return [
        'id' => (int) $row['id'],
        'type' => (string) $row['type'],
        'title' => (string) $row['title'],
        'body' => (string) $row['body'],
        'payload' => $row['payload_json'] ? json_decode((string) $row['payload_json'], true) : null,
        'is_read' => (bool) $row['is_read'],
        'created_at' => (string) $row['created_at'],
        'read_at' => $row['read_at'] !== null ? (string) $row['read_at'] : null,
    ];
}, $stmt->fetchAll(PDO::FETCH_ASSOC));

app_ok([
    'page' => $page,
    'per_page' => $perPage,
    'total' => $total,
    'has_more' => ($offset + count($items)) < $total,
    'items' => $items,
]);
