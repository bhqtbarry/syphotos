<?php
require dirname(__DIR__) . '/_bootstrap.php';

global $pdo;
app_require_method('GET');
$photoId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($photoId <= 0) {
    app_fail('缺少图片 ID', 422);
}

$viewerUserId = 0;
$token = app_bearer_token();
if ($token !== '') {
    $session = app_session_by_access_token($pdo, $token);
    if ($session && app_is_session_active($session, 'access')) {
        $viewerUserId = (int) $session['user_id'];
    }
}

$photo = app_fetch_photo_detail($pdo, $photoId, $viewerUserId);
if (!$photo) {
    app_fail('图片不存在或无权查看', 404);
}

app_ok(['item' => $photo]);
