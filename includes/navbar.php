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

?>
<header class="topbar">
    <div class="topbar-shell">

        <div class="topbar-inner">

            <!-- BRAND -->
            <a class="brand" href="<?= htmlspecialchars($brandUrl) ?>">
                <span class="brand-mark">LAB</span>

                <span class="brand-text">
                    <span class="brand-text-full"><?= htmlspecialchars(APP_NAME) ?></span>
                    <span class="brand-text-short">Lab Reservation</span>
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
            >
                Menu
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
                                    class="<?= navLinkActive('/public/admin/stations.php') ?>"
                                    href="<?= BASE_URL ?>admin/stations.php"
                                >
                                    Stations
                                </a>

                                <a
                                    class="<?= navLinkActive('/public/admin/equipment.php') ?>"
                                    href="<?= BASE_URL ?>admin/equipment.php"
                                >
                                    Equipment
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
                        class="<?= navLinkActive('/public/profile.php') ?>"
                        href="<?= BASE_URL ?>profile.php"
                    >
                        Profile
                    </a>

                    <div class="nav-user-chip" title="<?= htmlspecialchars($userName) ?>">
                        <span class="nav-user-name">
                            <?= htmlspecialchars($userName) ?>
                        </span>

                        <span class="nav-user-role">
                            <?= htmlspecialchars($userRole) ?>
                        </span>
                    </div>

                    <a
                        class="nav-action danger"
                        href="<?= BASE_URL ?>logout.php"
                    >
                        Logout
                    </a>

                <?php endif; ?>

            </nav>

        </div>

    </div>
</header>