<?php
declare(strict_types=1);

if (PHP_VERSION_ID < 80000) {
    http_response_code(500);
    exit('Deze beheeromgeving vereist PHP 8.0 of hoger.');
}

define('AB_ADMIN_DIR', dirname(__DIR__));
define('AB_ROOT', dirname(AB_ADMIN_DIR));
define('AB_CONTENT_DIR', AB_ROOT . '/content');
define('AB_UPLOAD_DIR', AB_ROOT . '/uploads');
define('AB_IMG_DIR', AB_ROOT . '/assets/img');

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
date_default_timezone_set('Europe/Amsterdam');

require_once __DIR__ . '/util.php';
require_once __DIR__ . '/store.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/media.php';

function ab_boot(bool $api = false): void
{
    enforce_https();
    send_security_headers();
    set_exception_handler(static function (Throwable $e) use ($api): void {
        error_log('admin: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        if ($api) {
            fail('Er ging iets mis op de server. Probeer het opnieuw.', 500);
        }
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Er ging iets mis op de server. Probeer het later opnieuw.';
        exit;
    });
}
