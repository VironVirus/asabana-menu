<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once BASE_PATH . '/includes/helpers.php';
require_once BASE_PATH . '/includes/categories.php';
require_once BASE_PATH . '/includes/MenuStore.php';

date_default_timezone_set('Africa/Lagos');
send_security_headers(defined('ASABANA_ADMIN') && ASABANA_ADMIN === true);

function menu_store(): MenuStore
{
    static $store;

    if (!$store instanceof MenuStore) {
        $store = new MenuStore(BASE_PATH);
    }

    return $store;
}
