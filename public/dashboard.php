<?php

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'Dashboard';
$pageCss = 'dashboard.css';
$bodyClass = 'page-dashboard';

$userId = getCurrentUserId();

/* ACTIVE RESERVATIONS */
$stmt = $pdo->prepare("
    SELECT COUNT(*) AS active_reservation_count
    FROM reservations
    WHERE user_id = :user_id
      AND status = 'active'
");

$stmt->execute([
    ':user_id' => $userId
]);

$activeReservationCount = (int) $stmt->fetch()['active_reservation_count'];

/* UPCOMING RESERVATIONS */
$stmt = $pdo->prepare("
    SELECT COUNT(*) AS upcoming_count
    FROM reservations
    WHERE user_id = :user_id
      AND status = 'active'
      AND start_time >= NOW()
");

$stmt->execute([
    ':user_id' => $userId
]);

$upcomingReservationCount = (int) $stmt->fetch()['upcoming_count'];

/* PAST RESERVATIONS */
$stmt = $pdo->prepare("
    SELECT COUNT(*) AS past_count
    FROM reservations
    WHERE user_id = :user_id
      AND end_time < NOW()
");

$stmt->execute([
    ':user_id' => $userId
]);

$pastReservationCount = (int) $stmt->fetch()['past_count'];

/* NEXT RESERVATION */
$stmt = $pdo->prepare("
    SELECT
        r.reservation_id,
        r.start_time,
        r.end_time,
        r.status,
        l.lab_name,
        w.station_code,
        w.station_name
    FROM reservations r
    INNER JOIN laboratories l
        ON r.lab_id = l.lab_id
    INNER JOIN workstations w
        ON r.station_id = w.station_id
    WHERE r.user_id = :user_id
      AND r.status = 'active'
      AND r.start_time >= NOW()
    ORDER BY r.start_time ASC
    LIMIT 1
");

$stmt->execute([
    ':user_id' => $userId
]);

$nextReservation = $stmt->fetch();

if (!function_exists('formatDashboardDateTime')) {
    function formatDashboardDateTime(?string $value): string
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
}

if (!function_exists('formatDashboardDate')) {
    function formatDashboardDate(?string $value): string
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
}

if (!function_exists('formatDashboardTime')) {
    function formatDashboardTime(?string $value): string
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
}

require_once __DIR__ . '/../includes/header.php';

?>

<section class="dashboard-page">

    <!-- HERO -->
    <section class="dashboard-hero" data-dashboard-tilt-card>

        <div class="dashboard-hero-content">

            <span class="dashboard-eyebrow">
                Student Dashboard
            </span>

            <h1>
                Welcome, <?= htmlspecialchars(getCurrentUserName(), ENT_QUOTES, 'UTF-8') ?>
            </h1>

            <p>
                Manage your laboratory reservations, review upcoming station access
                and continue your reservation process from one clean workspace.
            </p>

            <div class="dashboard-hero-actions">
                <a href="reserve.php" class="dashboard-btn dashboard-btn-primary">
                    New Reservation
                </a>

                <a href="labs.php" class="dashboard-btn dashboard-btn-light">
                    Browse Laboratories
                </a>

                <a href="my-reservations.php" class="dashboard-btn dashboard-btn-outline">
                    My Reservations
                </a>
            </div>

        </div>

        <div class="dashboard-hero-visual">

            <div class="dashboard-mini-panel">

                <div class="dashboard-mini-header">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>

                <div class="dashboard-mini-body">

                    <div class="dashboard-mini-title">
                        <div>
                            <small>Current Status</small>
                            <strong>Reservation Overview</strong>
                        </div>

                        <span>
                            Live
                        </span>
                    </div>

                    <div class="dashboard-mini-list">

                        <div class="dashboard-mini-item">
                            <span>01</span>
                            <div>
                                <strong>Active Reservations</strong>
                                <small><?= (int) $activeReservationCount ?> active records</small>
                            </div>
                        </div>

                        <div class="dashboard-mini-item">
                            <span>02</span>
                            <div>
                                <strong>Upcoming Access</strong>
                                <small><?= (int) $upcomingReservationCount ?> upcoming records</small>
                            </div>
                        </div>

                        <div class="dashboard-mini-item">
                            <span>03</span>
                            <div>
                                <strong>Past Usage</strong>
                                <small><?= (int) $pastReservationCount ?> completed or past records</small>
                            </div>
                        </div>

                    </div>

                </div>

            </div>

            <div class="dashboard-floating-chip dashboard-chip-one">
                <span>✓</span>
                Ready
            </div>

            <div class="dashboard-floating-chip dashboard-chip-two">
                <span>⏱</span>
                Upcoming
            </div>

            <div class="dashboard-floating-chip dashboard-chip-three">
                <span>📌</span>
                Tracked
            </div>

        </div>

    </section>

    <!-- KPI CARDS -->
    <section class="dashboard-kpi-grid">

        <article class="dashboard-kpi-card is-active reveal-on-scroll">
            <span class="dashboard-kpi-label">
                Active Reservations
            </span>

            <strong>
                <?= (int) $activeReservationCount ?>
            </strong>

            <p>
                Currently active reservation records.
            </p>
        </article>

        <article class="dashboard-kpi-card is-upcoming reveal-on-scroll">
            <span class="dashboard-kpi-label">
                Upcoming
            </span>

            <strong>
                <?= (int) $upcomingReservationCount ?>
            </strong>

            <p>
                Future active reservations waiting for use.
            </p>
        </article>

        <article class="dashboard-kpi-card is-past reveal-on-scroll">
            <span class="dashboard-kpi-label">
                Past Reservations
            </span>

            <strong>
                <?= (int) $pastReservationCount ?>
            </strong>

            <p>
                Reservations whose end time has passed.
            </p>
        </article>

    </section>

    <!-- MAIN GRID -->
    <section class="dashboard-main-grid">

        <!-- QUICK ACTIONS -->
        <article class="dashboard-panel dashboard-actions-card reveal-on-scroll">

            <div class="dashboard-section-header">
                <div>
                    <span class="dashboard-section-label">
                        Actions
                    </span>

                    <h2>
                        Quick Actions
                    </h2>

                    <p>
                        Continue your reservation process with one click.
                    </p>
                </div>
            </div>

            <div class="dashboard-action-grid">

                <a href="labs.php" class="dashboard-action-item">
                    <span>01</span>

                    <div>
                        <strong>Browse Laboratories</strong>
                        <p>Explore available labs, departments and station types.</p>
                    </div>
                </a>

                <a href="reserve.php" class="dashboard-action-item">
                    <span>02</span>

                    <div>
                        <strong>New Reservation</strong>
                        <p>Select a lab, choose a station and check availability.</p>
                    </div>
                </a>

                <a href="my-reservations.php" class="dashboard-action-item">
                    <span>03</span>

                    <div>
                        <strong>My Reservations</strong>
                        <p>View, edit or cancel your existing reservations.</p>
                    </div>
                </a>

                <a href="profile.php" class="dashboard-action-item">
                    <span>04</span>

                    <div>
                        <strong>Profile</strong>
                        <p>Review your student and account information.</p>
                    </div>
                </a>

            </div>

        </article>

        <!-- UPCOMING RESERVATION -->
        <article class="dashboard-panel dashboard-upcoming-card reveal-on-scroll">

            <div class="dashboard-section-header">
                <div>
                    <span class="dashboard-section-label">
                        Schedule
                    </span>

                    <h2>
                        Upcoming Reservation
                    </h2>

                    <p>
                        Your nearest active reservation.
                    </p>
                </div>

                <span class="dashboard-status-badge <?= $nextReservation ? 'is-success' : 'is-warning' ?>">
                    <?= $nextReservation ? 'Scheduled' : 'Empty' ?>
                </span>
            </div>

            <?php if ($nextReservation): ?>

                <div class="dashboard-next-date-card">
                    <span>Date</span>

                    <strong>
                        <?= htmlspecialchars(formatDashboardDate($nextReservation['start_time']), ENT_QUOTES, 'UTF-8') ?>
                    </strong>
                </div>

                <div class="dashboard-next-time-grid">

                    <div>
                        <span>Start</span>

                        <strong>
                            <?= htmlspecialchars(formatDashboardTime($nextReservation['start_time']), ENT_QUOTES, 'UTF-8') ?>
                        </strong>
                    </div>

                    <div>
                        <span>End</span>

                        <strong>
                            <?= htmlspecialchars(formatDashboardTime($nextReservation['end_time']), ENT_QUOTES, 'UTF-8') ?>
                        </strong>
                    </div>

                </div>

                <div class="dashboard-next-meta">

                    <div class="dashboard-next-meta-row">
                        <span>Laboratory</span>

                        <strong>
                            <?= htmlspecialchars($nextReservation['lab_name'], ENT_QUOTES, 'UTF-8') ?>
                        </strong>
                    </div>

                    <div class="dashboard-next-meta-row">
                        <span>Station</span>

                        <strong>
                            <?= htmlspecialchars($nextReservation['station_code'] . ' - ' . $nextReservation['station_name'], ENT_QUOTES, 'UTF-8') ?>
                        </strong>
                    </div>

                    <div class="dashboard-next-meta-row">
                        <span>Status</span>

                        <strong>
                            <?= htmlspecialchars(ucfirst($nextReservation['status']), ENT_QUOTES, 'UTF-8') ?>
                        </strong>
                    </div>

                </div>

                <div class="dashboard-upcoming-actions">
                    <a
                        href="reservation-detail.php?id=<?= (int) $nextReservation['reservation_id'] ?>"
                        class="dashboard-btn dashboard-btn-primary"
                    >
                        View Detail
                    </a>

                    <a href="my-reservations.php" class="dashboard-btn dashboard-btn-outline">
                        All Reservations
                    </a>
                </div>

            <?php else: ?>

                <div class="dashboard-empty-upcoming">
                    <span class="dashboard-status-badge is-warning">
                        No Upcoming Reservation
                    </span>

                    <h3>
                        You do not have an upcoming active reservation.
                    </h3>

                    <p>
                        Browse laboratories and create your first reservation.
                    </p>

                    <a href="labs.php" class="dashboard-btn dashboard-btn-primary">
                        Explore Laboratories
                    </a>
                </div>

            <?php endif; ?>

        </article>

    </section>

    <!-- RESERVATION GUIDE -->
    <section class="dashboard-panel dashboard-guide-card reveal-on-scroll">

        <div class="dashboard-section-header">
            <div>
                <span class="dashboard-section-label">
                    Guide
                </span>

                <h2>
                    Reservation Guide
                </h2>

                <p>
                    Follow these steps to create and manage a laboratory reservation.
                </p>
            </div>
        </div>

        <div class="dashboard-guide-steps">

            <div>
                <span>1</span>
                <strong>Browse Labs</strong>
                <p>Open the laboratories page and select a suitable laboratory.</p>
            </div>

            <div>
                <span>2</span>
                <strong>Select Station</strong>
                <p>Review station details, capacity and assigned equipment.</p>
            </div>

            <div>
                <span>3</span>
                <strong>Check Availability</strong>
                <p>Choose a date and available time slot for your reservation.</p>
            </div>

            <div>
                <span>4</span>
                <strong>Manage Reservation</strong>
                <p>View, edit or cancel your reservation from My Reservations.</p>
            </div>

        </div>

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
        threshold: 0.15
    });

    revealItems.forEach(function (item) {
        observer.observe(item);
    });

    const dashboardTiltCard = document.querySelector('[data-dashboard-tilt-card]');

    if (dashboardTiltCard) {
        dashboardTiltCard.addEventListener('pointermove', function (event) {
            const rect = dashboardTiltCard.getBoundingClientRect();

            const x = event.clientX - rect.left;
            const y = event.clientY - rect.top;

            const rotateY = ((x / rect.width) - 0.5) * 5;
            const rotateX = ((y / rect.height) - 0.5) * -5;

            dashboardTiltCard.style.transform =
                'perspective(1200px) rotateX(' + rotateX + 'deg) rotateY(' + rotateY + 'deg)';
        });

        dashboardTiltCard.addEventListener('pointerleave', function () {
            dashboardTiltCard.style.transform =
                'perspective(1200px) rotateX(0deg) rotateY(0deg)';
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>