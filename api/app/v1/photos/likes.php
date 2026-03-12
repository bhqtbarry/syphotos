<?php
require dirname(__DIR__) . '/_bootstrap.php';

global $pdo;
app_require_method('GET');
$auth = app_require_auth($pdo);
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$perPage = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 30;

app_ok(app_fetch_user_likes($pdo, (int) $auth['user']['id'], $page, $perPage));
