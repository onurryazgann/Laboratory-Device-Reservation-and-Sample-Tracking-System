<?php

$currentPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');

function navLinkActive(string $pathPart): string
{
    $currentPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    return strpos($currentPath, $pathPart) !== false ? 'active' : '';
}

$isLoggedIn = isLoggedIn();
$isAdmin = $isLoggedIn && isAdmin();

$brandUrl = BASE_URL . 'index.php';

if ($isLoggedIn && !$isAdmin) {
    $brandUrl = BASE_URL . 'dashboard.php';
}

if ($isLoggedIn && $isAdmin) {
    $brandUrl = BASE_URL . 'admin/index.php';
}

$userName = $isLoggedIn ? getCurrentUserName() : '';
$userRole = $_SESSION['role_name'] ?? 'user';

$currentPageTitle = trim($pageTitle ?? APP_NAME);

if ($currentPageTitle === APP_NAME) {
    $currentPageTitle = 'Home';
}

?>
<header class="topbar">
    <div class="topbar-shell">

        <div class="topbar-inner">

            <!-- BRAND -->
            <a class="brand" href="<?= htmlspecialchars($brandUrl, ENT_QUOTES, 'UTF-8') ?>">
                <span class="brand-mark">LAB</span>

                <span class="brand-text">
                    <span class="brand-text-full"><?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="brand-text-short"><?= htmlspecialchars($currentPageTitle, ENT_QUOTES, 'UTF-8') ?></span>
                </span>
            </a>

            <!-- PURE CSS MOBILE TOGGLE -->
            <input
                type="checkbox"
                id="mobile-nav-toggle"
                class="mobile-nav-checkbox"
                aria-hidden="true"
            >

            <label
                for="mobile-nav-toggle"
                class="mobile-menu-btn"
                aria-label="Toggle navigation"
                title="Menu"
            >
                <span class="hamburger-icon" aria-hidden="true">☰</span>
            </label>

            <!-- NAVIGATION -->
            <nav class="nav-links" aria-label="Main navigation">

                <!-- PUBLIC LINKS -->
                <a
                    class="<?= navLinkActive('/public/index.php') ?>"
                    href="<?= BASE_URL ?>index.php"
                >
                    Home
                </a>

                <a
                    class="<?= navLinkActive('/public/labs.php') ?>"
                    href="<?= BASE_URL ?>labs.php"
                >
                    Laboratories
                </a>

                <?php if (!$isLoggedIn): ?>

                    <!-- GUEST LINKS -->
                    <a
                        class="<?= navLinkActive('/public/login.php') ?>"
                        href="<?= BASE_URL ?>login.php"
                    >
                        Login
                    </a>

                    <a
                        class="nav-action primary <?= navLinkActive('/public/register.php') ?>"
                        href="<?= BASE_URL ?>register.php"
                    >
                        Register
                    </a>

                <?php else: ?>

                    <!-- USER LINKS -->
                    <a
                        class="<?= navLinkActive('/public/dashboard.php') ?>"
                        href="<?= BASE_URL ?>dashboard.php"
                    >
                        Dashboard
                    </a>

                    <a
                        class="<?= navLinkActive('/public/reserve.php') ?>"
                        href="<?= BASE_URL ?>reserve.php"
                    >
                        Reserve
                    </a>

                    <a
                        class="<?= navLinkActive('/public/my-reservations.php') ?>"
                        href="<?= BASE_URL ?>my-reservations.php"
                    >
                        My Reservations
                    </a>

                    <?php if ($isAdmin): ?>

                        <!-- ADMIN TOOLS -->
                        <details class="nav-dropdown">
                            <summary class="<?= navLinkActive('/public/admin/') ?>">
                                Admin Tools
                            </summary>

                            <div class="nav-dropdown-menu">

                                <a
                                    class="<?= navLinkActive('/public/admin/index.php') ?>"
                                    href="<?= BASE_URL ?>admin/index.php"
                                >
                                    Admin Dashboard
                                </a>

                                <a
                                    class="<?= navLinkActive('/public/admin/reservations.php') ?>"
                                    href="<?= BASE_URL ?>admin/reservations.php"
                                >
                                    Reservations
                                </a>

                                <a
                                    class="<?= navLinkActive('/public/admin/labs.php') ?>"
                                    href="<?= BASE_URL ?>admin/labs.php"
                                >
                                    Laboratories
                                </a>

                                <a
                                    class="<?= navLinkActive('/public/admin/lab-form.php') ?>"
                                    href="<?= BASE_URL ?>admin/lab-form.php"
                                >
                                    Add Laboratory
                                </a>

                                <a
                                    class="<?= navLinkActive('/public/admin/stations.php') ?>"
                                    href="<?= BASE_URL ?>admin/stations.php"
                                >
                                    Stations
                                </a>

                                <a
                                    class="<?= navLinkActive('/public/admin/station-form.php') ?>"
                                    href="<?= BASE_URL ?>admin/station-form.php"
                                >
                                    Add Station
                                </a>

                                <a
                                    class="<?= navLinkActive('/public/admin/equipment.php') ?>"
                                    href="<?= BASE_URL ?>admin/equipment.php"
                                >
                                    Equipment
                                </a>

                                <a
                                    class="<?= navLinkActive('/public/admin/equipment-form.php') ?>"
                                    href="<?= BASE_URL ?>admin/equipment-form.php"
                                >
                                    Add Equipment
                                </a>

                                <a
                                    class="<?= navLinkActive('/public/admin/users.php') ?>"
                                    href="<?= BASE_URL ?>admin/users.php"
                                >
                                    Users
                                </a>

                            </div>
                        </details>

                    <?php endif; ?>

                    <!-- ACCOUNT LINKS -->
                    <a
                        class="nav-profile-link <?= navLinkActive('/public/profile.php') ?>"
                        href="<?= htmlspecialchars(BASE_URL . 'profile.php', ENT_QUOTES, 'UTF-8') ?>"
                        title="<?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?>"
                        aria-label="Profile: <?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?>"
                    >
                        <span class="nav-profile-icon" aria-hidden="true">👤</span>

                        <span class="nav-profile-name">
                            <?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </a>

                    <a
                        class="nav-action danger nav-logout-link"
                        href="<?= htmlspecialchars(BASE_URL . 'logout.php', ENT_QUOTES, 'UTF-8') ?>"
                        title="Logout"
                        aria-label="Logout"
                    >
                        <span class="nav-logout-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M10 6H6.75C5.78 6 5 6.78 5 7.75V16.25C5 17.22 5.78 18 6.75 18H10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                <path d="M14 8L18 12L14 16" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M18 12H10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                        </span>

                        <span class="nav-logout-text">Logout</span>
                    </a>

                <?php endif; ?>

            </nav>

        </div>

    </div>
</header>