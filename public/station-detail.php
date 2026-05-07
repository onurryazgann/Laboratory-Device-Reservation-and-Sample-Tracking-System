<?php

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/lab_helper.php';
require_once __DIR__ . '/../helpers/reservation_helper.php';

syncExpiredReservations($pdo);

$stationId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$stationId) {
    http_response_code(400);
    die('Invalid station ID.');
}

$station = getStationById($pdo, (int) $stationId);

if (!$station) {
    http_response_code(404);
    die('Station not found.');
}

$equipmentList = getStationEquipment($pdo, (int) $stationId);
$equipmentSummary = getStationEquipmentSummary($pdo, (int) $stationId);
$availability = getStationComputedAvailability($pdo, (int) $stationId);
$upcomingReservations = getUpcomingReservationsByStation($pdo, (int) $stationId, 10);

$pageTitle = 'Station Detail';
$pageCss = 'station-detail.css';
$bodyClass = 'page-station-detail';

function formatStationDetailDateTime(?string $value): string
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

function formatStationDetailTime(?string $value): string
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

function formatStationDetailType(?string $type): string
{
    $type = trim((string) $type);

    if ($type === '') {
        return '-';
    }

    return ucwords(str_replace('_', ' ', $type));
}

function stationDetailStatusClass(string $status): string
{
    if ($status === 'active' || $status === 'available') {
        return 'is-success';
    }

    if ($status === 'maintenance' || $status === 'Reserved Now') {
        return 'is-warning';
    }

    if ($status === 'passive' || $status === 'closed') {
        return 'is-error';
    }

    return 'is-info';
}

function stationDetailAvailabilityClass(array $availability): string
{
    if (!empty($availability['is_available'])) {
        return 'is-success';
    }

    if (($availability['status_label'] ?? '') === 'Reserved Now') {
        return 'is-warning';
    }

    return 'is-error';
}

$currentReservation = $availability['current_reservation'] ?? null;

$totalEquipment = (int) ($equipmentSummary['total_equipment_count'] ?? 0);
$availableEquipment = (int) ($equipmentSummary['available_equipment_count'] ?? 0);
$maintenanceEquipment = (int) ($equipmentSummary['maintenance_equipment_count'] ?? 0);
$passiveEquipment = (int) ($equipmentSummary['passive_equipment_count'] ?? 0);

require_once __DIR__ . '/../includes/header.php';

?>

