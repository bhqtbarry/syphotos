<?php
require __DIR__ . '/../db_connect.php';
require __DIR__ . '/../src/photo_feed_service.php';

header('Content-Type: application/json; charset=UTF-8');

$filters = photo_feed_normalize_filters($_GET);
$expires = isset($_GET['expires']) && is_numeric($_GET['expires']) ? (int) $_GET['expires'] : 0;
$signature = isset($_GET['sig']) ? (string) $_GET['sig'] : '';

if ($expires <= 0 || $signature === '' || !photo_feed_verify_access_signature($filters, $expires, $signature)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => '认证失败或已过期',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $total = photo_feed_fetch_total($pdo, $filters);
    $photos = photo_feed_fetch_page($pdo, $filters);
    $offset = ($filters['page'] - 1) * $filters['per_page'];
    $hasMore = ($offset + count($photos)) < $total;

    echo json_encode([
        'success' => true,
        'total' => $total,
        'count' => count($photos),
        'hasMore' => $hasMore,
        'items' => photo_feed_prepare_items($photos),
        'html' => photo_feed_render_cards($photos),
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => '获取图片失败: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
