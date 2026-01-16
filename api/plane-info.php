<?php
// api/plane-info.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db_connect.php'; // 确保这里能拿到 $pdo

$registration = isset($_GET['registration'])
    ? trim($_GET['registration'])
    : '';

if ($registration === '' || mb_strlen($registration) < 3) {
    echo json_encode([
        'status' => 'error',
        'message' => 'registration 参数无效'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT typecode, owner
        FROM airplane
        WHERE registration = ?
        LIMIT 1
    ");
    $stmt->execute([$registration]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        echo json_encode([
            'status' => 'success',
            'data' => [
                '机型' => $row['typecode'],
                '运营机构' => $row['owner']
            ]
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'status' => 'not_found',
            'message' => '未找到该飞机信息'
        ], JSON_UNESCAPED_UNICODE);
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => '数据库查询失败',
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
