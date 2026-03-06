<?php
require 'src/photo_feed_service.php';

$filename = isset($_GET['file']) ? basename((string) $_GET['file']) : '';
$variant = isset($_GET['variant']) ? (string) $_GET['variant'] : 'thumb';
$expires = isset($_GET['expires']) && is_numeric($_GET['expires']) ? (int) $_GET['expires'] : 0;
$signature = isset($_GET['sig']) ? (string) $_GET['sig'] : '';

if ($filename === '' || !in_array($variant, ['thumb', 'original'], true) || $expires <= 0 || $signature === '') {
    http_response_code(400);
    exit('Invalid request');
}

if ($expires < time()) {
    http_response_code(403);
    exit('Link expired');
}

$expected = photo_feed_build_asset_signature($filename, $variant, $expires);
if (!hash_equals($expected, $signature)) {
    http_response_code(403);
    exit('Invalid signature');
}

$baseDir = $variant === 'original' ? __DIR__ . '/uploads/o/' : __DIR__ . '/uploads/';
$path = realpath($baseDir . $filename);
$allowedBase = realpath($baseDir);

if ($path === false || $allowedBase === false || strpos($path, $allowedBase) !== 0 || !is_file($path)) {
    http_response_code(404);
    exit('Not found');
}

$mime = mime_content_type($path) ?: 'image/jpeg';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=300');
header('X-Content-Type-Options: nosniff');
readfile($path);
