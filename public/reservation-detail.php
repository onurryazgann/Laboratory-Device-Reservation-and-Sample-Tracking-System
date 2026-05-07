<?php

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/reservation_helper.php';

$userId = getCurrentUserId();

syncExpiredReservations($pdo);

$reservationId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$reservationId) {
    http_response_code(400);
    die('Invalid reservation ID.');
}

$reservation = getReservationDetail($pdo, (int) $reservationId);

if (!$reservation) {
    http_response_code(404);
    die('Reservation not found.');
}

if ((int) $reservation['user_id'] !== (int) $userId) {
    http_response_code(403);
    die('You are not allowed to view this reservation.');
}

$history = getReservationStatusHistory($pdo, (int) $reservationId);

$pageTitle = 'Reservation Detail';
$pageCss = 'reservation-detail.css';
$bodyClass = 'page-reservation-detail';

if (!function_exists('formatReservationDetailDateTime')) {
    function formatReservationDetailDateTime(?string $value): string
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

if (!function_exists('formatReservationDetailDate')) {
    function formatReservationDetailDate(?string $value): string
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

if (!function_exists('formatReservationDetailTime')) {
    function formatReservationDetailTime(?string $value): string
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

if (!function_exists('reservationDetailStatusClass')) {
    function reservationDetailStatusClass(string $status): string
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

        return 'is-warning';
    }
}

if (!function_exists('canEditReservationFromDetail')) {
    function canEditReservationFromDetail(array $reservation): bool
    {
        if (($reservation['status'] ?? '') !== 'active') {
            return false;
        }

        return strtotime($reservation['start_time']) > time();
    }
}

$canEdit = canEditReservationFromDetail($reservation);
$purpose = trim((string) ($reservation['purpose'] ?? ''));

require_once __DIR__ . '/../includes/header.php';

?>

<section class="detail-page">

    <!-- TOPBAR -->
    <div class="detail-topbar reveal-on-scroll">

        <a href="my-reservations.php" class="detail-btn detail-btn-outline">
            ← Back to My Reservations
        </a>

        <?php if ($canEdit): ?>
            <a
                href="reservation-edit.php?id=<?= (int) $reservation['reservation_id'] ?>"
                class="detail-btn detail-btn-primary"
            >
                Edit Reservation
            </a>
        <?php else: ?>
            <button
                type="button"
                class="detail-btn detail-btn-disabled"
                disabled
                title="Only future active reservations can be edited."
            >
                Edit Locked
            </button>
        <?php endif; ?>

    </div>

    <!-- HERO -->
    <section class="detail-hero reveal-on-scroll" data-detail-tilt-card>

        <div class="detail-hero-content">

            <span class="detail-eyebrow">
                Reservation Detail
            </span>

            <h1>
                <?= htmlspecialchars($reservation['lab_code'] . ' - ' . $reservation['station_code'], ENT_QUOTES, 'UTF-8') ?>
            </h1>

            <p>
                <?= htmlspecialchars($purpose !== '' ? $purpose : 'No purpose provided.', ENT_QUOTES, 'UTF-8') ?>
            </p>

            <div class="detail-hero-actions">

                <a href="my-reservations.php" class="detail-btn detail-btn-light">
                    Reservation List
                </a>

                <?php if ($canEdit): ?>
                    <a
                        href="reservation-edit.php?id=<?= (int) $reservation['reservation_id'] ?>"
                        class="detail-btn detail-btn-primary"
                    >
                        Edit This Reservation
                    </a>
                <?php endif; ?>

            </div>

        </div>

        <div class="detail-status-panel">

            <div class="detail-status-card">

                <div class="detail-status-header">
                    <span>Reservation #<?= (int) $reservation['reservation_id'] ?></span>

                    <strong class="detail-status-badge <?= reservationDetailStatusClass($reservation['status']) ?>">
                        <?= htmlspecialchars(ucfirst($reservation['status']), ENT_QUOTES, 'UTF-8') ?>
                    </strong>
                </div>

                <div class="detail-status-meta">

                    <div>
                        <span>Created At</span>
                        <strong>
                            <?= htmlspecialchars(formatReservationDetailDateTime($reservation['created_at'] ?? null), ENT_QUOTES, 'UTF-8') ?>
                        </strong>
                    </div>

                    <div>
                        <span>Updated At</span>
                        <strong>
                            <?= htmlspecialchars(formatReservationDetailDateTime($reservation['updated_at'] ?? null), ENT_QUOTES, 'UTF-8') ?>
                        </strong>
                    </div>

                    <div>
                        <span>Edit Status</span>
                        <strong>
                            <?= $canEdit ? 'Editable' : 'Locked' ?>
                        </strong>
                    </div>

                </div>

            </div>

        </div>

    </section>

    <!-- TIME CARDS -->
    <section class="detail-time-grid reveal-on-scroll">

        <article class="detail-time-card">
            <span>Date</span>

            <strong>
                <?= htmlspecialchars(formatReservationDetailDate($reservation['start_time']), ENT_QUOTES, 'UTF-8') ?>
            </strong>

            <p>Reservation date</p>
        </article>

        <article class="detail-time-card">
            <span>Start Time</span>

            <strong>
                <?= htmlspecialchars(formatReservationDetailTime($reservation['start_time']), ENT_QUOTES, 'UTF-8') ?>
            </strong>

            <p>Station access begins</p>
        </article>

        <article class="detail-time-card">
            <span>End Time</span>

            <strong>
                <?= htmlspecialchars(formatReservationDetailTime($reservation['end_time']), ENT_QUOTES, 'UTF-8') ?>
            </strong>

            <p>Station access ends</p>
        </article>

    </section>

    <!-- RESERVATION INFO -->
    <section class="detail-panel reveal-on-scroll">

        <div class="detail-section-header">
            <div>
                <span class="detail-section-label">
                    Reservation Information
                </span>

                <h2>
                    Laboratory and station details.
                </h2>

                <p>
                    Review the selected laboratory, station and technical reservation context.
                </p>
            </div>

            <span class="detail-status-badge <?= reservationDetailStatusClass($reservation['status']) ?>">
                <?= htmlspecialchars(ucfirst($reservation['status']), ENT_QUOTES, 'UTF-8') ?>
            </span>
        </div>

        <div class="detail-info-grid">

            <div class="detail-info-row">
                <span>Laboratory</span>
                <strong><?= htmlspecialchars($reservation['lab_name'], ENT_QUOTES, 'UTF-8') ?></strong>
            </div>

            <div class="detail-info-row">
                <span>Lab Code</span>
                <strong><?= htmlspecialchars($reservation['lab_code'], ENT_QUOTES, 'UTF-8') ?></strong>
            </div>

            <div class="detail-info-row">
                <span>Lab Type</span>
                <strong><?= htmlspecialchars($reservation['lab_type'] ?? '-', ENT_QUOTES, 'UTF-8') ?></strong>
            </div>

            <div class="detail-info-row">
                <span>Location</span>
                <strong><?= htmlspecialchars($reservation['location'] ?? '-', ENT_QUOTES, 'UTF-8') ?></strong>
            </div>

            <div class="detail-info-row">
                <span>Station</span>
                <strong><?= htmlspecialchars($reservation['station_name'], ENT_QUOTES, 'UTF-8') ?></strong>
            </div>

            <div class="detail-info-row">
                <span>Station Code</span>
                <strong><?= htmlspecialchars($reservation['station_code'], ENT_QUOTES, 'UTF-8') ?></strong>
            </div>

            <div class="detail-info-row">
                <span>Capacity</span>
                <strong><?= (int) $reservation['capacity'] ?></strong>
            </div>

            <div class="detail-info-row">
                <span>Station Status</span>
                <strong><?= htmlspecialchars(ucfirst($reservation['station_status']), ENT_QUOTES, 'UTF-8') ?></strong>
            </div>

        </div>

    </section>

    <!-- USER INFO -->
    <section class="detail-panel reveal-on-scroll">

        <div class="detail-section-header">
            <div>
                <span class="detail-section-label">
                    User Information
                </span>

                <h2>
                    Reservation owner.
                </h2>

                <p>
                    Account information connected with this reservation record.
                </p>
            </div>
        </div>

        <div class="detail-info-grid">

            <div class="detail-info-row">
                <span>Name</span>
                <strong><?= htmlspecialchars($reservation['user_full_name'], ENT_QUOTES, 'UTF-8') ?></strong>
            </div>

            <div class="detail-info-row">
                <span>Email</span>
                <strong><?= htmlspecialchars($reservation['user_email'] ?? '-', ENT_QUOTES, 'UTF-8') ?></strong>
            </div>

            <div class="detail-info-row">
                <span>Phone</span>
                <strong><?= htmlspecialchars($reservation['user_phone'] ?? '-', ENT_QUOTES, 'UTF-8') ?></strong>
            </div>

        </div>

    </section>

    <!-- HISTORY -->
    <section class="detail-panel reveal-on-scroll">

        <div class="detail-section-header">
            <div>
                <span class="detail-section-label">
                    Status History
                </span>

                <h2>
                    Reservation status timeline.
                </h2>

                <p>
                    All status changes related to this reservation are listed below.
                </p>
            </div>
        </div>

        <?php if (count($history) > 0): ?>

            <div class="detail-table-wrapper">
                <table class="detail-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Old Status</th>
                            <th>New Status</th>
                            <th>Changed By</th>
                            <th>Note</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($history as $item): ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars(formatReservationDetailDateTime($item['changed_at']), ENT_QUOTES, 'UTF-8') ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($item['old_status'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($item['new_status'], ENT_QUOTES, 'UTF-8') ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($item['changed_by_name'] ?? 'System', ENT_QUOTES, 'UTF-8') ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($item['note'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php else: ?>

            <div class="detail-empty-state">
                <span class="detail-status-badge is-info">
                    No History
                </span>

                <h3>
                    No status history found.
                </h3>

                <p>
                    Status changes will appear here when this reservation is updated.
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

    const detailTiltCard = document.querySelector('[data-detail-tilt-card]');

    if (detailTiltCard) {
        detailTiltCard.addEventListener('pointermove', function (event) {
            const rect = detailTiltCard.getBoundingClientRect();

            const x = event.clientX - rect.left;
            const y = event.clientY - rect.top;

            const rotateY = ((x / rect.width) - 0.5) * 5;
            const rotateX = ((y / rect.height) - 0.5) * -5;

            detailTiltCard.style.transform =
                'perspective(1200px) rotateX(' + rotateX + 'deg) rotateY(' + rotateY + 'deg)';
        });

        detailTiltCard.addEventListener('pointerleave', function () {
            detailTiltCard.style.transform =
                'perspective(1200px) rotateX(0deg) rotateY(0deg)';
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>