<?php
declare(strict_types=1);

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function app_base_url(): string
{
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $directory = rtrim(dirname($script), '/.');

    if (str_ends_with($directory, '/admin')) {
        $directory = substr($directory, 0, -6);
    }

    return $directory === '/' ? '' : $directory;
}

function app_url(string $path = ''): string
{
    $base = app_base_url();
    $path = ltrim($path, '/');

    return $base . ($path === '' ? '/' : '/' . $path);
}

function media_url(string $path): string
{
    $segments = array_map('rawurlencode', explode('/', ltrim($path, '/')));
    return app_url(implode('/', $segments));
}

function format_price(int $price): string
{
    return '₦' . number_format($price);
}

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $sessionDirectory = defined('BASE_PATH') ? BASE_PATH . '/runtime/sessions' : sys_get_temp_dir();

    if (!is_dir($sessionDirectory) && !mkdir($sessionDirectory, 0750, true) && !is_dir($sessionDirectory)) {
        throw new RuntimeException('Secure session storage is unavailable.');
    }

    session_name('asabana_admin');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    session_save_path($sessionDirectory);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => app_base_url() . '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

function csrf_token(): string
{
    start_secure_session();

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    start_secure_session();
    $submitted = $_POST['_csrf'] ?? '';
    $stored = $_SESSION['csrf_token'] ?? '';

    if (!is_string($submitted) || !is_string($stored) || $stored === '' || !hash_equals($stored, $submitted)) {
        throw new RuntimeException('Your session expired. Please refresh the page and try again.');
    }
}

function flash(string $type, string $message): void
{
    start_secure_session();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function pull_flash(): ?array
{
    start_secure_session();
    $message = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return is_array($message) ? $message : null;
}

function client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

function send_security_headers(bool $admin = false): void
{
    if (headers_sent()) {
        return;
    }

    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    header("Content-Security-Policy: default-src 'self'; base-uri 'self'; connect-src 'self'; font-src 'self'; form-action 'self'; frame-ancestors 'none'; img-src 'self' data:; object-src 'none'; script-src 'self'; style-src 'self'");

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    if ($secure) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }

    if ($admin) {
        header('Cache-Control: no-store, max-age=0');
        header('Pragma: no-cache');
        header('X-Robots-Tag: noindex, nofollow, noarchive');
    }
}
