<?php

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/lab_helper.php';
require_once __DIR__ . '/../helpers/reservation_helper.php';

syncExpiredReservations($pdo);

$labId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$labId) {
    http_response_code(400);
    die('Invalid laboratory ID.');
}

$lab = getLabById($pdo, (int) $labId);

if (!$lab) {
    http_response_code(404);
    die('Laboratory not found.');
}

$stations = getStationsByLab($pdo, (int) $labId);
$equipmentSummary = getLabEquipmentSummary($pdo, (int) $labId);

$pageTitle = 'Laboratory Detail';
$pageCss = 'lab-detail.css';
$bodyClass = 'page-lab-detail';

function formatLabDetailType(?string $type): string
{
    $type = trim((string) $type);

    if ($type === '') {
        return '-';
    }

    return ucwords(str_replace('_', ' ', $type));
}

function labDetailAvailabilityClass(string $statusLabel): string
{
    if ($statusLabel === 'Available') {
        return 'is-success';
    }

    if ($statusLabel === 'Reserved Now') {
        return 'is-warning';
    }

    if ($statusLabel === 'Maintenance' || $statusLabel === 'Passive') {
        return 'is-error';
    }

    return 'is-info';
}

function labDetailStationStatusClass(string $status): string
{
    if ($status === 'active') {
        return 'is-success';
    }

    if ($status === 'maintenance') {
        return 'is-warning';
    }

    if ($status === 'passive') {
        return 'is-error';
    }

    return 'is-info';
}

$stationCards = [];
$activeStationCount = 0;
$availableNowCount = 0;
$totalEquipmentCount = 0;
$availableEquipmentCount = 0;
$maintenanceEquipmentCount = 0;
$passiveEquipmentCount = 0;

foreach ($stations as $station) {
    $availability = getStationComputedAvailability(
        $pdo,
        (int) $station['station_id']
    );

    if ($station['status'] === 'active') {
        $activeStationCount++;
    }

    if (($availability['status_label'] ?? '') === 'Available') {
        $availableNowCount++;
    }

    $totalEquipmentCount += (int) ($station['total_equipment_count'] ?? 0);
    $availableEquipmentCount += (int) ($station['available_equipment_count'] ?? 0);
    $maintenanceEquipmentCount += (int) ($station['maintenance_equipment_count'] ?? 0);
    $passiveEquipmentCount += (int) ($station['passive_equipment_count'] ?? 0);

    $stationCards[] = [
        'station' => $station,
        'availability' => $availability
    ];
}

require_once __DIR__ . '/../includes/header.php';

?>

