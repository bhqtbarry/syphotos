<?php
require dirname(__DIR__) . '/_bootstrap.php';

global $pdo;
app_require_method('GET');
$field = isset($_GET['field']) ? (string) $_GET['field'] : '';
$query = isset($_GET['q']) ? (string) $_GET['q'] : '';
$filters = photo_feed_normalize_filters($_GET);
$allowedFields = ['userid', 'airline', 'aircraft_model', 'cam', 'lens', 'registration_number', 'iatacode'];

if (!in_array($field, $allowedFields, true)) {
    app_fail('不支持的筛选字段', 422);
}

app_ok([
    'items' => photo_feed_fetch_filter_suggestions($pdo, $field, $query, $filters, 10),
]);