<section class="stationdet-page">

    <!-- TOPBAR -->
    <div class="stationdet-topbar reveal-on-scroll">

        <a
            href="lab-detail.php?id=<?= (int) $station['lab_id'] ?>"
            class="stationdet-btn stationdet-btn-outline"
        >
            ← Back to Laboratory
        </a>

        <?php if ($station['status'] === 'active'): ?>
            <a
                href="reserve.php?lab_id=<?= (int) $station['lab_id'] ?>&station_id=<?= (int) $station['station_id'] ?>"
                class="stationdet-btn stationdet-btn-primary"
            >
                Reserve This Station
            </a>
        <?php else: ?>
            <button
                type="button"
                class="stationdet-btn stationdet-btn-disabled"
                disabled
            >
                Reservation Closed
            </button>
        <?php endif; ?>

    </div>

    <!-- HERO -->
    <section class="stationdet-hero reveal-on-scroll" data-stationdet-tilt-card>

        <div class="stationdet-hero-content">

            <span class="stationdet-eyebrow">
                <?= htmlspecialchars($station['station_code'], ENT_QUOTES, 'UTF-8') ?>
            </span>

            <h1>
                <?= htmlspecialchars($station['station_name'], ENT_QUOTES, 'UTF-8') ?>
            </h1>

            <p>
                <?= nl2br(htmlspecialchars($station['notes'] ?? 'No notes available.', ENT_QUOTES, 'UTF-8')) ?>
            </p>

            <div class="stationdet-alert <?= !empty($availability['is_available']) ? 'is-success' : 'is-warning' ?>">
                <?php if (($availability['status_label'] ?? '') === 'Available'): ?>
                    This station is currently available.
                <?php elseif (($availability['status_label'] ?? '') === 'Reserved Now'): ?>
                    This station is currently reserved, but you can create a reservation for a future available time slot.
                <?php else: ?>
                    <?= htmlspecialchars($availability['reason'] ?? 'This station is not currently available.', ENT_QUOTES, 'UTF-8') ?>
                <?php endif; ?>
            </div>

            <div class="stationdet-hero-actions">
                <?php if ($station['status'] === 'active'): ?>
                    <a
                        href="reserve.php?lab_id=<?= (int) $station['lab_id'] ?>&station_id=<?= (int) $station['station_id'] ?>"
                        class="stationdet-btn stationdet-btn-primary"
                    >
                        Start Reservation
                    </a>
                <?php endif; ?>

                <a href="#stationEquipment" class="stationdet-btn stationdet-btn-light">
                    View Equipment
                </a>
            </div>

        </div>

        <div class="stationdet-hero-visual">

            <div class="stationdet-mini-panel">

                <div class="stationdet-mini-header">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>

                <div class="stationdet-mini-body">

                    <div class="stationdet-mini-title">
                        <div>
                            <small>Live Availability</small>
                            <strong><?= htmlspecialchars($availability['status_label'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?></strong>
                        </div>

                        <span class="stationdet-status-badge <?= stationDetailAvailabilityClass($availability) ?>">
                            <?= htmlspecialchars($availability['status_label'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>

                    <div class="stationdet-mini-list">

                        <div class="stationdet-mini-item is-active">
                            <span>01</span>
                            <div>
                                <strong>Laboratory</strong>
                                <small><?= htmlspecialchars($station['lab_code'] . ' - ' . $station['lab_name'], ENT_QUOTES, 'UTF-8') ?></small>
                            </div>
                        </div>

                        <div class="stationdet-mini-item">
                            <span>02</span>
                            <div>
                                <strong>Type</strong>
                                <small><?= htmlspecialchars(formatStationDetailType($station['type_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small>
                            </div>
                        </div>

                        <div class="stationdet-mini-item">
                            <span>03</span>
                            <div>
                                <strong>Capacity</strong>
                                <small><?= (int) $station['capacity'] ?> person<?= (int) $station['capacity'] === 1 ? '' : 's' ?></small>
                            </div>
                        </div>

                    </div>

                </div>

            </div>

            <div class="stationdet-floating-chip stationdet-chip-one">
                <span>✓</span>
                <?= htmlspecialchars(ucfirst($station['status']), ENT_QUOTES, 'UTF-8') ?>
            </div>

            <div class="stationdet-floating-chip stationdet-chip-two">
                <span>⚙</span>
                Equipment
            </div>

            <div class="stationdet-floating-chip stationdet-chip-three">
                <span>↗</span>
                Reserve
            </div>

        </div>

    </section>

    <!-- KPI -->
    <section class="stationdet-kpi-grid">

        <article class="stationdet-kpi-card reveal-on-scroll">
            <span>Total Equipment</span>
            <strong><?= $totalEquipment ?></strong>
            <p>All equipment assigned to this station.</p>
        </article>

        <article class="stationdet-kpi-card is-available reveal-on-scroll">
            <span>Available</span>
            <strong><?= $availableEquipment ?></strong>
            <p>Equipment currently available for use.</p>
        </article>

        <article class="stationdet-kpi-card is-maintenance reveal-on-scroll">
            <span>Maintenance</span>
            <strong><?= $maintenanceEquipment ?></strong>
            <p>Equipment currently under maintenance.</p>
        </article>

        <article class="stationdet-kpi-card is-passive reveal-on-scroll">
            <span>Passive</span>
            <strong><?= $passiveEquipment ?></strong>
            <p>Equipment currently passive or unavailable.</p>
        </article>

    </section>

    <!-- STATION INFORMATION -->
    <section class="stationdet-panel reveal-on-scroll">

        <div class="stationdet-section-header">
            <div>
                <span class="stationdet-section-label">
                    Station Information
                </span>

                <h2>
                    Laboratory and station details.
                </h2>

                <p>
                    Review the laboratory connection, department information and station status.
                </p>
            </div>

            <span class="stationdet-status-badge <?= stationDetailStatusClass($station['status']) ?>">
                <?= htmlspecialchars(ucfirst($station['status']), ENT_QUOTES, 'UTF-8') ?>
            </span>
        </div>

        <div class="stationdet-info-grid">

            <div class="stationdet-info-row">
                <span>Laboratory</span>
                <strong><?= htmlspecialchars($station['lab_name'], ENT_QUOTES, 'UTF-8') ?></strong>
            </div>

            <div class="stationdet-info-row">
                <span>Lab Code</span>
                <strong><?= htmlspecialchars($station['lab_code'], ENT_QUOTES, 'UTF-8') ?></strong>
            </div>

            <div class="stationdet-info-row">
                <span>Lab Type</span>
                <strong><?= htmlspecialchars(formatStationDetailType($station['lab_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
            </div>

            <div class="stationdet-info-row">
                <span>Faculty</span>
                <strong><?= htmlspecialchars($station['faculty_name'], ENT_QUOTES, 'UTF-8') ?></strong>
            </div>

            <div class="stationdet-info-row">
                <span>Department</span>
                <strong><?= htmlspecialchars($station['department_name'], ENT_QUOTES, 'UTF-8') ?></strong>
            </div>

            <div class="stationdet-info-row">
                <span>Location</span>
                <strong><?= htmlspecialchars($station['location'] ?? '-', ENT_QUOTES, 'UTF-8') ?></strong>
            </div>

            <div class="stationdet-info-row">
                <span>Station Type</span>
                <strong><?= htmlspecialchars(formatStationDetailType($station['type_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
            </div>

            <div class="stationdet-info-row">
                <span>Capacity</span>
                <strong><?= (int) $station['capacity'] ?></strong>
            </div>

        </div>

    </section>

    <!-- CURRENT RESERVATION -->
    <?php if ($currentReservation): ?>
        <section class="stationdet-panel reveal-on-scroll">

            <div class="stationdet-section-header">
                <div>
                    <span class="stationdet-section-label">
                        Current Reservation
                    </span>

                    <h2>
                        Station is reserved now.
                    </h2>

                    <p>
                        You can still reserve this station for a future available time slot.
                    </p>
                </div>

                <span class="stationdet-status-badge is-warning">
                    Reserved Now
                </span>
            </div>

            <div class="stationdet-info-grid">

                <div class="stationdet-info-row">
                    <span>Start</span>
                    <strong><?= htmlspecialchars(formatStationDetailDateTime($currentReservation['start_time'] ?? null), ENT_QUOTES, 'UTF-8') ?></strong>
                </div>

                <div class="stationdet-info-row">
                    <span>End</span>
                    <strong><?= htmlspecialchars(formatStationDetailDateTime($currentReservation['end_time'] ?? null), ENT_QUOTES, 'UTF-8') ?></strong>
                </div>

                <div class="stationdet-info-row">
                    <span>Status</span>
                    <strong><?= htmlspecialchars(ucfirst($currentReservation['status'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></strong>
                </div>

            </div>

        </section>
    <?php endif; ?>

 <!-- EQUIPMENT LIST -->
<section class="stationdet-panel stationdet-equipment-carousel-section reveal-on-scroll" id="stationEquipment">

<div class="stationdet-section-header">
    <div>
        <span class="stationdet-section-label">
            Equipment
        </span>

        <h2>
            Equipment assigned to this station.
        </h2>

        <p>
            Move sideways to review equipment status before creating a reservation.
        </p>
    </div>

    <span class="stationdet-status-badge is-info">
        <?= count($equipmentList) ?> Item<?= count($equipmentList) === 1 ? '' : 's' ?>
    </span>
</div>

<?php if (count($equipmentList) > 0): ?>

    <div class="stationdet-equipment-carousel-shell">

        <button
            type="button"
            class="stationdet-carousel-btn stationdet-carousel-left"
            data-equipment-prev
            aria-label="Previous equipment"
        >
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M15 6L9 12L15 18"></path>
            </svg>
        </button>

        <div
            class="stationdet-equipment-grid"
            id="stationdetEquipmentGrid"
            data-total-equipment="<?= count($equipmentList) ?>"
        >

            <?php foreach ($equipmentList as $equipment): ?>
                <?php
                $equipmentStatus = $equipment['status'] ?? 'unknown';
                ?>

                <article class="stationdet-equipment-card">

                    <div class="stationdet-equipment-top">
                        <div>
                            <span class="stationdet-equipment-code">
                                <?= htmlspecialchars($equipment['instance_code'] ?? $equipment['equipment_code'] ?? $equipment['serial_number'] ?? 'EQ', ENT_QUOTES, 'UTF-8') ?>
                            </span>

                            <h3>
                                <?= htmlspecialchars($equipment['equipment_name'] ?? 'Equipment', ENT_QUOTES, 'UTF-8') ?>
                            </h3>
                        </div>

                        <span class="stationdet-status-badge <?= stationDetailStatusClass($equipmentStatus) ?>">
                            <?= htmlspecialchars(ucfirst($equipmentStatus), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>

                    <div class="stationdet-info-grid stationdet-equipment-info-grid">

                        <div class="stationdet-info-row">
                            <span>Category</span>
                            <strong><?= htmlspecialchars($equipment['category'] ?? '-', ENT_QUOTES, 'UTF-8') ?></strong>
                        </div>

                        <div class="stationdet-info-row">
                            <span>Serial</span>
                            <strong><?= htmlspecialchars($equipment['serial_number'] ?? '-', ENT_QUOTES, 'UTF-8') ?></strong>
                        </div>

                        <div class="stationdet-info-row">
                            <span>Model</span>
                            <strong><?= htmlspecialchars($equipment['model'] ?? '-', ENT_QUOTES, 'UTF-8') ?></strong>
                        </div>

                        <div class="stationdet-info-row">
                            <span>Brand</span>
                            <strong><?= htmlspecialchars($equipment['brand'] ?? '-', ENT_QUOTES, 'UTF-8') ?></strong>
                        </div>

                    </div>

                </article>

            <?php endforeach; ?>

        </div>

        <button
            type="button"
            class="stationdet-carousel-btn stationdet-carousel-right"
            data-equipment-next
            aria-label="Next equipment"
        >
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M9 6L15 12L9 18"></path>
            </svg>
        </button>

    </div>

<?php else: ?>

    <div class="stationdet-empty-state">
        <span class="stationdet-status-badge is-warning">
            No Equipment
        </span>

        <h3>
            No equipment found.
        </h3>

        <p>
            This station currently has no equipment assigned.
        </p>
    </div>

<?php endif; ?>

</section>
    <!-- UPCOMING RESERVATIONS -->
    <section class="stationdet-panel reveal-on-scroll">

        <div class="stationdet-section-header">
            <div>
                <span class="stationdet-section-label">
                    Upcoming Schedule
                </span>

                <h2>
                    Upcoming station reservations.
                </h2>

                <p>
                    These records help you understand future station usage.
                </p>
            </div>

            <span class="stationdet-status-badge is-info">
                <?= count($upcomingReservations) ?> Record<?= count($upcomingReservations) === 1 ? '' : 's' ?>
            </span>
        </div>

        <?php if (count($upcomingReservations) > 0): ?>

            <div class="stationdet-table-wrapper">
                <table class="stationdet-table">
                    <thead>
                        <tr>
                            <th>Start</th>
                            <th>End</th>
                            <th>Time</th>
                            <th>Status</th>
                            <th>Purpose</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($upcomingReservations as $reservation): ?>
                            <tr>
                                <td><?= htmlspecialchars(formatStationDetailDateTime($reservation['start_time'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars(formatStationDetailDateTime($reservation['end_time'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <?= htmlspecialchars(formatStationDetailTime($reservation['start_time'] ?? null), ENT_QUOTES, 'UTF-8') ?>
                                    —
                                    <?= htmlspecialchars(formatStationDetailTime($reservation['end_time'] ?? null), ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td>
                                    <span class="stationdet-status-badge <?= stationDetailStatusClass($reservation['status'] ?? '') ?>">
                                        <?= htmlspecialchars(ucfirst($reservation['status'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($reservation['purpose'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php else: ?>

            <div class="stationdet-empty-state">
                <span class="stationdet-status-badge is-success">
                    Open Schedule
                </span>

                <h3>
                    No upcoming reservation found.
                </h3>

                <p>
                    This station currently has no upcoming reservation records.
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
/*
|--------------------------------------------------------------------------
| Equipment Carousel
|--------------------------------------------------------------------------
*/

const equipmentCarousel = document.getElementById('stationdetEquipmentGrid');
const equipmentPrevButton = document.querySelector('[data-equipment-prev]');
const equipmentNextButton = document.querySelector('[data-equipment-next]');

if (equipmentCarousel && equipmentPrevButton && equipmentNextButton) {
    function getEquipmentScrollAmount() {
        const firstCard = equipmentCarousel.querySelector('.stationdet-equipment-card');

        if (!firstCard) {
            return 420;
        }

        const carouselStyle = window.getComputedStyle(equipmentCarousel);
        const gap = parseInt(carouselStyle.columnGap || carouselStyle.gap || '22', 10);

        return firstCard.offsetWidth + gap;
    }

    equipmentPrevButton.addEventListener('click', function () {
        equipmentCarousel.scrollBy({
            left: -getEquipmentScrollAmount(),
            behavior: 'smooth'
        });
    });

    equipmentNextButton.addEventListener('click', function () {
        equipmentCarousel.scrollBy({
            left: getEquipmentScrollAmount(),
            behavior: 'smooth'
        });
    });
}
    const stationdetTiltCard = document.querySelector('[data-stationdet-tilt-card]');

    if (stationdetTiltCard) {
        stationdetTiltCard.addEventListener('pointermove', function (event) {
            const rect = stationdetTiltCard.getBoundingClientRect();

            const x = event.clientX - rect.left;
            const y = event.clientY - rect.top;

            const rotateY = ((x / rect.width) - 0.5) * 5;
            const rotateX = ((y / rect.height) - 0.5) * -5;

            stationdetTiltCard.style.transform =
                'perspective(1200px) rotateX(' + rotateX + 'deg) rotateY(' + rotateY + 'deg)';
        });

        stationdetTiltCard.addEventListener('pointerleave', function () {
            stationdetTiltCard.style.transform =
                'perspective(1200px) rotateX(0deg) rotateY(0deg)';
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>