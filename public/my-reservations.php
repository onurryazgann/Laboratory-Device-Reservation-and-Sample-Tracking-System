<?php

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/reservation_helper.php';

$userId = getCurrentUserId();

syncExpiredReservations($pdo);

$pageTitle = 'My Reservations';
$pageCss = 'my-reservations.css';

$statusFilter = $_GET['status'] ?? 'all';

if (!in_array($statusFilter, ['all', 'active', 'cancelled', 'completed'], true)) {
    $statusFilter = 'all';
}

$message = '';
$messageStatus = false;

function isFutureReservation(string $startTime): bool
{
    return strtotime($startTime) > time();
}

function formatReservationDateTime(?string $value): string
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

function formatReservationDate(?string $value): string
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

function formatReservationTime(?string $value): string
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

function reservationBadgeClass(string $status): string
{
    if ($status === 'active') {
        return 'badge-success';
    }

    if ($status === 'cancelled') {
        return 'badge-error';
    }

    if ($status === 'completed') {
        return 'badge-info';
    }

    return 'badge-warning';
}

function reservationStatusLabel(string $status): string
{
    return ucfirst($status);
}

function statusFilterLabel(string $status): string
{
    if ($status === 'all') {
        return 'All Reservations';
    }

    return ucfirst($status) . ' Reservations';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $reservationId = filter_input(INPUT_POST, 'reservation_id', FILTER_VALIDATE_INT);

    if ($action === 'cancel' && $reservationId) {
        $reservation = getReservationDetail($pdo, (int) $reservationId);

        if (!$reservation) {
            $messageStatus = false;
            $message = 'Reservation not found.';
        } elseif ((int) $reservation['user_id'] !== (int) $userId) {
            $messageStatus = false;
            $message = 'You are not allowed to cancel this reservation.';
        } elseif ($reservation['status'] !== 'active') {
            $messageStatus = false;
            $message = 'Only active reservations can be cancelled.';
        } elseif (!isFutureReservation($reservation['start_time'])) {
            $messageStatus = false;
            $message = 'Past or currently running reservations cannot be cancelled from this page.';
        } else {
            try {
                $pdo->beginTransaction();

                cancelReservation($pdo, (int) $reservationId);

                addReservationStatusHistory(
                    $pdo,
                    (int) $reservationId,
                    'active',
                    'cancelled',
                    (int) $userId,
                    'Reservation cancelled by user.'
                );

                $pdo->commit();

                $messageStatus = true;
                $message = 'Reservation cancelled successfully.';
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $messageStatus = false;
                $message = DEBUG_MODE
                    ? 'Reservation cancellation failed: ' . $e->getMessage()
                    : 'Reservation cancellation failed.';
            }
        }
    }
}

syncExpiredReservations($pdo);

$reservations = getUserReservations($pdo, (int) $userId, $statusFilter);
$allReservationsForKpi = getUserReservations($pdo, (int) $userId, 'all');

$activeCount = 0;
$cancelledCount = 0;
$completedCount = 0;

foreach ($allReservationsForKpi as $reservation) {
    if ($reservation['status'] === 'active') {
        $activeCount++;
    } elseif ($reservation['status'] === 'cancelled') {
        $cancelledCount++;
    } elseif ($reservation['status'] === 'completed') {
        $completedCount++;
    }
}

$totalCount = count($allReservationsForKpi);
$visibleCount = count($reservations);

require_once __DIR__ . '/../includes/header.php';

?>

