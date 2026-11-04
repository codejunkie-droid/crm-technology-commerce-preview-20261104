<?php
declare(strict_types=1);
const CRM_ROOT = __DIR__ . '/..';
if (is_file(CRM_ROOT . '/.env')) {
    foreach (file(CRM_ROOT . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        if (preg_match('/^[A-Z_][A-Z0-9_]*$/', trim($key)) && getenv(trim($key)) === false) putenv(trim($key) . '=' . trim($value, " \t\n\r\0\x0B\"'"));
    }
}
date_default_timezone_set('UTC');
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    session_name('crm_session');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>getenv('APP_ENV')==='production' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),'httponly'=>true,'samesite'=>'Lax']);
    session_start();
}
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Builder.php';
require_once __DIR__ . '/Pdf.php';
require_once __DIR__ . '/Store.php';
require_once __DIR__ . '/Api.php';
$store = new Store();
