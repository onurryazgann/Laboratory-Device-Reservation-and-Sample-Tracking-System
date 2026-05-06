<?php

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/lab_helper.php';
require_once __DIR__ . '/../helpers/reservation_helper.php';
require_once __DIR__ . '/../includes/csrf.php';

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
    die('You are not allowed to edit this reservation.');
}

function isEditableReservation(array $reservation): bool
{
    if ($reservation['status'] !== 'active') {
        return false;
    }

    return strtotime($reservation['start_time']) > time();
}

function datetimeLocalEditValue(?string $value): string
{
    $value = trim($value ?? '');

    if ($value === '') {
        return '';
    }

    try {
        $value = str_replace('T', ' ', $value);
        return (new DateTime($value))->format('Y-m-d\TH:i');
    } catch (Exception $e) {
        return '';
    }
}

function formatReservationEditDateTime(?string $value): string
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

function formatReservationEditDate(?string $value): string
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

function formatReservationEditTime(?string $value): string
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

function reservationEditBadgeClass(string $status): string
{
    if ($status === 'active') {
        return 'badge-success';
    }

    if ($status === 'cancelled') {
        return 'badge-error';
    }

    if ($status === 'completed') {
        return 'badge-info';
    }

    return 'badge-warning';
}

$message = '';
$messageStatus = false;
$conflicts = [];

$startTimeValue = datetimeLocalEditValue($reservation['start_time']);
$endTimeValue = datetimeLocalEditValue($reservation['end_time']);
$purposeValue = $reservation['purpose'] ?? '';

$canEdit = isEditableReservation($reservation);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();

    if (!$canEdit) {
        $messageStatus = false;
        $message = 'This reservation cannot be edited.';
    } else {
        $startTimeValue = trim($_POST['start_time'] ?? '');
        $endTimeValue = trim($_POST['end_time'] ?? '');
        $purposeValue = trim($_POST['purpose'] ?? '');

        $startTime = normalizeDateTimeForDatabase($startTimeValue);
        $endTime = normalizeDateTimeForDatabase($endTimeValue);

        $stationContext = getReservationStationContext(
            $pdo,
            (int) $reservation['station_id']
        );

        if (!$stationContext) {
            $messageStatus = false;
            $message = 'Reservation station was not found.';
        } elseif ((int) $stationContext['lab_id'] !== (int) $reservation['lab_id']) {
            $messageStatus = false;
            $message = 'Reservation station and laboratory connection is invalid.';
        } elseif ((int) $stationContext['lab_is_active'] !== 1) {
            $messageStatus = false;
            $message = 'This laboratory is not active.';
        } elseif ($stationContext['station_status'] !== 'active') {
            $messageStatus = false;
            $message = 'This station is not active for reservation.';
        } else {
            $slotValidation = validateFixedReservationSlot($startTime, $endTime);

            if ($slotValidation['valid'] !== true) {
                $messageStatus = false;
                $message = $slotValidation['message'];
            } else {
                $isAvailable = checkAvailability(
                    $pdo,
                    (int) $reservation['station_id'],
                    $startTime,
                    $endTime,
                    (int) $reservation['reservation_id']
                );

                if (!$isAvailable) {
                    $messageStatus = false;
                    $message = 'This station is not available for the selected time slot.';

                    $conflicts = getConflictingReservations(
                        $pdo,
                        (int) $reservation['station_id'],
                        $startTime,
                        $endTime,
                        (int) $reservation['reservation_id']
                    );
                } else {
                    try {
                        $stmt = $pdo->prepare("
                            UPDATE reservations
                            SET
                                start_time = :start_time,
                                end_time = :end_time,
                                purpose = :purpose
                            WHERE reservation_id = :reservation_id
                            AND user_id = :user_id
                            AND status = 'active'
                        ");

                        $stmt->execute([
                            ':start_time' => $startTime,
                            ':end_time' => $endTime,
                            ':purpose' => $purposeValue !== '' ? mb_substr($purposeValue, 0, 255) : null,
                            ':reservation_id' => (int) $reservation['reservation_id'],
                            ':user_id' => (int) $userId,
                        ]);

                        $messageStatus = true;
                        $message = 'Reservation updated successfully.';

                        $reservation = getReservationDetail($pdo, (int) $reservationId);

                        $startTimeValue = datetimeLocalEditValue($reservation['start_time']);
                        $endTimeValue = datetimeLocalEditValue($reservation['end_time']);
                        $purposeValue = $reservation['purpose'] ?? '';
                        $canEdit = isEditableReservation($reservation);
                    } catch (Exception $e) {
                        $messageStatus = false;
                        $message = DEBUG_MODE
                            ? 'Reservation update failed: ' . $e->getMessage()
                            : 'Reservation update failed.';
                    }
                }
            }
        }
    }
}

$pageTitle = 'Edit Reservation';
$pageCss = 'reservation.css';
$pageJs = 'reservation-edit.js';

