<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth_helper.php';
require_once __DIR__ . '/../../helpers/validation_helper.php';
require_once __DIR__ . '/../../helpers/response_helper.php';
require_once __DIR__ . '/../../helpers/reservation_helper.php';

if (!isLoggedIn()) {
    jsonError('Authentication is required.', 401);
}

$stationId = $_GET['station_id'] ?? $_POST['station_id'] ?? '';
$dateInput = $_GET['date'] ?? $_POST['date'] ?? '';

$excludeReservationIdInput = $_GET['exclude_reservation_id'] ?? $_POST['exclude_reservation_id'] ?? '';
$excludeReservationId = null;

if ($excludeReservationIdInput !== '') {
    if (!isPositiveInteger($excludeReservationIdInput)) {
        jsonError('Valid exclude reservation ID is required.', 400);
    }

    $excludeReservationId = (int) $excludeReservationIdInput;
}

if (!isPositiveInteger($stationId)) {
    jsonError('Valid station ID is required.', 400);
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateInput)) {
    jsonError('Valid date is required. Expected format: YYYY-MM-DD.', 400);
}

$selectedDate = DateTime::createFromFormat('!Y-m-d', $dateInput);

if (!$selectedDate || $selectedDate->format('Y-m-d') !== $dateInput) {
    jsonError('Invalid date value.', 400);
}

$today = new DateTime('today');
$lastSelectableDate = (clone $today)->modify('+14 days');

if ($selectedDate < $today || $selectedDate > $lastSelectableDate) {
    jsonError('Reservation date must be within the next 15 days.', 400);
}

$station = getReservationStationContext($pdo, (int) $stationId);

if (!$station) {
    jsonError('Station not found.', 404);
}

if ((int) $station['lab_is_active'] !== 1) {
    jsonError('This laboratory is not active.', 400);
}

if ($station['station_status'] !== 'active') {
    jsonError('This station is not active for reservation.', 400, [
        'station_status' => $station['station_status']
    ]);
}

$slotStartHours = [8, 10, 12, 14, 16, 18, 20, 22];
$now = new DateTime();
$slots = [];

foreach ($slotStartHours as $hour) {
    $start = clone $selectedDate;
    $start->setTime($hour, 0, 0);

    $end = clone $start;
    $end->modify('+2 hours');

    $startForDatabase = $start->format('Y-m-d H:i:s');
    $endForDatabase = $end->format('Y-m-d H:i:s');

    $isPastSlot = $start <= $now;
    $hasConflict = false;

    if (!$isPastSlot) {
        $hasConflict = !checkAvailability(
            $pdo,
            (int) $stationId,
            $startForDatabase,
            $endForDatabase,
            $excludeReservationId
);
    }

    $available = !$isPastSlot && !$hasConflict;

    if ($isPastSlot) {
        $reason = 'Past slot';
    } elseif ($hasConflict) {
        $reason = 'Booked';
    } else {
        $reason = 'Available';
    }

    $displayEndHour = $hour + 2;
    $labelEnd = $displayEndHour === 24 ? '24:00' : sprintf('%02d:00', $displayEndHour);

    $slots[] = [
        'label' => sprintf('%02d:00 - %s', $hour, $labelEnd),
        'start_time' => $start->format('Y-m-d\TH:i:s'),
        'end_time' => $end->format('Y-m-d\TH:i:s'),
        'available' => $available,
        'reason' => $reason
    ];
}

jsonSuccess('Day slots loaded successfully.', [
    'station' => [
        'station_id' => (int) $station['station_id'],
        'lab_id' => (int) $station['lab_id'],
        'station_code' => $station['station_code'],
        'station_name' => $station['station_name'],
        'station_status' => $station['station_status'],
        'lab_name' => $station['lab_name']
    ],
    'date' => $selectedDate->format('Y-m-d'),
    'slots' => $slots
]);