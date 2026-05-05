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
$pageCss = 'labs.css';

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

function stationDetailStatusBadgeClass(string $status): string
{
    if ($status === 'active' || $status === 'available') {
        return 'badge-success';
    }

    if ($status === 'maintenance' || $status === 'Reserved Now') {
        return 'badge-warning';
    }

    if ($status === 'passive' || $status === 'closed') {
        return 'badge-error';
    }

    return 'badge-info';
}

function stationDetailAvailabilityBadgeClass(array $availability): string
{
    if (!empty($availability['is_available'])) {
        return 'badge-success';
    }

    if (($availability['status_label'] ?? '') === 'Reserved Now') {
        return 'badge-warning';
    }

    return 'badge-error';
}

function stationDetailAvailabilityMessageClass(array $availability): string
{
    if (!empty($availability['is_available'])) {
        return 'alert-success';
    }

    return 'alert-error';
}

$currentReservation = $availability['current_reservation'] ?? null;

require_once __DIR__ . '/../includes/header.php';

?>

<section class="page-section station-detail-page">
    <div class="container">

        <!-- TOP ACTIONS -->
        <div class="lab-detail-topbar">
            <a href="lab-detail.php?id=<?= (int) $station['lab_id'] ?>" class="btn btn-outline">
                ← Back to Laboratory
            </a>

            <?php if ($station['status'] === 'active'): ?>
                <a
                    href="reserve.php?lab_id=<?= (int) $station['lab_id'] ?>&station_id=<?= (int) $station['station_id'] ?>"
                    class="btn btn-primary"
                >
                    Reserve This Station
                </a>
            <?php else: ?>
                <button type="button" class="btn btn-secondary" disabled>
                    Reservation Closed
                </button>
            <?php endif; ?>
        </div>

        <!-- HERO -->
        <div class="card station-detail-hero-card" style="margin-bottom:32px;">
            <div class="station-detail-hero-grid">

                <div>
                    <span class="badge badge-info">
                        <?= htmlspecialchars($station['station_code']) ?>
                    </span>

                    <h1 class="section-title" style="margin-bottom:12px; margin-top:16px;">
                        <?= htmlspecialchars($station['station_name']) ?>
                    </h1>

                    <p class="section-subtitle" style="margin-bottom:20px;">
                        <?= nl2br(htmlspecialchars($station['notes'] ?? 'No notes available.')) ?>
                    </p>

                    <div class="alert <?= stationDetailAvailabilityMessageClass($availability) ?>" style="margin-bottom:18px;">
                        <?php if (($availability['status_label'] ?? '') === 'Available'): ?>
                            This station is currently available.
                        <?php elseif (($availability['status_label'] ?? '') === 'Reserved Now'): ?>
                            This station is currently reserved, but you may still create a reservation for a future available time interval.
                        <?php else: ?>
                            <?= htmlspecialchars($availability['reason'] ?? 'This station is not currently available.') ?>
                        <?php endif; ?>
                    </div>

                    <?php if ($station['status'] === 'active'): ?>
                        <a
                            href="reserve.php?lab_id=<?= (int) $station['lab_id'] ?>&station_id=<?= (int) $station['station_id'] ?>"
                            class="btn btn-primary"
                        >
                            Start Reservation
                        </a>
                    <?php else: ?>
                        <div class="alert alert-error">
                            This station is currently not available for reservation.
                        </div>
                    <?php endif; ?>
                </div>

                <div class="station-detail-info-card">

                    <div class="station-detail-info-row">
                        <span>Laboratory</span>
                        <strong><?= htmlspecialchars($station['lab_name']) ?></strong>
                    </div>

                    <div class="station-detail-info-row">
                        <span>Lab Code</span>
                        <strong><?= htmlspecialchars($station['lab_code']) ?></strong>
                    </div>

                    <div class="station-detail-info-row">
                        <span>Lab Type</span>
                        <strong><?= htmlspecialchars(formatStationDetailType($station['lab_type'] ?? '')) ?></strong>
                    </div>

                    <div class="station-detail-info-row">
                        <span>Faculty</span>
                        <strong><?= htmlspecialchars($station['faculty_name']) ?></strong>
                    </div>

                    <div class="station-detail-info-row">
                        <span>Department</span>
                        <strong><?= htmlspecialchars($station['department_name']) ?></strong>
                    </div>

                    <div class="station-detail-info-row">
                        <span>Location</span>
                        <strong><?= htmlspecialchars($station['location'] ?? '-') ?></strong>
                    </div>

                    <div class="station-detail-info-row">
                        <span>Station Type</span>
                        <strong><?= htmlspecialchars($station['type_name']) ?></strong>
                    </div>

                    <div class="station-detail-info-row">
                        <span>Capacity</span>
                        <strong><?= (int) $station['capacity'] ?></strong>
                    </div>

                    <div class="station-detail-info-row">
                        <span>System Status</span>
                        <strong>
                            <span class="badge <?= stationDetailStatusBadgeClass($station['status']) ?>">
                                <?= htmlspecialchars(ucfirst($station['status'])) ?>
                            </span>
                        </strong>
                    </div>

                    <div class="station-detail-info-row">
                        <span>Live Availability</span>
                        <strong>
                            <span class="badge <?= stationDetailAvailabilityBadgeClass($availability) ?>">
                                <?= htmlspecialchars($availability['status_label'] ?? 'Unknown') ?>
                            </span>
                        </strong>
                    </div>

                </div>

            </div>
        </div>

        <!-- KPI -->
        <div class="station-detail-kpi-grid" style="margin-bottom:32px;">

            <div class="card card-hover station-detail-kpi-card">
                <span>Total Equipment</span>
                <strong><?= (int) $equipmentSummary['total_equipment_count'] ?></strong>
                <p>All equipment assigned to this station.</p>
            </div>

            <div class="card card-hover station-detail-kpi-card is-available">
                <span>Available</span>
                <strong><?= (int) $equipmentSummary['available_equipment_count'] ?></strong>
                <p>Equipment currently available for use.</p>
            </div>

            <div class="card card-hover station-detail-kpi-card is-maintenance">
                <span>Maintenance</span>
                <strong><?= (int) $equipmentSummary['maintenance_equipment_count'] ?></strong>
                <p>Equipment under maintenance.</p>
            </div>

            <div class="card card-hover station-detail-kpi-card is-passive">
                <span>Passive</span>
                <strong><?= (int) $equipmentSummary['passive_equipment_count'] ?></strong>
                <p>Passive equipment records.</p>
            </div>

        </div>

        <!-- CURRENT RESERVATION -->
        <?php if (!empty($currentReservation)): ?>
            <div class="card station-detail-section-card" style="margin-bottom:32px;">
                <div class="lab-detail-section-header">
                    <div>
                        <h2 style="margin-top:0; margin-bottom:8px;">
                            Current Active Reservation
                        </h2>

                        <p class="section-subtitle" style="margin-bottom:0;">
                            This station is currently reserved in the following time interval.
                        </p>
                    </div>

                    <span class="badge badge-warning">
                        Reserved Now
                    </span>
                </div>

                <div class="station-detail-current-grid">

                    <div class="station-detail-info-row">
                        <span>Start Time</span>
                        <strong><?= htmlspecialchars(formatStationDetailDateTime($currentReservation['start_time'])) ?></strong>
                    </div>

                    <div class="station-detail-info-row">
                        <span>End Time</span>
                        <strong><?= htmlspecialchars(formatStationDetailDateTime($currentReservation['end_time'])) ?></strong>
                    </div>

                    <div class="station-detail-info-row">
                        <span>Purpose</span>
                        <strong><?= htmlspecialchars($currentReservation['purpose'] ?? '-') ?></strong>
                    </div>

                </div>
            </div>
        <?php endif; ?>

        <!-- EQUIPMENT LIST -->
        <div class="card station-detail-section-card" style="margin-bottom:32px;">
            <div class="lab-detail-section-header">
                <div>
                    <h2 style="margin-top:0; margin-bottom:8px;">
                        Equipment in This Station
                    </h2>

                    <p class="section-subtitle" style="margin-bottom:0;">
                        Devices and physical equipment assigned to this workstation.
                    </p>
                </div>

                <span class="badge badge-info">
                    <?= count($equipmentList) ?> Item<?= count($equipmentList) === 1 ? '' : 's' ?>
                </span>
            </div>

            <?php if (count($equipmentList) > 0): ?>
                <div class="station-detail-equipment-grid">

                    <?php foreach ($equipmentList as $equipment): ?>
                        <?php
                        $equipmentStatus = $equipment['status'] ?? 'unknown';
                        ?>

                        <article class="station-detail-equipment-card">
                            <div>
                                <span class="lab-code">
                                    <?= htmlspecialchars($equipment['asset_code']) ?>
                                </span>

                                <h3>
                                    <?= htmlspecialchars($equipment['equipment_name']) ?>
                                </h3>

                                <p>
                                    <?= htmlspecialchars($equipment['category']) ?>
                                </p>
                            </div>

                            <div class="station-detail-equipment-meta">
                                <div>
                                    <span>Brand</span>
                                    <strong><?= htmlspecialchars($equipment['brand'] ?? '-') ?></strong>
                                </div>

                                <div>
                                    <span>Model</span>
                                    <strong><?= htmlspecialchars($equipment['model'] ?? '-') ?></strong>
                                </div>

                                <div>
                                    <span>Status</span>
                                    <strong>
                                        <span class="badge <?= stationDetailStatusBadgeClass($equipmentStatus) ?>">
                                            <?= htmlspecialchars(ucfirst($equipmentStatus)) ?>
                                        </span>
                                    </strong>
                                </div>
                            </div>
                        </article>

                    <?php endforeach; ?>

                </div>
            <?php else: ?>
                <div class="alert alert-success" style="margin-bottom:0;">
                    No equipment found for this station.
                </div>
            <?php endif; ?>
        </div>

        <!-- UPCOMING -->
        <div class="card station-detail-section-card">
            <div class="lab-detail-section-header">
                <div>
                    <h2 style="margin-top:0; margin-bottom:8px;">
                        Upcoming Active Reservations
                    </h2>

                    <p class="section-subtitle" style="margin-bottom:0;">
                        Future active reservations for this station.
                    </p>
                </div>

                <span class="badge badge-info">
                    <?= count($upcomingReservations) ?> Record<?= count($upcomingReservations) === 1 ? '' : 's' ?>
                </span>
            </div>

            <?php if (count($upcomingReservations) > 0): ?>
                <div class="table-wrapper lab-detail-table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Start Time</th>
                                <th>End Time</th>
                                <th>Status</th>
                                <th>Purpose</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($upcomingReservations as $reservation): ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars(formatStationDetailDateTime($reservation['start_time'])) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(formatStationDetailDateTime($reservation['end_time'])) ?>
                                    </td>

                                    <td>
                                        <span class="badge badge-success">
                                            <?= htmlspecialchars(ucfirst($reservation['status'])) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($reservation['purpose'] ?? '-') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-success" style="margin-bottom:0;">
                    No upcoming active reservation found for this station.
                </div>
            <?php endif; ?>
        </div>

    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>