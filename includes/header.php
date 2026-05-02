<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/auth_helper.php';

$pageTitle = $pageTitle ?? APP_NAME;

$currentPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$isAdminArea = strpos($currentPath, '/public/admin/') !== false;

// Page area class
$bodyClass = $isAdminArea ? 'admin-area' : 'user-area';

// User state classes
if (isset($_SESSION['user_id'])) {
    $bodyClass .= ' authenticated-user';

    if (isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'admin') {
        $bodyClass .= ' admin-user';
    }
} else {
    $bodyClass .= ' guest-user';
}

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - <?= htmlspecialchars(APP_NAME) ?></title>

    <!-- Material 3 Global -->
    <link rel="stylesheet" href="<?= htmlspecialchars(asset_url('css/theme.css')) ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(asset_url('css/layout.css')) ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(asset_url('css/components.css')) ?>">

    <!-- Legacy / Base -->
    <link rel="stylesheet" href="<?= htmlspecialchars(asset_url('css/style.css')) ?>">

    <!-- Admin -->
    <?php if ($isAdminArea): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars(asset_url('css/admin.css')) ?>">
    <?php endif; ?>

    <!-- Optional Page CSS -->
    <?php if (!empty($pageCss)): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars(asset_url('css/' . $pageCss)) ?>">
    <?php endif; ?>

    <!-- Stable Navbar Override -->
    <link rel="stylesheet" href="<?= htmlspecialchars(asset_url('css/navbar.css')) ?>">
</head>
<body class="<?= htmlspecialchars($bodyClass) ?>">

<?php require_once __DIR__ . '/navbar.php'; ?>

<main class="main-content">