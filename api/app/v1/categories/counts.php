<?php
require dirname(__DIR__) . '/_bootstrap.php';

global $pdo;
app_require_method('GET');
$type = trim((string) ($_GET['type'] ?? 'airline'));
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$perPage = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 100;
if (!in_array($type, ['airline', 'aircraft_model'], true)) {
    app_fail('不支持的分类类型', 422);
}

app_ok(app_fetch_category_counts($pdo, $type, $page, $perPage));
