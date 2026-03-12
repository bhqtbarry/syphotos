<?php
require dirname(__DIR__) . '/_bootstrap.php';

global $pdo;
app_require_method('GET');
$level = trim((string) ($_GET['level'] ?? 'country'));
$filters = photo_feed_normalize_filters($_GET);

app_ok([
    'items' => app_fetch_map_clusters($pdo, $level, $filters),
]);
