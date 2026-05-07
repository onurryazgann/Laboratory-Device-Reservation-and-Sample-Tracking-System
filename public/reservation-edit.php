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

function reservationEditStatusClass(string $status): string
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
$pageCss = 'reservation-edit.css';
$pageJs = 'reservation-edit.js';
$bodyClass = 'page-reservation-edit';

require_once __DIR__ . '/../includes/header.php';

?>

<section class="edit-page">

    <!-- TOPBAR -->
    <div class="detail-topbar reveal-on-scroll">

        <a
            href="reservation-detail.php?id=<?= (int) $reservation['reservation_id'] ?>"
            class="detail-btn detail-btn-outline"
        >
            ← Back to Reservation Detail
        </a>

        <a href="my-reservations.php" class="detail-btn detail-btn-light">
            My Reservations
        </a>

    </div>

    <!-- HERO -->
    <section class="detail-hero reveal-on-scroll" data-edit-tilt-card>

        <div class="detail-hero-content">

            <span class="detail-eyebrow">
                Edit Reservation #<?= (int) $reservation['reservation_id'] ?>
            </span>

            <h1>
                <?= htmlspecialchars($reservation['lab_code'] . ' — ' . $reservation['station_code'], ENT_QUOTES, 'UTF-8') ?>
            </h1>

            <p>
                Update the time slot or purpose of your future active laboratory reservation.
            </p>

            <div class="detail-hero-actions">

                <?php if ($canEdit): ?>
                    <a href="#reservationEditFormCard" class="detail-btn detail-btn-primary">
                        Update Reservation
                    </a>
                <?php endif; ?>

                <a
                    href="reservation-detail.php?id=<?= (int) $reservation['reservation_id'] ?>"
                    class="detail-btn detail-btn-light"
                >
                    View Detail
                </a>

            </div>

        </div>

        <div class="detail-status-panel">

            <div class="detail-status-card">

                <div class="detail-status-header">
                    <span>Current Status</span>

                    <strong class="detail-status-badge <?= reservationEditStatusClass($reservation['status']) ?>">
                        <?= htmlspecialchars(ucfirst($reservation['status']), ENT_QUOTES, 'UTF-8') ?>
                    </strong>
                </div>

                <div class="detail-status-meta">

                    <div>
                        <span>Current Start</span>
                        <strong>
                            <?= htmlspecialchars(formatReservationEditDateTime($reservation['start_time']), ENT_QUOTES, 'UTF-8') ?>
                        </strong>
                    </div>

                    <div>
                        <span>Current End</span>
                        <strong>
                            <?= htmlspecialchars(formatReservationEditDateTime($reservation['end_time']), ENT_QUOTES, 'UTF-8') ?>
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

    <!-- MESSAGE -->
    <?php if ($message !== ''): ?>
        <section class="reserve-alert <?= $messageStatus ? 'is-success' : 'is-error' ?> reveal-on-scroll">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        </section>
    <?php endif; ?>

    <!-- NOT EDITABLE -->
    <?php if (!$canEdit): ?>
        <section class="detail-panel reveal-on-scroll">

            <div class="detail-section-header">
                <div>
                    <span class="detail-section-label">
                        Edit Locked
                    </span>

                    <h2>
                        Reservation cannot be edited.
                    </h2>

                    <p>
                        Only future active reservations can be edited.
                    </p>
                </div>

                <span class="detail-status-badge is-warning">
                    Locked
                </span>
            </div>

            <div class="detail-info-grid">

                <div class="detail-info-row">
                    <span>Current Status</span>
                    <strong><?= htmlspecialchars(ucfirst($reservation['status']), ENT_QUOTES, 'UTF-8') ?></strong>
                </div>

                <div class="detail-info-row">
                    <span>Start Time</span>
                    <strong><?= htmlspecialchars(formatReservationEditDateTime($reservation['start_time']), ENT_QUOTES, 'UTF-8') ?></strong>
                </div>

                <div class="detail-info-row">
                    <span>End Time</span>
                    <strong><?= htmlspecialchars(formatReservationEditDateTime($reservation['end_time']), ENT_QUOTES, 'UTF-8') ?></strong>
                </div>

            </div>

        </section>
    <?php endif; ?>

    <!-- SUMMARY -->
    <section class="detail-panel reveal-on-scroll">

        <div class="detail-section-header">
            <div>
                <span class="detail-section-label">
                    Reservation Summary
                </span>

                <h2>
                    Check current reservation details.
                </h2>

                <p>
                    Review the selected laboratory and station before saving changes.
                </p>
            </div>

            <span class="detail-status-badge <?= reservationEditStatusClass($reservation['status']) ?>">
                <?= htmlspecialchars(ucfirst($reservation['status']), ENT_QUOTES, 'UTF-8') ?>
            </span>
        </div>

        <div class="detail-info-grid">

            <div class="detail-info-row">
                <span>Laboratory</span>
                <strong>
                    <?= htmlspecialchars($reservation['lab_code'] . ' — ' . $reservation['lab_name'], ENT_QUOTES, 'UTF-8') ?>
                </strong>
            </div>

            <div class="detail-info-row">
                <span>Station</span>
                <strong>
                    <?= htmlspecialchars($reservation['station_code'] . ' — ' . $reservation['station_name'], ENT_QUOTES, 'UTF-8') ?>
                </strong>
            </div>

            <div class="detail-info-row">
                <span>Location</span>
                <strong>
                    <?= htmlspecialchars($reservation['location'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                </strong>
            </div>

            <div class="detail-info-row">
                <span>Current Date</span>
                <strong>
                    <?= htmlspecialchars(formatReservationEditDate($reservation['start_time']), ENT_QUOTES, 'UTF-8') ?>
                </strong>
            </div>

            <div class="detail-info-row">
                <span>Current Time</span>
                <strong>
                    <?= htmlspecialchars(formatReservationEditTime($reservation['start_time']), ENT_QUOTES, 'UTF-8') ?>
                    —
                    <?= htmlspecialchars(formatReservationEditTime($reservation['end_time']), ENT_QUOTES, 'UTF-8') ?>
                </strong>
            </div>

            <div class="detail-info-row">
                <span>Purpose</span>
                <strong>
                    <?= htmlspecialchars(trim($reservation['purpose'] ?? '') !== '' ? $reservation['purpose'] : '-', ENT_QUOTES, 'UTF-8') ?>
                </strong>
            </div>

        </div>

    </section>

    <!-- CONFLICTS -->
    <?php if (!empty($conflicts)): ?>
        <section class="detail-panel reveal-on-scroll">

            <div class="detail-section-header">
                <div>
                    <span class="detail-section-label">
                        Conflict
                    </span>

                    <h2>
                        Conflicting reservations.
                    </h2>

                    <p>
                        The selected time interval overlaps with another active reservation.
                    </p>
                </div>

                <span class="detail-status-badge is-warning">
                    Unavailable
                </span>
            </div>

            <div class="detail-table-wrapper">
                <table class="detail-table">
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

                                <td>
                                    <?= htmlspecialchars($conflict['user_full_name'], ENT_QUOTES, 'UTF-8') ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(formatReservationEditDateTime($conflict['start_time']), ENT_QUOTES, 'UTF-8') ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(formatReservationEditDateTime($conflict['end_time']), ENT_QUOTES, 'UTF-8') ?>
                                </td>

                                <td>
                                    <span class="detail-status-badge <?= reservationEditStatusClass($conflict['status']) ?>">
                                        <?= htmlspecialchars(ucfirst($conflict['status']), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>

                                <td>
                                    <?= htmlspecialchars($conflict['purpose'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </section>
    <?php endif; ?>

    <!-- FORM -->
    <?php if ($canEdit): ?>
        <section class="detail-panel edit-form-panel reveal-on-scroll" id="reservationEditFormCard">

            <div class="detail-section-header">
                <div>
                    <span class="detail-section-label">
                        Update Reservation
                    </span>

                    <h2>
                        Choose a new time slot.
                    </h2>

                    <p>
                        Select a future date and available 2-hour slot. The system blocks overlapping active reservations.
                    </p>
                </div>

                <span class="detail-status-badge is-info">
                    Editable
                </span>
            </div>

            <form
                method="POST"
                action=""
                id="reservationEditForm"
                data-reservation-id="<?= (int) $reservation['reservation_id'] ?>"
                data-station-id="<?= (int) $reservation['station_id'] ?>"
                data-current-start="<?= htmlspecialchars(datetimeLocalEditValue($startTimeValue), ENT_QUOTES, 'UTF-8') ?>"
                data-current-end="<?= htmlspecialchars(datetimeLocalEditValue($endTimeValue), ENT_QUOTES, 'UTF-8') ?>"
                class="reserve-form"
            >
                <input
                    type="hidden"
                    id="start_time"
                    name="start_time"
                    value="<?= htmlspecialchars(datetimeLocalEditValue($startTimeValue), ENT_QUOTES, 'UTF-8') ?>"
                >

                <input
                    type="hidden"
                    id="end_time"
                    name="end_time"
                    value="<?= htmlspecialchars(datetimeLocalEditValue($endTimeValue), ENT_QUOTES, 'UTF-8') ?>"
                >

                <div class="reserve-form-group">
                    <label>New Reservation Date</label>

                    <small>
                        You can select today or one of the next 14 days.
                    </small>

                    <div
                        class="reservation-date-grid reserve-date-grid"
                        id="reservationEditDatePicker"
                        aria-label="Reservation date selection"
                    ></div>
                </div>

                <div
                    class="reserve-form-group reservation-slot-section"
                    id="reservationEditSlotSection"
                    hidden
                >
                    <label>Available Time Slots</label>

                    <small>
                        Each reservation slot is 2 hours. Unavailable slots are shown as disabled.
                    </small>

                    <div
                        class="reservation-slot-grid reserve-slot-grid"
                        id="reservationEditSlotGrid"
                        aria-label="Reservation time slot selection"
                    ></div>
                </div>

                <div
                    class="reservation-selected-slot reserve-selected-slot"
                    id="reservationEditSelectedSlot"
                    hidden
                >
                    <strong>Selected Slot:</strong>
                    <span id="reservationEditSelectedSlotText">-</span>
                </div>

                <div
                    id="reservationEditClientMessage"
                    class="reservation-availability-message reserve-availability-message"
                    style="display:none;"
                ></div>

                <div class="reserve-form-group">
                    <label for="purpose">Purpose</label>

                    <textarea
                        id="purpose"
                        name="purpose"
                        rows="4"
                        maxlength="255"
                        placeholder="Example: Database project study"
                    ><?= htmlspecialchars($purposeValue, ENT_QUOTES, 'UTF-8') ?></textarea>

                    <small>
                        Optional. Maximum 255 characters.
                    </small>
                </div>

                <div class="reserve-form-actions">
                    <button type="submit" class="reserve-btn reserve-btn-primary">
                        Save Changes
                    </button>

                    <a
                        href="reservation-detail.php?id=<?= (int) $reservation['reservation_id'] ?>"
                        class="reserve-btn reserve-btn-outline"
                    >
                        Cancel Editing
                    </a>
                </div>

            </form>

        </section>
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

    const editTiltCard = document.querySelector('[data-edit-tilt-card]');

    if (editTiltCard) {
        editTiltCard.addEventListener('pointermove', function (event) {
            const rect = editTiltCard.getBoundingClientRect();

            const x = event.clientX - rect.left;
            const y = event.clientY - rect.top;

            const rotateY = ((x / rect.width) - 0.5) * 5;
            const rotateX = ((y / rect.height) - 0.5) * -5;

            editTiltCard.style.transform =
                'perspective(1200px) rotateX(' + rotateX + 'deg) rotateY(' + rotateY + 'deg)';
        });

        editTiltCard.addEventListener('pointerleave', function () {
            editTiltCard.style.transform =
                'perspective(1200px) rotateX(0deg) rotateY(0deg)';
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>