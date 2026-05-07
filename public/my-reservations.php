<?php

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/reservation_helper.php';
require_once __DIR__ . '/../includes/csrf.php';

$userId = getCurrentUserId();

syncExpiredReservations($pdo);

$pageTitle = 'My Reservations';
$pageCss = 'my-reservations.css';
$bodyClass = 'page-my-reservations';

$statusFilter = $_GET['status'] ?? 'all';

if (!in_array($statusFilter, ['all', 'active', 'cancelled', 'completed'], true)) {
    $statusFilter = 'all';
}

$message = '';
$messageStatus = false;

if (!function_exists('isFutureReservation')) {
    function isFutureReservation(string $startTime): bool
    {
        return strtotime($startTime) > time();
    }
}

if (!function_exists('formatReservationDateTime')) {
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
}

if (!function_exists('formatReservationDate')) {
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
}

if (!function_exists('formatReservationTime')) {
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
}

if (!function_exists('reservationStatusLabel')) {
    function reservationStatusLabel(string $status): string
    {
        return ucfirst($status);
    }
}

if (!function_exists('statusFilterLabel')) {
    function statusFilterLabel(string $status): string
    {
        if ($status === 'all') {
            return 'All Reservations';
        }

        return ucfirst($status) . ' Reservations';
    }
}