<section class="labdet-page">

    <!-- TOPBAR -->
    <div class="labdet-topbar reveal-on-scroll">

        <a href="labs.php" class="labdet-btn labdet-btn-outline">
            ← Back to Laboratories
        </a>

        <a
            href="reserve.php?lab_id=<?= (int) $lab['lab_id'] ?>"
            class="labdet-btn labdet-btn-primary"
        >
            Reserve from This Lab
        </a>

    </div>

    <!-- HERO -->
    <section class="labdet-hero reveal-on-scroll" data-labdet-tilt-card>

        <div class="labdet-hero-content">

            <span class="labdet-eyebrow">
                <?= htmlspecialchars($lab['lab_code'], ENT_QUOTES, 'UTF-8') ?>
            </span>

            <h1>
                <?= htmlspecialchars($lab['lab_name'], ENT_QUOTES, 'UTF-8') ?>
            </h1>

            <p>
                <?= nl2br(htmlspecialchars($lab['description'] ?? 'No description available.', ENT_QUOTES, 'UTF-8')) ?>
            </p>

            <div class="labdet-hero-actions">
                <a
                    href="reserve.php?lab_id=<?= (int) $lab['lab_id'] ?>"
                    class="labdet-btn labdet-btn-primary"
                >
                    Start Reservation
                </a>

                <a href="#labStations" class="labdet-btn labdet-btn-light">
                    View Stations
                </a>
            </div>

        </div>

        <div class="labdet-hero-visual">

            <div class="labdet-mini-panel">

                <div class="labdet-mini-header">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>

                <div class="labdet-mini-body">

                    <div class="labdet-mini-title">
                        <div>
                            <small>Laboratory Overview</small>
                            <strong><?= htmlspecialchars(formatLabDetailType($lab['lab_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                        </div>

                        <span>Live</span>
                    </div>

                    <div class="labdet-mini-list">

                        <div class="labdet-mini-item is-active">
                            <span>01</span>
                            <div>
                                <strong>Faculty</strong>
                                <small><?= htmlspecialchars($lab['faculty_name'], ENT_QUOTES, 'UTF-8') ?></small>
                            </div>
                        </div>

                        <div class="labdet-mini-item">
                            <span>02</span>
                            <div>
                                <strong>Department</strong>
                                <small><?= htmlspecialchars($lab['department_name'], ENT_QUOTES, 'UTF-8') ?></small>
                            </div>
                        </div>

                        <div class="labdet-mini-item">
                            <span>03</span>
                            <div>
                                <strong>Location</strong>
                                <small><?= htmlspecialchars($lab['location'] ?? '-', ENT_QUOTES, 'UTF-8') ?></small>
                            </div>
                        </div>

                    </div>

                </div>

            </div>

            <div class="labdet-floating-chip labdet-chip-one">
                <span>✓</span>
                <?= (int) $activeStationCount ?> Active
            </div>

            <div class="labdet-floating-chip labdet-chip-two">
                <span>↗</span>
                Reserve
            </div>

            <div class="labdet-floating-chip labdet-chip-three">
                <span>⚙</span>
                Equipment
            </div>

        </div>

    </section>

    <!-- KPI -->
    <section class="labdet-kpi-grid">

        <article class="labdet-kpi-card reveal-on-scroll">
            <span>Total Stations</span>
            <strong><?= count($stations) ?></strong>
            <p>All stations connected to this laboratory.</p>
        </article>

        <article class="labdet-kpi-card is-active reveal-on-scroll">
            <span>Active Stations</span>
            <strong><?= (int) $activeStationCount ?></strong>
            <p>Stations open for reservation workflow.</p>
        </article>

        <article class="labdet-kpi-card is-available reveal-on-scroll">
            <span>Available Now</span>
            <strong><?= (int) $availableNowCount ?></strong>
            <p>Stations not currently reserved.</p>
        </article>

        <article class="labdet-kpi-card is-equipment reveal-on-scroll">
            <span>Total Equipment</span>
            <strong><?= (int) $totalEquipmentCount ?></strong>
            <p>Equipment assigned to stations in this lab.</p>
        </article>

    </section>

    <!-- EQUIPMENT SUMMARY -->
    <section class="labdet-panel reveal-on-scroll">

        <div class="labdet-section-header">
            <div>
                <span class="labdet-section-label">
                    Equipment Summary
                </span>

                <h2>
                    Equipment connected to this laboratory.
                </h2>

                <p>
                    Review equipment types and status counts before selecting a station.
                </p>
            </div>

            <span class="labdet-status-badge is-info">
                <?= count($equipmentSummary) ?> Type<?= count($equipmentSummary) === 1 ? '' : 's' ?>
            </span>
        </div>

        <div class="labdet-equipment-overview">

            <div>
                <span>Total</span>
                <strong><?= (int) $totalEquipmentCount ?></strong>
            </div>

            <div>
                <span>Available</span>
                <strong><?= (int) $availableEquipmentCount ?></strong>
            </div>

            <div>
                <span>Maintenance</span>
                <strong><?= (int) $maintenanceEquipmentCount ?></strong>
            </div>

            <div>
                <span>Passive</span>
                <strong><?= (int) $passiveEquipmentCount ?></strong>
            </div>

        </div>

        <?php if (count($equipmentSummary) > 0): ?>
            <div class="labdet-table-wrapper">
                <table class="labdet-table">
                    <thead>
                        <tr>
                            <th>Equipment</th>
                            <th>Category</th>
                            <th>Total</th>
                            <th>Available</th>
                            <th>Maintenance</th>
                            <th>Passive</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($equipmentSummary as $equipment): ?>
                            <tr>
                                <td><?= htmlspecialchars($equipment['equipment_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($equipment['category'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= (int) $equipment['total_count'] ?></td>

                                <td>
                                    <span class="labdet-status-badge is-success">
                                        <?= (int) $equipment['available_count'] ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="labdet-status-badge is-warning">
                                        <?= (int) $equipment['maintenance_count'] ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="labdet-status-badge is-error">
                                        <?= (int) $equipment['passive_count'] ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="labdet-empty-inline">
                No equipment found for this laboratory.
            </div>
        <?php endif; ?>

    </section>

   <!-- STATIONS -->
<section class="labdet-panel labdet-stations-carousel-section reveal-on-scroll" id="labStations">

<div class="labdet-section-header">
    <div>
        <span class="labdet-section-label">
            Stations
        </span>

        <h2>
            Select a station.
        </h2>

        <p>
            Move sideways to inspect station details, equipment status and continue to reservation.
        </p>
    </div>

    <span class="labdet-status-badge is-info">
        <?= count($stations) ?> Station<?= count($stations) === 1 ? '' : 's' ?>
    </span>
</div>

<?php if (count($stationCards) > 0): ?>

    <div class="labdet-station-carousel-shell">

        <button
            type="button"
            class="labdet-carousel-btn labdet-carousel-left"
            data-labdet-prev
            aria-label="Previous stations"
        >
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M15 6L9 12L15 18"></path>
            </svg>
        </button>

        <div
            class="labdet-station-grid"
            id="labdetStationGrid"
            data-total-stations="<?= count($stationCards) ?>"
        >

            <?php foreach ($stationCards as $item): ?>
                <?php
                $station = $item['station'];
                $availability = $item['availability'];
                $availabilityLabel = $availability['status_label'] ?? 'Unknown';
                $typeLabel = formatLabDetailType($station['type_name'] ?? '');
                $typeShort = mb_substr($typeLabel, 0, 3);
                ?>

                <article class="labdet-station-card reveal-on-scroll">

                    <div class="labdet-station-top">

                        <div class="labdet-type-icon">
                            <span>
                                <?= htmlspecialchars($typeShort, ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </div>

                        <span class="labdet-status-badge <?= labDetailAvailabilityClass($availabilityLabel) ?>">
                            <?= htmlspecialchars($availabilityLabel, ENT_QUOTES, 'UTF-8') ?>
                        </span>

                    </div>

                    <div class="labdet-code-row">
                        <span>
                            <?= htmlspecialchars($station['station_code'], ENT_QUOTES, 'UTF-8') ?>
                        </span>

                        <strong>
                            <?= htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8') ?>
                        </strong>
                    </div>

                    <h3>
                        <?= htmlspecialchars($station['station_name'], ENT_QUOTES, 'UTF-8') ?>
                    </h3>

                    <div class="labdet-station-meta">

                        <div class="labdet-meta-row">
                            <span>Type</span>

                            <strong>
                                <?= htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8') ?>
                            </strong>
                        </div>

                        <div class="labdet-meta-row">
                            <span>Capacity</span>

                            <strong>
                                <?= (int) $station['capacity'] ?>
                            </strong>
                        </div>

                        <div class="labdet-meta-row">
                            <span>Status</span>

                            <strong>
                                <span class="labdet-status-badge <?= labDetailStationStatusClass($station['status']) ?>">
                                    <?= htmlspecialchars(ucfirst($station['status']), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </strong>
                        </div>

                    </div>

                    <div class="labdet-station-equipment-grid">

                        <div>
                            <span>Total Equipment</span>
                            <strong><?= (int) ($station['total_equipment_count'] ?? 0) ?></strong>
                        </div>

                        <div>
                            <span>Available</span>
                            <strong><?= (int) ($station['available_equipment_count'] ?? 0) ?></strong>
                        </div>

                        <div>
                            <span>Maintenance</span>
                            <strong><?= (int) ($station['maintenance_equipment_count'] ?? 0) ?></strong>
                        </div>

                        <div>
                            <span>Passive</span>
                            <strong><?= (int) ($station['passive_equipment_count'] ?? 0) ?></strong>
                        </div>

                    </div>

                    <div class="labdet-station-actions">

                        <a
                            href="station-detail.php?id=<?= (int) $station['station_id'] ?>"
                            class="labdet-btn labdet-btn-outline"
                        >
                            View Station
                        </a>

                        <?php if ($station['status'] === 'active'): ?>
                            <a
                                href="reserve.php?lab_id=<?= (int) $lab['lab_id'] ?>&station_id=<?= (int) $station['station_id'] ?>"
                                class="labdet-btn labdet-btn-primary"
                            >
                                Reserve
                            </a>
                        <?php else: ?>
                            <button
                                type="button"
                                class="labdet-btn labdet-btn-disabled"
                                disabled
                            >
                                Reservation Closed
                            </button>
                        <?php endif; ?>

                    </div>

                </article>

            <?php endforeach; ?>

        </div>

        <button
            type="button"
            class="labdet-carousel-btn labdet-carousel-right"
            data-labdet-next
            aria-label="Next stations"
        >
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M9 6L15 12L9 18"></path>
            </svg>
        </button>

    </div>

<?php else: ?>

    <div class="labdet-empty-state">
        <span class="labdet-status-badge is-warning">
            No Station
        </span>

        <h3>
            No station found for this laboratory.
        </h3>

        <p>
            This laboratory currently has no station listings.
        </p>
    </div>

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

    /*
    |--------------------------------------------------------------------------
    | Station Carousel
    |--------------------------------------------------------------------------
    */

    const stationCarousel = document.getElementById('labdetStationGrid');
    const stationPrevButton = document.querySelector('[data-labdet-prev]');
    const stationNextButton = document.querySelector('[data-labdet-next]');

    if (stationCarousel && stationPrevButton && stationNextButton) {
        function getStationScrollAmount() {
            const firstCard = stationCarousel.querySelector('.labdet-station-card');

            if (!firstCard) {
                return 420;
            }

            const carouselStyle = window.getComputedStyle(stationCarousel);
            const gap = parseInt(carouselStyle.columnGap || carouselStyle.gap || '22', 10);

            return firstCard.offsetWidth + gap;
        }

        stationPrevButton.addEventListener('click', function () {
            stationCarousel.scrollBy({
                left: -getStationScrollAmount(),
                behavior: 'smooth'
            });
        });

        stationNextButton.addEventListener('click', function () {
            stationCarousel.scrollBy({
                left: getStationScrollAmount(),
                behavior: 'smooth'
            });
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Hero Tilt Effect
    |--------------------------------------------------------------------------
    */

    const labdetTiltCard = document.querySelector('[data-labdet-tilt-card]');

    if (labdetTiltCard) {
        labdetTiltCard.addEventListener('pointermove', function (event) {
            const rect = labdetTiltCard.getBoundingClientRect();

            const x = event.clientX - rect.left;
            const y = event.clientY - rect.top;

            const rotateY = ((x / rect.width) - 0.5) * 5;
            const rotateX = ((y / rect.height) - 0.5) * -5;

            labdetTiltCard.style.transform =
                'perspective(1200px) rotateX(' + rotateX + 'deg) rotateY(' + rotateY + 'deg)';
        });

        labdetTiltCard.addEventListener('pointerleave', function () {
            labdetTiltCard.style.transform =
                'perspective(1200px) rotateX(0deg) rotateY(0deg)';
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>