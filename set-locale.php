<?php
require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/src/i18n.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$token = $_POST['csrf'] ?? '';
verify_csrf($token);

$requestedLocale = strtolower(trim($_POST['locale'] ?? ''));
if (!is_supported_locale($requestedLocale)) {
    $requestedLocale = detect_locale();
}

$_SESSION['locale'] = $requestedLocale;

setcookie('syphotos_locale', $requestedLocale, [
    'expires' => time() + 31536000,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => false,
    'samesite' => 'Lax',
]);

$redirectTarget = normalize_redirect($_POST['redirect_to'] ?? ($_SERVER['HTTP_REFERER'] ?? '/'));
redirect($redirectTarget);

function normalize_redirect(string $target): string
{
    $target = trim($target);
    if ($target === '') {
        return '/';
    }
    if (preg_match('#^[a-z][a-z0-9+\-.]*://#i', $target)) {
        $parts = parse_url($target);
        $path = $parts['path'] ?? '/';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        $target = $path . $query;
    }
    if ($target[0] !== '/') {
        $target = '/' . ltrim($target, '/');
    }
    return $target;
}
