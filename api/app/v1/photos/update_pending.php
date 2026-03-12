<?php
require dirname(__DIR__) . '/_bootstrap.php';

global $pdo;
app_require_method('POST');
$auth = app_require_auth($pdo);
$data = app_request_data();
app_require_fields($data, ['photo_id']);

$photo = app_update_pending_photo($pdo, (int) $auth['user']['id'], (int) $data['photo_id'], $data);
app_ok([
    'message' => '图片信息已更新',
    'item' => $photo,
]);
