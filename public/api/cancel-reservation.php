<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth_helper.php';
require_once __DIR__ . '/../../helpers/validation_helper.php';
require_once __DIR__ . '/../../helpers/response_helper.php';
require_once __DIR__ . '/../../helpers/reservation_helper.php';

if (!isLoggedIn()) {
    jsonError('Authentication is required.', 401);
}

$userId = getCurrentUserId();

$reservationId = $_POST['reservation_id'] ?? $_GET['reservation_id'] ?? '';

if (!isPositiveInteger($reservationId)) {
    jsonError('Valid reservation ID is required.', 400);
}

$reservation = getReservationDetail($pdo, (int) $reservationId);

if (!$reservation) {
    jsonError('Reservation not found.', 404);
}

if (!isAdmin() && (int) $reservation['user_id'] !== (int) $userId) {
    jsonError('You are not allowed to cancel this reservation.', 403);
}

if ($reservation['status'] !== 'active') {
    jsonError('Only active reservations can be cancelled.', 400);
}

if (!isReservationStartInFuture($reservation['start_time'])) {
    jsonError('Past reservations cannot be cancelled.', 400);
}

try {
    $pdo->beginTransaction();

    $oldStatus = $reservation['status'];

    cancelReservation($pdo, (int) $reservationId);

    addReservationStatusHistory(
        $pdo,
        (int) $reservationId,
        $oldStatus,
        'cancelled',
        (int) $userId,
        'Reservation cancelled.'
    );

    $pdo->commit();

    $userIdForSummary = isAdmin() ? $reservation['user_id'] : $userId;
    $allUserReservations = getUserReservations($pdo, (int) $userIdForSummary, 'all');
    $activeCount = 0;
    $cancelledCount = 0;
    $completedCount = 0;
    foreach ($allUserReservations as $r) {
        if ($r['status'] === 'active') $activeCount++;
        elseif ($r['status'] === 'cancelled') $cancelledCount++;
        elseif ($r['status'] === 'completed') $completedCount++;
    }

    jsonSuccess('Reservation cancelled successfully.', [
        'reservation_id' => (int) $reservationId,
        'old_status' => $oldStatus,
        'new_status' => 'cancelled',
        'summary' => [
            'total' => count($allUserReservations),
            'active' => $activeCount,
            'cancelled' => $cancelledCount,
            'completed' => $completedCount,
        ],
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if (DEBUG_MODE) {
        jsonError('Reservation cancellation failed: ' . $e->getMessage(), 500);
    }

    jsonError('Reservation cancellation failed.', 500);
}