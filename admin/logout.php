<?php
declare(strict_types=1);

define('ASABANA_ADMIN', true);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once BASE_PATH . '/includes/Auth.php';

$auth = new AdminAuth(BASE_PATH);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

try {
    verify_csrf();
    $auth->logout();
} catch (Throwable) {
    // Logout remains safe to complete even when the previous session has expired.
}

header('Location: ' . app_url('admin/'));
exit;

