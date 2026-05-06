<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth_helper.php';
require_once __DIR__ . '/../../helpers/response_helper.php';
require_once __DIR__ . '/../../helpers/reservation_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Only POST requests are allowed.', 405);
}

if (!isLoggedIn()) {
    jsonError('Authentication required.', 401);
}

if (!isAdmin()) {
    jsonError('Admin privileges required.', 403);
}

$reservationId = filter_input(INPUT_POST, 'reservation_id', FILTER_VALIDATE_INT);
$newStatus = trim($_POST['new_status'] ?? '');

$statusFilter = $_POST['status'] ?? '';
$labIdFilter = $_POST['lab_id'] ?? '';
$searchFilter = trim($_POST['q'] ?? '');
$dateFromFilter = $_POST['date_from'] ?? '';
$dateToFilter = $_POST['date_to'] ?? '';

if ($reservationId === false || $reservationId <= 0) {
    jsonError('Valid reservation ID is required.', 422);
}

if ($newStatus === '' || !isValidReservationStatus($newStatus)) {
    jsonError('Invalid reservation status.', 422);
}

$reservation = getReservationDetail($pdo, $reservationId);

if (!$reservation) {
    jsonError('Reservation not found.', 404);
}

if ($reservation['status'] !== 'active') {
    jsonError('Only active reservations can be updated from this page.', 422);
}

if ($newStatus === $reservation['status']) {
    jsonError('The selected status is already assigned to this reservation.', 422);
}

try {
    $pdo->beginTransaction();
    $oldStatus = $reservation['status'];
    updateReservationStatus($pdo, (int) $reservationId, $newStatus);
    addReservationStatusHistory($pdo, (int) $reservationId, $oldStatus, $newStatus, getCurrentUserId(), 'Reservation status updated by admin.');
    $pdo->commit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonError('Reservation status update failed.', 500);
}

$filters = [
    'status' => $statusFilter,
    'lab_id' => $labIdFilter,
    'q' => $searchFilter,
    'date_from' => $dateFromFilter,
    'date_to' => $dateToFilter,
];

if ($filters['status'] !== '' && !in_array($filters['status'], getReservationStatusOptions(), true)) {
    $filters['status'] = '';
}
if ($filters['lab_id'] !== '' && !filter_var($filters['lab_id'], FILTER_VALIDATE_INT)) {
    $filters['lab_id'] = '';
}

$allReservations = getAdminReservations($pdo, $filters);
$totalCount = count($allReservations);
$activeCount = 0;
$cancelledCount = 0;
$completedCount = 0;
foreach ($allReservations as $r) {
    if ($r['status'] === 'active') {
        $activeCount++;
    } elseif ($r['status'] === 'cancelled') {
        $cancelledCount++;
    } elseif ($r['status'] === 'completed') {
        $completedCount++;
    }
}

jsonSuccess('Reservation status updated successfully.', [
    'reservation_id' => (int) $reservationId,
    'old_status' => $oldStatus,
    'new_status' => $newStatus,
    'summary' => [
        'total' => $totalCount,
        'active' => $activeCount,
        'cancelled' => $cancelledCount,
        'completed' => $completedCount,
    ],
]);
