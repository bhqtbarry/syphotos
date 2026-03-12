<?php
require dirname(__DIR__) . '/_bootstrap.php';

global $pdo;
app_require_method('GET');
$filters = photo_feed_normalize_filters($_GET);
$viewerUserId = 0;
$token = app_bearer_token();
if ($token !== '') {
    $session = app_session_by_access_token($pdo, $token);
    if ($session && app_is_session_active($session, 'access')) {
        $viewerUserId = (int) $session['user_id'];
    }
}

app_ok(app_fetch_feed($pdo, $filters, $viewerUserId));
