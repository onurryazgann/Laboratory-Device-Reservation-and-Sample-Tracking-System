<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth_helper.php';
require_once __DIR__ . '/../../helpers/validation_helper.php';
require_once __DIR__ . '/../../helpers/response_helper.php';
require_once __DIR__ . '/../../helpers/reservation_helper.php';

if (!isLoggedIn()) {
    jsonError('Authentication is required.', 401);
}

$stationId = $_POST['station_id'] ?? $_GET['station_id'] ?? '';
$startTimeInput = $_POST['start_time'] ?? $_GET['start_time'] ?? '';
$endTimeInput = $_POST['end_time'] ?? $_GET['end_time'] ?? '';

if (!isPositiveInteger($stationId)) {
    jsonError('Valid station ID is required.', 400);
}

$startTime = normalizeDateTimeForDatabase($startTimeInput);
$endTime = normalizeDateTimeForDatabase($endTimeInput);

$slotValidation = validateFixedReservationSlot($startTime, $endTime);

if ($slotValidation['valid'] !== true) {
    jsonError($slotValidation['message'], 400);
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

$isAvailable = checkAvailability($pdo, (int) $stationId, $startTime, $endTime);

$conflicts = [];

if (!$isAvailable) {
    $conflicts = getConflictingReservations($pdo, (int) $stationId, $startTime, $endTime);
}

jsonSuccess('Availability check completed.', [
    'available' => $isAvailable,
    'station' => [
        'station_id' => (int) $station['station_id'],
        'lab_id' => (int) $station['lab_id'],
        'station_code' => $station['station_code'],
        'station_name' => $station['station_name'],
        'station_status' => $station['station_status'],
        'lab_name' => $station['lab_name']
    ],
    'requested_time' => [
        'start_time' => $startTime,
        'end_time' => $endTime
    ],
    'conflicts' => $conflicts
]);