<section class="page-section" data-my-reservations-page="true">
    <div class="container">

        <!-- HERO -->
        <div class="card my-reservations-hero-card" style="margin-bottom:32px;">
            <div class="my-reservations-hero-content">

                <div>
                    <span class="badge badge-info">
                        Reservation Center
                    </span>

                    <h1 class="section-title" style="margin-bottom:8px; margin-top:16px;">
                        My Reservations
                    </h1>

                    <p class="section-subtitle" style="margin-bottom:0;">
                        Track, manage and review your full laboratory reservation history.
                    </p>
                </div>

                <div class="my-reservations-hero-actions">
                    <a href="reserve.php" class="btn btn-primary">
                        New Reservation
                    </a>

                    <a href="labs.php" class="btn btn-outline">
                        Browse Laboratories
                    </a>
                </div>

            </div>
        </div>

        <!-- MESSAGE -->
        <?php if ($message !== ''): ?>
            <div
                class="alert <?= $messageStatus ? 'alert-success' : 'alert-error' ?>"
                style="margin-bottom:24px;"
            >
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- KPI -->
        <div class="my-reservations-kpi-grid" style="margin-bottom:32px;">

            <div class="card card-hover my-reservation-kpi-card">
                <span class="my-reservation-kpi-label">Total</span>

                <strong>
                    <?= (int) $totalCount ?>
                </strong>

                <p>All reservation records</p>
            </div>

            <div class="card card-hover my-reservation-kpi-card is-active">
                <span class="my-reservation-kpi-label">Active</span>

                <strong>
                    <?= (int) $activeCount ?>
                </strong>

                <p>Upcoming or currently valid reservations</p>
            </div>

            <div class="card card-hover my-reservation-kpi-card is-cancelled">
                <span class="my-reservation-kpi-label">Cancelled</span>

                <strong>
                    <?= (int) $cancelledCount ?>
                </strong>

                <p>Cancelled reservation records</p>
            </div>

            <div class="card card-hover my-reservation-kpi-card is-completed">
                <span class="my-reservation-kpi-label">Completed</span>

                <strong>
                    <?= (int) $completedCount ?>
                </strong>

                <p>Past completed reservations</p>
            </div>

        </div>

        <!-- FILTER -->
        <div class="card my-reservations-filter-card" style="margin-bottom:32px;">
            <div class="my-reservations-filter-header">
                <div>
                    <h2 style="margin-top:0; margin-bottom:8px;">
                        Filter Reservations
                    </h2>

                    <p class="section-subtitle" style="margin-bottom:0;">
                        Showing <?= (int) $visibleCount ?> result<?= $visibleCount === 1 ? '' : 's' ?> for:
                        <strong><?= htmlspecialchars(statusFilterLabel($statusFilter)) ?></strong>
                    </p>
                </div>

                <span class="badge <?= reservationBadgeClass($statusFilter === 'all' ? 'active' : $statusFilter) ?>">
                    <?= htmlspecialchars(statusFilterLabel($statusFilter)) ?>
                </span>
            </div>

            <div class="my-reservations-tabs">
                <a
                    href="my-reservations.php?status=all"
                    class="my-reservations-tab <?= $statusFilter === 'all' ? 'is-active' : '' ?>"
                >
                    All
                    <span><?= (int) $totalCount ?></span>
                </a>

                <a
                    href="my-reservations.php?status=active"
                    class="my-reservations-tab <?= $statusFilter === 'active' ? 'is-active' : '' ?>"
                >
                    Active
                    <span><?= (int) $activeCount ?></span>
                </a>

                <a
                    href="my-reservations.php?status=cancelled"
                    class="my-reservations-tab <?= $statusFilter === 'cancelled' ? 'is-active' : '' ?>"
                >
                    Cancelled
                    <span><?= (int) $cancelledCount ?></span>
                </a>

                <a
                    href="my-reservations.php?status=completed"
                    class="my-reservations-tab <?= $statusFilter === 'completed' ? 'is-active' : '' ?>"
                >
                    Completed
                    <span><?= (int) $completedCount ?></span>
                </a>
            </div>
        </div>

        <!-- LIST -->
        <?php if (count($reservations) > 0): ?>

            <div class="my-reservation-card-grid">

                <?php foreach ($reservations as $reservation): ?>
                    <?php
                    $canCancel =
                        $reservation['status'] === 'active'
                        && isFutureReservation($reservation['start_time']);

                    $canEdit = $canCancel;

                    $status = $reservation['status'];
                    $purpose = trim($reservation['purpose'] ?? '');
                    ?>

                    <article class="card card-hover my-reservation-card">

                        <div class="my-reservation-card-header">

                            <div>
                                <h3 class="my-reservation-title">
                                    <?= htmlspecialchars($reservation['lab_code']) ?>
                                    —
                                    <?= htmlspecialchars($reservation['lab_name']) ?>
                                </h3>
                            </div>

                            <span class="badge <?= reservationBadgeClass($status) ?>">
                                <?= htmlspecialchars(reservationStatusLabel($status)) ?>
                            </span>

                        </div>

                        <div class="my-reservation-time-panel">
                            <div>
                                <span>Date</span>
                                <strong>
                                    <?= htmlspecialchars(formatReservationDate($reservation['start_time'])) ?>
                                </strong>
                            </div>

                            <div>
                                <span>Time</span>
                                <strong>
                                    <?= htmlspecialchars(formatReservationTime($reservation['start_time'])) ?>
                                    —
                                    <?= htmlspecialchars(formatReservationTime($reservation['end_time'])) ?>
                                </strong>
                            </div>
                        </div>

                        <div class="my-reservation-meta">

                            <div class="my-reservation-meta-row">
                                <span>Station</span>

                                <strong>
                                    <?= htmlspecialchars($reservation['station_code']) ?>
                                    —
                                    <?= htmlspecialchars($reservation['station_name']) ?>
                                </strong>
                            </div>

                            <div class="my-reservation-meta-row">
                                <span>Start</span>

                                <strong>
                                    <?= htmlspecialchars(formatReservationDateTime($reservation['start_time'])) ?>
                                </strong>
                            </div>

                            <div class="my-reservation-meta-row">
                                <span>End</span>

                                <strong>
                                    <?= htmlspecialchars(formatReservationDateTime($reservation['end_time'])) ?>
                                </strong>
                            </div>

                            <div class="my-reservation-meta-row">
                                <span>Purpose</span>

                                <strong>
                                    <?= htmlspecialchars($purpose !== '' ? $purpose : '-') ?>
                                </strong>
                            </div>

                        </div>

                        <div class="my-reservation-actions">

                            <a
                                href="reservation-detail.php?id=<?= (int) $reservation['reservation_id'] ?>"
                                class="btn btn-outline"
                            >
                                View Detail
                            </a>

                            <?php if ($canEdit): ?>
                                <a
                                    href="reservation-edit.php?id=<?= (int) $reservation['reservation_id'] ?>"
                                    class="btn btn-secondary"
                                >
                                    Edit
                                </a>
                            <?php endif; ?>

                            <?php if ($canCancel): ?>
                                <form
                                    method="POST"
                                    action="my-reservations.php?status=<?= htmlspecialchars($statusFilter) ?>"
                                    onsubmit="return confirm('Are you sure you want to cancel this reservation?');"
                                    class="my-reservation-cancel-form"
                                >
                                    <input
                                        type="hidden"
                                        name="reservation_id"
                                        value="<?= (int) $reservation['reservation_id'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        name="action"
                                        value="cancel"
                                        class="btn btn-danger-soft"
                                    >
                                        Cancel
                                    </button>
                                </form>
                            <?php endif; ?>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="card my-reservations-empty-state">
                <span class="badge badge-warning">
                    No Reservation
                </span>

                <h3>No reservation found.</h3>

                <p class="section-subtitle">
                    Start by creating your first laboratory reservation.
                </p>

                <div class="my-reservations-empty-actions">
                    <a href="reserve.php" class="btn btn-primary">
                        Create Reservation
                    </a>

                    <a href="labs.php" class="btn btn-outline">
                        Browse Laboratories
                    </a>
                </div>
            </div>

        <?php endif; ?>

    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>