if (!function_exists('myReservationStatusClass')) {
    function myReservationStatusClass(string $status): string
    {
        if ($status === 'active') {
            return 'is-success';
        }

        if ($status === 'cancelled') {
            return 'is-error';
        }

        if ($status === 'completed') {
            return 'is-info';
        }

        return 'is-warning';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();

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

<section class="myres-page" data-my-reservations-page="true">

    <!-- HERO -->
    <section class="myres-hero" data-myres-tilt-card>

        <div class="myres-hero-content">

            <span class="myres-eyebrow">
                Reservation Center
            </span>

            <h1>
                Track and manage your laboratory reservations.
            </h1>

            <p>
                Review your reservation history, filter by status, open reservation details,
                edit upcoming reservations or cancel active future reservations.
            </p>

            <div class="myres-hero-actions">
                <a href="reserve.php" class="myres-btn myres-btn-primary">
                    New Reservation
                </a>

                <a href="labs.php" class="myres-btn myres-btn-light">
                    Browse Laboratories
                </a>
            </div>

        </div>

        <div class="myres-hero-visual">

            <div class="myres-mini-panel">

                <div class="myres-mini-header">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>

                <div class="myres-mini-body">

                    <div class="myres-mini-title">
                        <div>
                            <small>Reservation Status</small>
                            <strong>Management Flow</strong>
                        </div>

                        <span>
                            Live
                        </span>
                    </div>

                    <div class="myres-mini-list">

                        <div class="myres-mini-item is-active">
                            <span>01</span>
                            <div>
                                <strong>Review Records</strong>
                                <small>See all active, cancelled and completed reservations.</small>
                            </div>
                        </div>

                        <div class="myres-mini-item">
                            <span>02</span>
                            <div>
                                <strong>Open Details</strong>
                                <small>Check laboratory, station and reservation information.</small>
                            </div>
                        </div>

                        <div class="myres-mini-item">
                            <span>03</span>
                            <div>
                                <strong>Manage Future Slots</strong>
                                <small>Edit or cancel active future reservations.</small>
                            </div>
                        </div>

                    </div>

                </div>

            </div>

            <div class="myres-floating-chip myres-chip-one">
                <span>✓</span>
                Active
            </div>

            <div class="myres-floating-chip myres-chip-two">
                <span>⏱</span>
                Upcoming
            </div>

            <div class="myres-floating-chip myres-chip-three">
                <span>📌</span>
                History
            </div>

        </div>

    </section>

    <!-- MESSAGE -->
    <?php if ($message !== ''): ?>
        <section class="myres-alert <?= $messageStatus ? 'is-success' : 'is-error' ?> reveal-on-scroll">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        </section>
    <?php endif; ?>

    <!-- KPI -->
    <section class="myres-kpi-grid">

        <article class="myres-kpi-card reveal-on-scroll">
            <span>Total</span>

            <strong>
                <?= (int) $totalCount ?>
            </strong>

            <p>
                All reservation records.
            </p>
        </article>

        <article class="myres-kpi-card is-active reveal-on-scroll">
            <span>Active</span>

            <strong>
                <?= (int) $activeCount ?>
            </strong>

            <p>
                Upcoming or currently valid reservations.
            </p>
        </article>

        <article class="myres-kpi-card is-cancelled reveal-on-scroll">
            <span>Cancelled</span>

            <strong>
                <?= (int) $cancelledCount ?>
            </strong>

            <p>
                Cancelled reservation records.
            </p>
        </article>

        <article class="myres-kpi-card is-completed reveal-on-scroll">
            <span>Completed</span>

            <strong>
                <?= (int) $completedCount ?>
            </strong>

            <p>
                Past completed reservations.
            </p>
        </article>

    </section>

    <!-- FILTER -->
    <section class="myres-filter-card reveal-on-scroll">

        <div class="myres-section-header">
            <div>
                <span class="myres-section-label">
                    Filter
                </span>

                <h2>
                    Filter reservations.
                </h2>

                <p>
                    Showing <?= (int) $visibleCount ?> result<?= $visibleCount === 1 ? '' : 's' ?> for
                    <strong><?= htmlspecialchars(statusFilterLabel($statusFilter), ENT_QUOTES, 'UTF-8') ?></strong>.
                </p>
            </div>

            <span class="myres-status-badge <?= myReservationStatusClass($statusFilter === 'all' ? 'active' : $statusFilter) ?>">
                <?= htmlspecialchars(statusFilterLabel($statusFilter), ENT_QUOTES, 'UTF-8') ?>
            </span>
        </div>

        <div class="myres-tabs">

            <a
                href="my-reservations.php?status=all"
                class="myres-tab <?= $statusFilter === 'all' ? 'is-active' : '' ?>"
            >
                All
                <span><?= (int) $totalCount ?></span>
            </a>

            <a
                href="my-reservations.php?status=active"
                class="myres-tab <?= $statusFilter === 'active' ? 'is-active' : '' ?>"
            >
                Active
                <span><?= (int) $activeCount ?></span>
            </a>

            <a
                href="my-reservations.php?status=cancelled"
                class="myres-tab <?= $statusFilter === 'cancelled' ? 'is-active' : '' ?>"
            >
                Cancelled
                <span><?= (int) $cancelledCount ?></span>
            </a>

            <a
                href="my-reservations.php?status=completed"
                class="myres-tab <?= $statusFilter === 'completed' ? 'is-active' : '' ?>"
            >
                Completed
                <span><?= (int) $completedCount ?></span>
            </a>

        </div>

    </section>

    <!-- LIST -->
    <?php if (count($reservations) > 0): ?>

        <section class="myres-list-section reveal-on-scroll">

            <div class="myres-section-header">
                <div>
                    <span class="myres-section-label">
                        Reservation List
                    </span>

                    <h2>
                        Your reservation records.
                    </h2>

                    <p>
                        Open details for full information. Future active reservations can be edited or cancelled.
                    </p>
                </div>
            </div>

            <div class="myres-card-grid">

                <?php foreach ($reservations as $reservation): ?>
                    <?php
                    $canCancel =
                        $reservation['status'] === 'active'
                        && isFutureReservation($reservation['start_time']);

                    $canEdit = $canCancel;

                    $status = $reservation['status'];
                    $purpose = trim($reservation['purpose'] ?? '');
                    ?>

                    <article class="myres-card reveal-on-scroll">

                        <div class="myres-card-header">

                            <div>
                                <span class="myres-card-code">
                                    <?= htmlspecialchars($reservation['lab_code'], ENT_QUOTES, 'UTF-8') ?>
                                </span>

                                <h3>
                                    <?= htmlspecialchars($reservation['lab_name'], ENT_QUOTES, 'UTF-8') ?>
                                </h3>
                            </div>

                            <span class="myres-status-badge <?= myReservationStatusClass($status) ?>">
                                <?= htmlspecialchars(reservationStatusLabel($status), ENT_QUOTES, 'UTF-8') ?>
                            </span>

                        </div>

                        <div class="myres-time-panel">

                            <div>
                                <span>Date</span>

                                <strong>
                                    <?= htmlspecialchars(formatReservationDate($reservation['start_time']), ENT_QUOTES, 'UTF-8') ?>
                                </strong>
                            </div>

                            <div>
                                <span>Time</span>

                                <strong>
                                    <?= htmlspecialchars(formatReservationTime($reservation['start_time']), ENT_QUOTES, 'UTF-8') ?>
                                    —
                                    <?= htmlspecialchars(formatReservationTime($reservation['end_time']), ENT_QUOTES, 'UTF-8') ?>
                                </strong>
                            </div>

                        </div>

                        <div class="myres-meta">

                            <div class="myres-meta-row">
                                <span>Station</span>

                                <strong>
                                    <?= htmlspecialchars($reservation['station_code'], ENT_QUOTES, 'UTF-8') ?>
                                    —
                                    <?= htmlspecialchars($reservation['station_name'], ENT_QUOTES, 'UTF-8') ?>
                                </strong>
                            </div>

                            <div class="myres-meta-row">
                                <span>Start</span>

                                <strong>
                                    <?= htmlspecialchars(formatReservationDateTime($reservation['start_time']), ENT_QUOTES, 'UTF-8') ?>
                                </strong>
                            </div>

                            <div class="myres-meta-row">
                                <span>End</span>

                                <strong>
                                    <?= htmlspecialchars(formatReservationDateTime($reservation['end_time']), ENT_QUOTES, 'UTF-8') ?>
                                </strong>
                            </div>

                            <div class="myres-meta-row">
                                <span>Purpose</span>

                                <strong>
                                    <?= htmlspecialchars($purpose !== '' ? $purpose : '-', ENT_QUOTES, 'UTF-8') ?>
                                </strong>
                            </div>

                        </div>

                        <div class="myres-card-actions">

                            <a
                                href="reservation-detail.php?id=<?= (int) $reservation['reservation_id'] ?>"
                                class="myres-btn myres-btn-outline"
                            >
                                View Detail
                            </a>

                            <?php if ($canEdit): ?>
                                <a
                                    href="reservation-edit.php?id=<?= (int) $reservation['reservation_id'] ?>"
                                    class="myres-btn myres-btn-light"
                                >
                                    Edit
                                </a>
                            <?php endif; ?>

                            <?php if ($canCancel): ?>
                                <form
                                    method="POST"
                                    action="my-reservations.php?status=<?= htmlspecialchars($statusFilter, ENT_QUOTES, 'UTF-8') ?>"
                                    onsubmit="return confirm('Are you sure you want to cancel this reservation?');"
                                    class="myres-cancel-form"
                                >
                                    <?= csrfInput() ?>
                                    <input
                                        type="hidden"
                                        name="reservation_id"
                                        value="<?= (int) $reservation['reservation_id'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        name="action"
                                        value="cancel"
                                        class="myres-btn myres-btn-danger"
                                    >
                                        Cancel
                                    </button>
                                </form>
                            <?php endif; ?>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        </section>

    <?php else: ?>

        <section class="myres-empty-state reveal-on-scroll">

            <span class="myres-status-badge is-warning">
                No Reservation
            </span>

            <h3>
                No reservation found.
            </h3>

            <p>
                Start by creating your first laboratory reservation.
            </p>

            <div class="myres-empty-actions">
                <a href="reserve.php" class="myres-btn myres-btn-primary">
                    Create Reservation
                </a>

                <a href="labs.php" class="myres-btn myres-btn-outline">
                    Browse Laboratories
                </a>
            </div>

        </section>

    <?php endif; ?>

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

    const myresTiltCard = document.querySelector('[data-myres-tilt-card]');

    if (myresTiltCard) {
        myresTiltCard.addEventListener('pointermove', function (event) {
            const rect = myresTiltCard.getBoundingClientRect();

            const x = event.clientX - rect.left;
            const y = event.clientY - rect.top;

            const rotateY = ((x / rect.width) - 0.5) * 5;
            const rotateX = ((y / rect.height) - 0.5) * -5;

            myresTiltCard.style.transform =
                'perspective(1200px) rotateX(' + rotateX + 'deg) rotateY(' + rotateY + 'deg)';
        });

        myresTiltCard.addEventListener('pointerleave', function () {
            myresTiltCard.style.transform =
                'perspective(1200px) rotateX(0deg) rotateY(0deg)';
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>