<?php

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'Dashboard';
$pageCss = 'dashboard.css';

$userId = getCurrentUserId();

/* ACTIVE */
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

/* UPCOMING */
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

/* PAST */
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

require_once __DIR__ . '/../includes/header.php';

?>

<section class="page-section dashboard-page">
    <div class="container">

        <!-- HERO -->
        <div class="card dashboard-hero-card" style="margin-bottom:32px;">
            <div class="dashboard-hero-content">

                <div>
                    <span class="badge badge-info">
                        Student Dashboard
                    </span>

                    <h1 class="section-title" style="margin-bottom:8px; margin-top:16px;">
                        Welcome, <?= htmlspecialchars(getCurrentUserName()) ?>
                    </h1>

                    <p class="section-subtitle" style="margin-bottom:0;">
                        Manage your laboratory reservations, view upcoming station access
                        and continue your reservation process from one clean dashboard.
                    </p>
                </div>

                <div class="dashboard-hero-actions">
                    <a href="reserve.php" class="btn btn-primary">
                        New Reservation
                    </a>

                    <a href="labs.php" class="btn btn-outline">
                        Browse Laboratories
                    </a>
                </div>

            </div>
        </div>

        <!-- KPI -->
        <div class="dashboard-kpi-grid" style="margin-bottom:32px;">

            <div class="card card-hover dashboard-kpi-card is-active">
                <span class="dashboard-kpi-label">
                    Active Reservations
                </span>

                <strong>
                    <?= (int) $activeReservationCount ?>
                </strong>

                <p>
                    Currently active reservation records.
                </p>
            </div>

            <div class="card card-hover dashboard-kpi-card is-upcoming">
                <span class="dashboard-kpi-label">
                    Upcoming
                </span>

                <strong>
                    <?= (int) $upcomingReservationCount ?>
                </strong>

                <p>
                    Future active reservations waiting for use.
                </p>
            </div>

            <div class="card card-hover dashboard-kpi-card is-past">
                <span class="dashboard-kpi-label">
                    Past Reservations
                </span>

                <strong>
                    <?= (int) $pastReservationCount ?>
                </strong>

                <p>
                    Reservations whose end time has passed.
                </p>
            </div>

        </div>

        <!-- MAIN GRID -->
        <div class="dashboard-main-grid">

            <!-- QUICK ACTIONS -->
            <div class="card dashboard-actions-card">

                <div class="dashboard-section-header">
                    <div>
                        <h2 style="margin-top:0; margin-bottom:8px;">
                            Quick Actions
                        </h2>

                        <p class="section-subtitle" style="margin-bottom:0;">
                            Continue your reservation process with one click.
                        </p>
                    </div>

                    <span class="badge badge-info">
                        Actions
                    </span>
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

            </div>

            <!-- UPCOMING RESERVATION -->
            <div class="card dashboard-upcoming-card">

                <div class="dashboard-section-header">
                    <div>
                        <h2 style="margin-top:0; margin-bottom:8px;">
                            Upcoming Reservation
                        </h2>

                        <p class="section-subtitle" style="margin-bottom:0;">
                            Your nearest active reservation.
                        </p>
                    </div>

                    <span class="badge <?= $nextReservation ? 'badge-success' : 'badge-warning' ?>">
                        <?= $nextReservation ? 'Scheduled' : 'Empty' ?>
                    </span>
                </div>

                <?php if ($nextReservation): ?>

                    <div class="dashboard-next-date-card">
                        <span>Date</span>

                        <strong>
                            <?= htmlspecialchars(formatDashboardDate($nextReservation['start_time'])) ?>
                        </strong>
                    </div>

                    <div class="dashboard-next-time-grid">

                        <div>
                            <span>Start</span>

                            <strong>
                                <?= htmlspecialchars(formatDashboardTime($nextReservation['start_time'])) ?>
                            </strong>
                        </div>

                        <div>
                            <span>End</span>

                            <strong>
                                <?= htmlspecialchars(formatDashboardTime($nextReservation['end_time'])) ?>
                            </strong>
                        </div>

                    </div>

                    <div class="dashboard-next-meta">

                        <div class="dashboard-next-meta-row">
                            <span>Laboratory</span>

                            <strong>
                                <?= htmlspecialchars($nextReservation['lab_name']) ?>
                            </strong>
                        </div>

                        <div class="dashboard-next-meta-row">
                            <span>Station</span>

                            <strong>
                                <?= htmlspecialchars($nextReservation['station_code'] . ' - ' . $nextReservation['station_name']) ?>
                            </strong>
                        </div>

                        <div class="dashboard-next-meta-row">
                            <span>Status</span>

                            <strong>
                                <?= htmlspecialchars(ucfirst($nextReservation['status'])) ?>
                            </strong>
                        </div>

                    </div>

                    <div class="dashboard-upcoming-actions">
                        <a
                            href="reservation-detail.php?id=<?= (int) $nextReservation['reservation_id'] ?>"
                            class="btn btn-primary"
                        >
                            View Detail
                        </a>

                        <a href="my-reservations.php" class="btn btn-outline">
                            All Reservations
                        </a>
                    </div>

                <?php else: ?>

                    <div class="dashboard-empty-upcoming">
                        <span class="badge badge-warning">
                            No Upcoming Reservation
                        </span>

                        <h3>
                            You do not have an upcoming active reservation.
                        </h3>

                        <p>
                            Browse laboratories and create your first reservation.
                        </p>

                        <a href="labs.php" class="btn btn-primary">
                            Explore Laboratories
                        </a>
                    </div>

                <?php endif; ?>

            </div>

        </div>

        <!-- RESERVATION GUIDE -->
        <div class="card dashboard-guide-card" style="margin-top:32px;">

            <div class="dashboard-section-header">
                <div>
                    <h2 style="margin-top:0; margin-bottom:8px;">
                        Reservation Guide
                    </h2>

                    <p class="section-subtitle" style="margin-bottom:0;">
                        Follow these steps to create and manage a laboratory reservation.
                    </p>
                </div>

                <span class="badge badge-info">
                    Guide
                </span>
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

        </div>

    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>