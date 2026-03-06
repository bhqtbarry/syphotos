<?php
require __DIR__ . '/../db_connect.php';
require __DIR__ . '/../src/photo_feed_service.php';

header('Content-Type: application/json; charset=UTF-8');

$field = isset($_GET['field']) ? (string) $_GET['field'] : '';
$keyword = isset($_GET['q']) ? (string) $_GET['q'] : '';
$filters = photo_feed_normalize_filters($_GET);

$allowedFields = ['userid', 'airline', 'aircraft_model', 'cam', 'lens', 'registration_number', 'iatacode'];
if (!in_array($field, $allowedFields, true)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => '不支持的筛选字段',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $items = photo_feed_fetch_filter_suggestions($pdo, $field, $keyword, $filters, 10);
    echo json_encode([
        'success' => true,
        'items' => $items,
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => '获取筛选建议失败: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