require_once __DIR__ . '/../includes/header.php';

?>

<section class="page-section reservation-edit-page">
    <div class="container">

        <!-- TOP ACTIONS -->
        <div class="reservation-detail-topbar">
            <a
                href="reservation-detail.php?id=<?= (int) $reservation['reservation_id'] ?>"
                class="btn btn-outline"
            >
                ← Back to Reservation Detail
            </a>

            <a href="my-reservations.php" class="btn btn-secondary">
                My Reservations
            </a>
        </div>

        <!-- HERO -->
        <div class="card reservation-detail-hero-card" style="margin-bottom:32px;">
            <div class="reservation-detail-hero-grid">

                <div>
                    <span class="badge badge-info">
                        Edit Reservation #<?= (int) $reservation['reservation_id'] ?>
                    </span>

                    <h1 class="section-title" style="margin-bottom:10px; margin-top:16px;">
                        <?= htmlspecialchars($reservation['lab_code'] . ' — ' . $reservation['station_code']) ?>
                    </h1>

                    <p class="section-subtitle" style="margin-bottom:0;">
                        Update the time interval or purpose of your future active reservation.
                    </p>
                </div>

                <div class="reservation-detail-status-card">
                    <div class="reservation-detail-status-header">
                        <span>Current Status</span>

                        <span class="badge <?= reservationEditBadgeClass($reservation['status']) ?>">
                            <?= htmlspecialchars(ucfirst($reservation['status'])) ?>
                        </span>
                    </div>

                    <div class="reservation-detail-status-meta">
                        <p>
                            <span>Current Start</span>
                            <strong>
                                <?= htmlspecialchars(formatReservationEditDateTime($reservation['start_time'])) ?>
                            </strong>
                        </p>

                        <p>
                            <span>Current End</span>
                            <strong>
                                <?= htmlspecialchars(formatReservationEditDateTime($reservation['end_time'])) ?>
                            </strong>
                        </p>
                    </div>
                </div>

            </div>
        </div>

        <!-- MESSAGE -->
        <?php if ($message !== ''): ?>
            <div
                class="alert <?= $messageStatus ? 'alert-success' : 'alert-error' ?>"
                style="margin-bottom:24px;"
            >
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- NOT EDITABLE -->
        <?php if (!$canEdit): ?>
            <div class="card reservation-detail-section-card" style="margin-bottom:32px;">
                <h2 style="margin-top:0;">Reservation Cannot Be Edited</h2>

                <div class="alert alert-error">
                    Only future active reservations can be edited.
                </div>

                <div class="reservation-detail-info-grid">
                    <div class="reservation-detail-info-row">
                        <span>Current Status</span>
                        <strong><?= htmlspecialchars(ucfirst($reservation['status'])) ?></strong>
                    </div>

                    <div class="reservation-detail-info-row">
                        <span>Start Time</span>
                        <strong><?= htmlspecialchars(formatReservationEditDateTime($reservation['start_time'])) ?></strong>
                    </div>

                    <div class="reservation-detail-info-row">
                        <span>End Time</span>
                        <strong><?= htmlspecialchars(formatReservationEditDateTime($reservation['end_time'])) ?></strong>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- SUMMARY -->
        <div class="card reservation-detail-section-card" style="margin-bottom:32px;">
            <div class="reservation-detail-section-header">
                <div>
                    <h2 style="margin-top:0; margin-bottom:8px;">Reservation Summary</h2>

                    <p class="section-subtitle" style="margin-bottom:0;">
                        Check the selected laboratory and station before saving changes.
                    </p>
                </div>

                <span class="badge <?= reservationEditBadgeClass($reservation['status']) ?>">
                    <?= htmlspecialchars(ucfirst($reservation['status'])) ?>
                </span>
            </div>

            <div class="reservation-detail-info-grid">

                <div class="reservation-detail-info-row">
                    <span>Laboratory</span>
                    <strong>
                        <?= htmlspecialchars($reservation['lab_code'] . ' — ' . $reservation['lab_name']) ?>
                    </strong>
                </div>

                <div class="reservation-detail-info-row">
                    <span>Station</span>
                    <strong>
                        <?= htmlspecialchars($reservation['station_code'] . ' — ' . $reservation['station_name']) ?>
                    </strong>
                </div>

                <div class="reservation-detail-info-row">
                    <span>Location</span>
                    <strong>
                        <?= htmlspecialchars($reservation['location'] ?? '-') ?>
                    </strong>
                </div>

                <div class="reservation-detail-info-row">
                    <span>Current Date</span>
                    <strong>
                        <?= htmlspecialchars(formatReservationEditDate($reservation['start_time'])) ?>
                    </strong>
                </div>

                <div class="reservation-detail-info-row">
                    <span>Current Time</span>
                    <strong>
                        <?= htmlspecialchars(formatReservationEditTime($reservation['start_time'])) ?>
                        —
                        <?= htmlspecialchars(formatReservationEditTime($reservation['end_time'])) ?>
                    </strong>
                </div>

                <div class="reservation-detail-info-row">
                    <span>Purpose</span>
                    <strong>
                        <?= htmlspecialchars(trim($reservation['purpose'] ?? '') !== '' ? $reservation['purpose'] : '-') ?>
                    </strong>
                </div>

            </div>
        </div>

        <!-- CONFLICTS -->
        <?php if (!empty($conflicts)): ?>
            <div class="card reservation-detail-section-card" style="margin-bottom:32px;">
                <h2 style="margin-top:0;">Conflicting Reservations</h2>

                <p class="section-subtitle">
                    The selected time interval overlaps with another active reservation.
                </p>

                <div class="table-wrapper reservation-detail-history-table">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Start</th>
                                <th>End</th>
                                <th>Status</th>
                                <th>Purpose</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($conflicts as $conflict): ?>
                                <tr>
                                    <td><?= (int) $conflict['reservation_id'] ?></td>
                                    <td><?= htmlspecialchars($conflict['user_full_name']) ?></td>
                                    <td><?= htmlspecialchars(formatReservationEditDateTime($conflict['start_time'])) ?></td>
                                    <td><?= htmlspecialchars(formatReservationEditDateTime($conflict['end_time'])) ?></td>
                                    <td>
                                        <span class="badge <?= reservationEditBadgeClass($conflict['status']) ?>">
                                            <?= htmlspecialchars(ucfirst($conflict['status'])) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($conflict['purpose'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- FORM -->
        <?php if ($canEdit): ?>
            <div class="card reservation-edit-form-card">
                <div class="reservation-detail-section-header">
                    <div>
                        <h2 style="margin-top:0; margin-bottom:8px;">Update Reservation</h2>

                        <p class="section-subtitle" style="margin-bottom:0;">
                            Choose a future time interval. The system will block overlapping active reservations.
                        </p>
                    </div>

                    <span class="badge badge-info">
                        Editable
                    </span>
                </div>

                <form
                    method="POST"
                    action=""
                    id="reservationEditForm"
                    data-reservation-id="<?= (int) $reservation['reservation_id'] ?>"
                    data-station-id="<?= (int) $reservation['station_id'] ?>"
                    data-current-start="<?= htmlspecialchars(datetimeLocalEditValue($startTimeValue)) ?>"
                    data-current-end="<?= htmlspecialchars(datetimeLocalEditValue($endTimeValue)) ?>"
                >
                    <?= csrfInput() ?>
                    <input
                        type="hidden"
                        id="start_time"
                        name="start_time"
                        value="<?= htmlspecialchars(datetimeLocalEditValue($startTimeValue)) ?>"
                    >

                    <input
                        type="hidden"
                        id="end_time"
                        name="end_time"
                        value="<?= htmlspecialchars(datetimeLocalEditValue($endTimeValue)) ?>"
                    >

                    <div class="form-group">
                        <label class="form-label">New Reservation Date</label>

                        <p class="field-hint">
                            You can select today or one of the next 14 days.
                        </p>

                        <div
                            class="reservation-date-grid"
                            id="reservationEditDatePicker"
                            aria-label="Reservation date selection"
                        ></div>
                    </div>

                    <div
                        class="form-group reservation-slot-section"
                        id="reservationEditSlotSection"
                        hidden
                    >
                        <label class="form-label">Available Time Slots</label>

                        <p class="field-hint">
                            Each reservation slot is 2 hours. Unavailable slots are shown as disabled.
                        </p>

                        <div
                            class="reservation-slot-grid"
                            id="reservationEditSlotGrid"
                            aria-label="Reservation time slot selection"
                        ></div>
                    </div>

                    <div
                        class="reservation-selected-slot"
                        id="reservationEditSelectedSlot"
                        hidden
                    >
                        <strong>Selected Slot:</strong>
                        <span id="reservationEditSelectedSlotText">-</span>
                    </div>

                    <div
                        id="reservationEditClientMessage"
                        class="reservation-availability-message"
                        style="display:none; margin-bottom:24px;"
                    ></div>

                    <div class="form-group">
                        <label for="purpose" class="form-label">Purpose</label>

                        <textarea
                            id="purpose"
                            name="purpose"
                            class="form-control"
                            rows="4"
                            maxlength="255"
                            placeholder="Example: Database project study"
                        ><?= htmlspecialchars($purposeValue) ?></textarea>

                        <small class="field-feedback">
                            Optional. Maximum 255 characters.
                        </small>
                    </div>

                    <div class="reservation-edit-actions">
                        <button type="submit" class="btn btn-primary" id="updateReservationButton">
                            Save Changes
                        </button>

                        <a
                            href="reservation-detail.php?id=<?= (int) $reservation['reservation_id'] ?>"
                            class="btn btn-outline"
                        >
                            Cancel Editing
                        </a>
                    </div>

                </form>
            </div>
        <?php endif; ?>

    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>