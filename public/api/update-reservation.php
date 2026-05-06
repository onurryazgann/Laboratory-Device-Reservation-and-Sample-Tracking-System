<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth_helper.php';
require_once __DIR__ . '/../../helpers/response_helper.php';
require_once __DIR__ . '/../../helpers/reservation_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Only POST requests are allowed.', 405);
}

if (!isLoggedIn()) {
    jsonError('You must be logged in to update a reservation.', 401);
}

$userId = getCurrentUserId();

if ($userId === null) {
    jsonError('User session could not be verified.', 401);
}

/**
 * LabAjax şu an form-url-encoded gönderiyor.
 * Yine de ileride JSON gönderilirse diye JSON desteği de bırakıyoruz.
 */
$input = $_POST;

if (empty($input)) {
    $rawInput = file_get_contents('php://input');
    $jsonInput = json_decode($rawInput, true);

    if (is_array($jsonInput)) {
        $input = $jsonInput;
    }
}

$reservationId = filter_var($input['reservation_id'] ?? null, FILTER_VALIDATE_INT);
$startTimeInput = trim((string) ($input['start_time'] ?? ''));
$endTimeInput = trim((string) ($input['end_time'] ?? ''));
$purposeInput = trim((string) ($input['purpose'] ?? ''));

if (!$reservationId || $reservationId <= 0) {
    jsonError('Valid reservation ID is required.', 422);
}

$reservation = getReservationDetail($pdo, (int) $reservationId);

if (!$reservation) {
    jsonError('Reservation was not found.', 404);
}

/**
 * Öğrenci sadece kendi rezervasyonunu güncelleyebilir.
 * Admin isterse herhangi bir rezervasyonu güncelleyebilir.
 */
if (!isAdmin() && (int) $reservation['user_id'] !== (int) $userId) {
    jsonError('You are not allowed to update this reservation.', 403);
}

if ($reservation['status'] !== 'active') {
    jsonError('Only active reservations can be updated.', 422);
}

try {
    $currentStart = new DateTime((string) $reservation['start_time']);
    $now = new DateTime();

    if ($currentStart <= $now) {
        jsonError('Past or ongoing reservations cannot be updated.', 422);
    }
} catch (Exception $e) {
    jsonError('Reservation date could not be verified.', 422);
}

$startTime = normalizeDateTimeForDatabase($startTimeInput);
$endTime = normalizeDateTimeForDatabase($endTimeInput);

$slotValidation = validateFixedReservationSlot($startTime, $endTime);

if ($slotValidation['valid'] !== true) {
    jsonError($slotValidation['message'], 422);
}

$stationContext = getReservationStationContext($pdo, (int) $reservation['station_id']);

if (!$stationContext) {
    jsonError('Reservation station was not found.', 404);
}

if ((int) $stationContext['lab_id'] !== (int) $reservation['lab_id']) {
    jsonError('Reservation station and laboratory connection is invalid.', 422);
}

if ((int) $stationContext['lab_is_active'] !== 1) {
    jsonError('This laboratory is not active.', 422);
}

if ($stationContext['station_status'] !== 'active') {
    jsonError('This station is not active for reservation.', 422);
}

$isAvailable = checkAvailability(
    $pdo,
    (int) $reservation['station_id'],
    $startTime,
    $endTime,
    (int) $reservationId
);

if (!$isAvailable) {
    $conflicts = getConflictingReservations(
        $pdo,
        (int) $reservation['station_id'],
        $startTime,
        $endTime,
        (int) $reservationId
    );

    jsonError(
        'This station is not available for the selected time slot.',
        409,
        [
            'available' => false,
            'conflicts' => $conflicts,
        ]
    );
}

try {
    $stmt = $pdo->prepare("
        UPDATE reservations
        SET
            start_time = :start_time,
            end_time = :end_time,
            purpose = :purpose
        WHERE reservation_id = :reservation_id
          AND status = 'active'
    ");

    $stmt->execute([
        ':start_time' => $startTime,
        ':end_time' => $endTime,
        ':purpose' => $purposeInput !== '' ? mb_substr($purposeInput, 0, 255) : null,
        ':reservation_id' => (int) $reservationId,
    ]);

    $updatedReservation = getReservationDetail($pdo, (int) $reservationId);

    jsonSuccess('Reservation updated successfully.', [
        'reservation_id' => (int) $reservationId,
        'available' => true,
        'reservation' => [
            'reservation_id' => (int) $updatedReservation['reservation_id'],
            'lab_id' => (int) $updatedReservation['lab_id'],
            'station_id' => (int) $updatedReservation['station_id'],
            'lab_name' => $updatedReservation['lab_name'],
            'station_code' => $updatedReservation['station_code'],
            'station_name' => $updatedReservation['station_name'],
            'start_time' => $updatedReservation['start_time'],
            'end_time' => $updatedReservation['end_time'],
            'purpose' => $updatedReservation['purpose'],
            'status' => $updatedReservation['status'],
        ],
    ]);
} catch (Throwable $e) {
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        jsonError('Reservation update failed: ' . $e->getMessage(), 500);
    }

    jsonError('Reservation update failed.', 500);
}