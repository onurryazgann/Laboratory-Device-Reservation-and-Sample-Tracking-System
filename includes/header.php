<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/auth_helper.php';
require_once __DIR__ . '/../includes/csrf.php';

$pageTitle = $pageTitle ?? APP_NAME;

$currentPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$isAdminArea = strpos($currentPath, '/public/admin/') !== false;

/*
|--------------------------------------------------------------------------
| Asset Version
|--------------------------------------------------------------------------
| CSS değişikliklerinde tarayıcı cache sorununu azaltmak için kullanılır.
|--------------------------------------------------------------------------
*/

$assetVersion = defined('ASSET_VERSION') ? (string) ASSET_VERSION : '20260508';

/*
|--------------------------------------------------------------------------
| Body Class
|--------------------------------------------------------------------------
| Sayfalarda önceden $bodyClass tanımlandıysa onu korur.
|--------------------------------------------------------------------------
*/

$pageSpecificBodyClass = $bodyClass ?? '';

$bodyClass = $isAdminArea ? 'admin-area' : 'user-area';

if (!empty($pageSpecificBodyClass)) {
    $bodyClass .= ' ' . $pageSpecificBodyClass;
}

/*
|--------------------------------------------------------------------------
| User State Classes
|--------------------------------------------------------------------------
*/

if (isset($_SESSION['user_id'])) {
    $bodyClass .= ' authenticated-user';

    if (isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'admin') {
        $bodyClass .= ' admin-user';
    }
} else {
    $bodyClass .= ' guest-user';
}

/*
|--------------------------------------------------------------------------
| Page Specific CSS Normalization
|--------------------------------------------------------------------------
| $pageCss string veya array olabilir.
| Örnek:
| $pageCss = 'admin-forms.css';
| $pageCss = ['admin-labs.css', 'admin-forms.css'];
|--------------------------------------------------------------------------
*/

$pageCssFiles = [];

if (!empty($pageCss)) {
    $pageCssFiles = is_array($pageCss) ? $pageCss : [$pageCss];
}

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <?php if (function_exists('csrfMetaTag')): ?>
        <?= csrfMetaTag() ?>
    <?php endif; ?>

    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>

    <!-- Global Theme CSS -->
    <link rel="stylesheet" href="<?= htmlspecialchars(asset_url('css/theme.css') . '?v=' . $assetVersion, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(asset_url('css/layout.css') . '?v=' . $assetVersion, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(asset_url('css/components.css') . '?v=' . $assetVersion, ENT_QUOTES, 'UTF-8') ?>">

    <!-- Base CSS -->
    <link rel="stylesheet" href="<?= htmlspecialchars(asset_url('css/style.css') . '?v=' . $assetVersion, ENT_QUOTES, 'UTF-8') ?>">

    <!-- Admin CSS -->
    <?php if ($isAdminArea): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars(asset_url('css/admin.css') . '?v=' . $assetVersion, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>

    <!-- Page Specific CSS -->
    <?php foreach ($pageCssFiles as $cssFile): ?>
        <?php
            $cssFile = trim((string) $cssFile);

            if ($cssFile === '') {
                continue;
            }
        ?>
        <link rel="stylesheet" href="<?= htmlspecialchars(asset_url('css/' . $cssFile) . '?v=' . $assetVersion, ENT_QUOTES, 'UTF-8') ?>">
    <?php endforeach; ?>

    <!-- Navbar CSS -->
    <link rel="stylesheet" href="<?= htmlspecialchars(asset_url('css/navbar.css') . '?v=' . $assetVersion, ENT_QUOTES, 'UTF-8') ?>">
</head>

<body class="<?= htmlspecialchars($bodyClass, ENT_QUOTES, 'UTF-8') ?>">

<?php require_once __DIR__ . '/navbar.php'; ?>

<main class="main-content">