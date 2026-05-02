<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Europe/Istanbul');

define('APP_NAME', 'Laboratory Reservation System');

/*
|--------------------------------------------------------------------------
| Environment Detection
|--------------------------------------------------------------------------
| Localde localhost / 127.0.0.1 görünür.
| InfinityFree'de gerçek domain görünür.
*/

$httpHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
$serverName = $_SERVER['SERVER_NAME'] ?? '';
$hostWithoutPort = strtolower(explode(':', $httpHost)[0]);

$isLocal = in_array($hostWithoutPort, ['localhost', '127.0.0.1', '::1'], true)
    || in_array(strtolower($serverName), ['localhost', '127.0.0.1', '::1'], true);

define('IS_LOCAL', $isLocal);

/*
|--------------------------------------------------------------------------
| Project URL Auto Detection
|--------------------------------------------------------------------------
| Local:
| http://localhost/Laboratory-Device-Reservation-and-Sample-Tracking-System/
|
| InfinityFree:
| https://yourdomain.infinityfreeapp.com/
*/

$isHttps = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
);

$protocol = $isHttps ? 'https://' : 'http://';

$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$publicPosition = strpos($scriptName, '/public/');

if ($publicPosition !== false) {
    $projectPath = substr($scriptName, 0, $publicPosition + 1);
} else {
    $projectPath = '/';
}

define('PROJECT_URL', $protocol . $httpHost . $projectPath);
define('BASE_URL', PROJECT_URL . 'public/');
define('ASSETS_URL', PROJECT_URL . 'assets/');

/*
|--------------------------------------------------------------------------
| Debug Mode
|--------------------------------------------------------------------------
| Localde hata göster.
| InfinityFree'de kullanıcıya hata gösterme.
*/

define('DEBUG_MODE', IS_LOCAL);

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

/*
|--------------------------------------------------------------------------
| Asset Helper
|--------------------------------------------------------------------------
| CSS/JS cache sorununu otomatik çözer.
| Dosya değişirse ?v= değeri de otomatik değişir.
*/

if (!function_exists('asset_url')) {
    function asset_url(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');

        $filePath = __DIR__ . '/../assets/' . $path;

        if (file_exists($filePath)) {
            $version = filemtime($filePath);
        } else {
            $version = time();
        }

        return ASSETS_URL . $path . '?v=' . $version;
    }
}