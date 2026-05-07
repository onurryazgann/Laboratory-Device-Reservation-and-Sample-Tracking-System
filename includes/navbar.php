<?php

$currentPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');

if (!function_exists('navLinkActive')) {
    function navLinkActive(string $pathPart): string
    {
        $currentPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');

        return strpos($currentPath, $pathPart) !== false ? 'active' : '';
    }
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

                <span class="brand-mark" aria-hidden="true">
                    <svg class="brand-logo-svg" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg">
                        <defs>
                            <linearGradient id="deviceLogoGradient" x1="0" y1="0" x2="1" y2="1">
                                <stop offset="0%" stop-color="#2563eb"/>
                                <stop offset="100%" stop-color="#14b8a6"/>
                            </linearGradient>
                        </defs>

                        <rect x="7" y="7" width="50" height="50" rx="18" fill="url(#deviceLogoGradient)"/>

                        <rect x="19" y="17" width="26" height="30" rx="7" fill="rgba(255,255,255,0.18)" stroke="white" stroke-width="3"/>

                        <path d="M24 27H40" stroke="white" stroke-width="3" stroke-linecap="round"/>
                        <path d="M24 34H35" stroke="white" stroke-width="3" stroke-linecap="round"/>

                        <circle cx="25" cy="43" r="2.6" fill="white"/>
                        <circle cx="32" cy="43" r="2.6" fill="white"/>
                        <circle cx="39" cy="43" r="2.6" fill="white"/>

                        <path d="M32 13V8" stroke="white" stroke-width="3" stroke-linecap="round"/>
                        <path d="M32 56V51" stroke="white" stroke-width="3" stroke-linecap="round"/>
                        <path d="M13 32H8" stroke="white" stroke-width="3" stroke-linecap="round"/>
                        <path d="M56 32H51" stroke="white" stroke-width="3" stroke-linecap="round"/>
                    </svg>
                </span>

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
            <a
                class="nav-home-mobile <?= htmlspecialchars(navLinkActive('/public/index.php'), ENT_QUOTES, 'UTF-8') ?>"
                href="<?= htmlspecialchars(BASE_URL . 'index.php', ENT_QUOTES, 'UTF-8') ?>"
            >
                Home
            </a>
            <a
                class="nav-labs-link <?= htmlspecialchars(navLinkActive('/public/labs.php'), ENT_QUOTES, 'UTF-8') ?>"
                href="<?= htmlspecialchars(BASE_URL . 'labs.php', ENT_QUOTES, 'UTF-8') ?>"
            >
                Laboratories
            </a>

                <?php if (!$isLoggedIn): ?>

                    <a
                        class="<?= htmlspecialchars(navLinkActive('/public/login.php'), ENT_QUOTES, 'UTF-8') ?>"
                        href="<?= htmlspecialchars(BASE_URL . 'login.php', ENT_QUOTES, 'UTF-8') ?>"
                    >
                        Login
                    </a>

                    <a
                        class="nav-action primary <?= htmlspecialchars(navLinkActive('/public/register.php'), ENT_QUOTES, 'UTF-8') ?>"
                        href="<?= htmlspecialchars(BASE_URL . 'register.php', ENT_QUOTES, 'UTF-8') ?>"
                    >
                        Register
                    </a>

                <?php else: ?>

                    <a
                        class="<?= htmlspecialchars(navLinkActive('/public/dashboard.php'), ENT_QUOTES, 'UTF-8') ?>"
                        href="<?= htmlspecialchars(BASE_URL . 'dashboard.php', ENT_QUOTES, 'UTF-8') ?>"
                    >
                        Dashboard
                    </a>

                    <a
                        class="<?= htmlspecialchars(navLinkActive('/public/reserve.php'), ENT_QUOTES, 'UTF-8') ?>"
                        href="<?= htmlspecialchars(BASE_URL . 'reserve.php', ENT_QUOTES, 'UTF-8') ?>"
                    >
                        Reserve
                    </a>

                    <a
                        class="<?= htmlspecialchars(navLinkActive('/public/my-reservations.php'), ENT_QUOTES, 'UTF-8') ?>"
                        href="<?= htmlspecialchars(BASE_URL . 'my-reservations.php', ENT_QUOTES, 'UTF-8') ?>"
                    >
                        My Reservations
                    </a>

                    <?php if ($isAdmin): ?>

                        <details class="nav-dropdown">
                            <summary class="<?= htmlspecialchars(navLinkActive('/public/admin/'), ENT_QUOTES, 'UTF-8') ?>">
                                Admin Tools
                            </summary>

                            <div class="nav-dropdown-menu">

                                <a
                                    class="<?= htmlspecialchars(navLinkActive('/public/admin/index.php'), ENT_QUOTES, 'UTF-8') ?>"
                                    href="<?= htmlspecialchars(BASE_URL . 'admin/index.php', ENT_QUOTES, 'UTF-8') ?>"
                                >
                                    Admin Dashboard
                                </a>

                                <a
                                    class="<?= htmlspecialchars(navLinkActive('/public/admin/reservations.php'), ENT_QUOTES, 'UTF-8') ?>"
                                    href="<?= htmlspecialchars(BASE_URL . 'admin/reservations.php', ENT_QUOTES, 'UTF-8') ?>"
                                >
                                    Reservations
                                </a>

                                <a
                                    class="<?= htmlspecialchars(navLinkActive('/public/admin/labs.php'), ENT_QUOTES, 'UTF-8') ?>"
                                    href="<?= htmlspecialchars(BASE_URL . 'admin/labs.php', ENT_QUOTES, 'UTF-8') ?>"
                                >
                                    Laboratories
                                </a>

                                <a
                                    class="<?= htmlspecialchars(navLinkActive('/public/admin/stations.php'), ENT_QUOTES, 'UTF-8') ?>"
                                    href="<?= htmlspecialchars(BASE_URL . 'admin/stations.php', ENT_QUOTES, 'UTF-8') ?>"
                                >
                                    Stations
                                </a>

                                <a
                                    class="<?= htmlspecialchars(navLinkActive('/public/admin/equipment.php'), ENT_QUOTES, 'UTF-8') ?>"
                                    href="<?= htmlspecialchars(BASE_URL . 'admin/equipment.php', ENT_QUOTES, 'UTF-8') ?>"
                                >
                                    Equipment
                                </a>

                                <a
                                    class="<?= htmlspecialchars(navLinkActive('/public/admin/users.php'), ENT_QUOTES, 'UTF-8') ?>"
                                    href="<?= htmlspecialchars(BASE_URL . 'admin/users.php', ENT_QUOTES, 'UTF-8') ?>"
                                >
                                    Users
                                </a>

                            </div>
                        </details>

                    <?php endif; ?>

                    <a
                        class="nav-profile-link <?= htmlspecialchars(navLinkActive('/public/profile.php'), ENT_QUOTES, 'UTF-8') ?>"
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