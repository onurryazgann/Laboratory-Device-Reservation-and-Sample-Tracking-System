<?php

require_once __DIR__ . '/../../includes/admin_check.php';
require_once __DIR__ . '/../../config/database.php';

$pageTitle = 'Admin Dashboard';
$pageCss = 'admin-dashboard.css';

$totalUsers = (int) $pdo->query("SELECT COUNT(*) AS total FROM users")->fetch()['total'];
$totalLabs = (int) $pdo->query("SELECT COUNT(*) AS total FROM laboratories")->fetch()['total'];
$totalStations = (int) $pdo->query("SELECT COUNT(*) AS total FROM workstations")->fetch()['total'];

$totalActiveReservations = (int) $pdo->query("
    SELECT COUNT(*) AS total
    FROM reservations
    WHERE status = 'active'
")->fetch()['total'];

$stmt = $pdo->query("
    SELECT
        r.reservation_id,
        r.start_time,
        r.end_time,
        r.status,
        CONCAT(u.first_name, ' ', u.last_name) AS user_full_name,
        l.lab_name,
        w.station_code,
        w.station_name
    FROM reservations r
    INNER JOIN users u
        ON r.user_id = u.user_id
    INNER JOIN laboratories l
        ON r.lab_id = l.lab_id
    INNER JOIN workstations w
        ON r.station_id = w.station_id
    ORDER BY r.created_at DESC
    LIMIT 5
");

$latestReservations = $stmt->fetchAll();

function formatAdminDateTime(?string $value): string
{
    if (!$value) {
        return '-';
    }

    try {
        return (new DateTime($value))->format('d.m.Y H:i');
    } catch (Exception $e) {
        return $value;
    }
}
function formatAdminDateOnly(?string $value): string
{
    if (!$value) {
        return '-';
    }

    try {
        return (new DateTime($value))->format('d.m.Y');
    } catch (Exception $e) {
        return $value;
    }
}

function formatAdminTimeOnly(?string $value): string
{
    if (!$value) {
        return '-';
    }

    try {
        return (new DateTime($value))->format('H:i');
    } catch (Exception $e) {
        return $value;
    }
}

function getAdminInitials(?string $name): string
{
    $name = trim((string) $name);

    if ($name === '') {
        return 'U';
    }

    $parts = preg_split('/\s+/', $name);
    $first = mb_substr($parts[0] ?? '', 0, 1);
    $last = mb_substr($parts[count($parts) - 1] ?? '', 0, 1);

    return mb_strtoupper($first . $last);
}
function adminStatusClass(string $status): string
{
    if ($status === 'active') {
        return 'is-success';
    }

    if ($status === 'cancelled') {
        return 'is-warning';
    }

    if ($status === 'completed') {
        return 'is-info';
    }

    return 'is-neutral';
}

require_once __DIR__ . '/../../includes/header.php';

?>

<section class="admindash-page">

    <!-- HERO -->
    <section class="admindash-hero reveal-on-scroll" data-admindash-tilt-card>

        <div class="admindash-hero-content">

            <span class="admindash-eyebrow">
                Admin Control Center
            </span>

            <h1>
                Manage laboratories, users and reservations from one clean workspace.
            </h1>

            <p>
                Welcome, <?= htmlspecialchars(getCurrentUserName(), ENT_QUOTES, 'UTF-8') ?>.
                Monitor system records, review the latest reservations and access management pages quickly.
            </p>

            <div class="admindash-hero-actions">
                <a href="reservations.php" class="admindash-btn admindash-btn-primary">
                    Manage Reservations
                </a>

                <a href="labs.php" class="admindash-btn admindash-btn-light">
                    Manage Laboratories
                </a>
            </div>

        </div>

        <div class="admindash-hero-visual">

            <div class="admindash-mini-panel">

                <div class="admindash-mini-header">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>

                <div class="admindash-mini-body">

                    <div class="admindash-mini-title">
                        <div>
                            <small>System Overview</small>
                            <strong>Admin Dashboard</strong>
                        </div>

                        <span>
                            <?= htmlspecialchars($_SESSION['role_name'] ?? 'admin', ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>

                    <div class="admindash-mini-list">

                        <div class="admindash-mini-item is-active">
                            <span>01</span>
                            <div>
                                <strong>Users</strong>
                                <small><?= (int) $totalUsers ?> registered user records</small>
                            </div>
                        </div>

                        <div class="admindash-mini-item">
                            <span>02</span>
                            <div>
                                <strong>Laboratories</strong>
                                <small><?= (int) $totalLabs ?> laboratory records</small>
                            </div>
                        </div>

                        <div class="admindash-mini-item">
                            <span>03</span>
                            <div>
                                <strong>Active Reservations</strong>
                                <small><?= (int) $totalActiveReservations ?> currently active reservations</small>
                            </div>
                        </div>

                    </div>

                </div>

            </div>

            <div class="admindash-floating-chip admindash-chip-one">
                <span>✓</span>
                Admin
            </div>

            <div class="admindash-floating-chip admindash-chip-two">
                <span>⌕</span>
                Monitor
            </div>

            <div class="admindash-floating-chip admindash-chip-three">
                <span>↗</span>
                Manage
            </div>

        </div>

    </section>

    <!-- KPI -->
    <section class="admindash-kpi-grid">

        <article class="admindash-kpi-card reveal-on-scroll">
            <span>Total Users</span>

            <strong>
                <?= (int) $totalUsers ?>
            </strong>

            <p>
                All registered system users.
            </p>
        </article>

        <article class="admindash-kpi-card is-lab reveal-on-scroll">
            <span>Laboratories</span>

            <strong>
                <?= (int) $totalLabs ?>
            </strong>

            <p>
                Laboratory records in the system.
            </p>
        </article>

        <article class="admindash-kpi-card is-station reveal-on-scroll">
            <span>Stations</span>

            <strong>
                <?= (int) $totalStations ?>
            </strong>

            <p>
                Workstation records connected to laboratories.
            </p>
        </article>

        <article class="admindash-kpi-card is-active reveal-on-scroll">
            <span>Active Reservations</span>

            <strong>
                <?= (int) $totalActiveReservations ?>
            </strong>

            <p>
                Reservations currently marked as active.
            </p>
        </article>

    </section>

    <!-- QUICK ACTIONS -->
    <section class="admindash-panel reveal-on-scroll">

        <div class="admindash-section-header">
            <div>
                <span class="admindash-section-label">
                    Quick Actions
                </span>

                <h2>
                    Management shortcuts.
                </h2>

                <p>
                    Access the most important admin pages directly.
                </p>
            </div>

            <span class="admindash-status-badge is-info">
                Admin Tools
            </span>
        </div>

        <div class="admindash-action-grid">

            <a href="reservations.php" class="admindash-action-item">
                <span>01</span>

                <div>
                    <strong>Manage Reservations</strong>
                    <p>Review, update and control reservation records.</p>
                </div>
            </a>

            <a href="labs.php" class="admindash-action-item">
                <span>02</span>

                <div>
                    <strong>Manage Laboratories</strong>
                    <p>Create, update or inspect laboratory records.</p>
                </div>
            </a>

            <a href="stations.php" class="admindash-action-item">
                <span>03</span>

                <div>
                    <strong>Manage Stations</strong>
                    <p>Control laboratory station information.</p>
                </div>
            </a>

            <a href="equipment.php" class="admindash-action-item">
                <span>04</span>

                <div>
                    <strong>Manage Equipment</strong>
                    <p>Inspect and manage equipment records.</p>
                </div>
            </a>

            <a href="users.php" class="admindash-action-item">
                <span>05</span>

                <div>
                    <strong>Manage Users</strong>
                    <p>Review registered user accounts.</p>
                </div>
            </a>

        </div>

    </section>

<!-- LATEST RESERVATIONS -->
<section class="admindash-panel admindash-latest-panel reveal-on-scroll">

    <div class="admindash-section-header">
        <div>
            <span class="admindash-section-label">
                Latest Reservations
            </span>

            <h2>
                Recent reservation records.
            </h2>

            <p>
                Review the latest reservation activity with user, station and schedule details.
            </p>
        </div>

        <a href="reservations.php" class="admindash-btn admindash-btn-light">
            View All
        </a>
    </div>

    <?php if (count($latestReservations) > 0): ?>

        <div class="admindash-reservation-list">

            <?php foreach ($latestReservations as $reservation): ?>
                <article class="admindash-reservation-row">

                    <div class="admindash-reservation-main">

                        <div class="admindash-reservation-id">
                            #<?= (int) $reservation['reservation_id'] ?>
                        </div>

                        <div class="admindash-user-cell">
                            <span class="admindash-user-avatar">
                                <?= htmlspecialchars(getAdminInitials($reservation['user_full_name']), ENT_QUOTES, 'UTF-8') ?>
                            </span>

                            <div>
                                <strong>
                                    <?= htmlspecialchars($reservation['user_full_name'], ENT_QUOTES, 'UTF-8') ?>
                                </strong>

                                <small>
                                    Reservation owner
                                </small>
                            </div>
                        </div>

                    </div>

                    <div class="admindash-lab-cell">
                        <span>Laboratory</span>

                        <strong>
                            <?= htmlspecialchars($reservation['lab_name'], ENT_QUOTES, 'UTF-8') ?>
                        </strong>
                    </div>

                    <div class="admindash-station-cell">
                        <span>Station</span>

                        <strong>
                            <?= htmlspecialchars($reservation['station_code'], ENT_QUOTES, 'UTF-8') ?>
                        </strong>

                        <small>
                            <?= htmlspecialchars($reservation['station_name'], ENT_QUOTES, 'UTF-8') ?>
                        </small>
                    </div>

                    <div class="admindash-schedule-cell">

                        <div class="admindash-time-box">
                            <span>Start</span>

                            <strong>
                                <?= htmlspecialchars(formatAdminDateOnly($reservation['start_time']), ENT_QUOTES, 'UTF-8') ?>
                            </strong>

                            <small>
                                <?= htmlspecialchars(formatAdminTimeOnly($reservation['start_time']), ENT_QUOTES, 'UTF-8') ?>
                            </small>
                        </div>

                        <div class="admindash-time-box">
                            <span>End</span>

                            <strong>
                                <?= htmlspecialchars(formatAdminDateOnly($reservation['end_time']), ENT_QUOTES, 'UTF-8') ?>
                            </strong>

                            <small>
                                <?= htmlspecialchars(formatAdminTimeOnly($reservation['end_time']), ENT_QUOTES, 'UTF-8') ?>
                            </small>
                        </div>

                    </div>

                    <div class="admindash-status-cell">
                        <span class="admindash-status-badge <?= adminStatusClass($reservation['status']) ?>">
                            <?= htmlspecialchars(ucfirst($reservation['status']), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>

                </article>
            <?php endforeach; ?>

        </div>

    <?php else: ?>

        <div class="admindash-empty-state">
            <span class="admindash-status-badge is-success">
                No Reservation
            </span>

            <h3>
                No reservation found.
            </h3>

            <p>
                New reservation records will be shown here.
            </p>
        </div>

    <?php endif; ?>

</section>

</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const revealItems = document.querySelectorAll('.reveal-on-scroll');

    const observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
            }
        });
    }, {
        threshold: 0.12
    });

    revealItems.forEach(function (item) {
        observer.observe(item);
    });

    const adminTiltCard = document.querySelector('[data-admindash-tilt-card]');

    if (adminTiltCard) {
        adminTiltCard.addEventListener('pointermove', function (event) {
            const rect = adminTiltCard.getBoundingClientRect();

            const x = event.clientX - rect.left;
            const y = event.clientY - rect.top;

            const rotateY = ((x / rect.width) - 0.5) * 5;
            const rotateX = ((y / rect.height) - 0.5) * -5;

            adminTiltCard.style.transform =
                'perspective(1200px) rotateX(' + rotateX + 'deg) rotateY(' + rotateY + 'deg)';
        });

        adminTiltCard.addEventListener('pointerleave', function () {
            adminTiltCard.style.transform =
                'perspective(1200px) rotateX(0deg) rotateY(0deg)';
        });
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>