<?php
require dirname(__DIR__) . '/_bootstrap.php';

global $pdo;
app_require_method('POST');
$auth = app_require_auth($pdo);
$data = app_request_data();
app_require_fields($data, ['photo_id', 'title_confirm']);

app_delete_user_photo($pdo, (int) $auth['user']['id'], (int) $data['photo_id'], (string) $data['title_confirm']);
app_ok(['message' => '图片已删除']);
