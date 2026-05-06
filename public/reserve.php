<?php

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/lab_helper.php';
require_once __DIR__ . '/../helpers/reservation_helper.php';
require_once __DIR__ . '/../helpers/validation_helper.php';
require_once __DIR__ . '/../includes/csrf.php';

$userId = getCurrentUserId();

$labs = getAllLabs($pdo);
$stations = [];
$selectedStation = null;

$message = '';
$messageStatus = false;
$createdReservationId = null;
$conflicts = [];

$labId = null;
$stationId = null;

$startTimeValue = $_POST['start_time'] ?? '';
$endTimeValue = $_POST['end_time'] ?? '';
$purposeValue = trim($_POST['purpose'] ?? '');

function selectedOption($currentValue, $expectedValue): string
{
    return (string) $currentValue === (string) $expectedValue ? 'selected' : '';
}

/**
 * GET isteği:
 * Kullanıcı lab/station seçerken çalışır.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $labIdInput = filter_input(INPUT_GET, 'lab_id', FILTER_VALIDATE_INT);
    $stationIdInput = filter_input(INPUT_GET, 'station_id', FILTER_VALIDATE_INT);

    $labId = $labIdInput ? (int) $labIdInput : null;
    $stationId = $stationIdInput ? (int) $stationIdInput : null;

    if ($stationId !== null) {
        $selectedStation = getReservationStationContext($pdo, $stationId);

        if (!$selectedStation) {
            $messageStatus = false;
            $message = 'Selected station was not found.';
            $stationId = null;
        } elseif ($labId !== null && (int) $selectedStation['lab_id'] !== (int) $labId) {
            $messageStatus = false;
            $message = 'Selected station does not belong to the selected laboratory.';
            $selectedStation = null;
            $stationId = null;
        } else {
            $labId = (int) $selectedStation['lab_id'];
            $stationId = (int) $selectedStation['station_id'];
        }
    }

    if ($labId !== null) {
        $stations = getStationsByLab($pdo, $labId);
    }
}

/**
 * POST isteği:
 * AJAX çalışmazsa fallback olarak çalışır.
 * Asıl yeni akış JS + API üzerinden çalışacak ama burası da güvenli kalmalı.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();

    $action = $_POST['action'] ?? '';

    $postedLabId = filter_input(INPUT_POST, 'lab_id', FILTER_VALIDATE_INT);
    $postedStationId = filter_input(INPUT_POST, 'station_id', FILTER_VALIDATE_INT);

    $labId = $postedLabId ? (int) $postedLabId : null;
    $stationId = $postedStationId ? (int) $postedStationId : null;

    $startTimeValue = trim($_POST['start_time'] ?? '');
    $endTimeValue = trim($_POST['end_time'] ?? '');
    $purposeValue = trim($_POST['purpose'] ?? '');

    $startTime = normalizeDateTimeForDatabase($startTimeValue);
    $endTime = normalizeDateTimeForDatabase($endTimeValue);

    if (!$stationId) {
        $messageStatus = false;
        $message = 'Valid station is required.';
    } else {
        $selectedStation = getReservationStationContext($pdo, $stationId);

        if (!$selectedStation) {
            $messageStatus = false;
            $message = 'Selected station was not found.';
            $stationId = null;
        } else {
            $realLabId = (int) $selectedStation['lab_id'];
            $realStationId = (int) $selectedStation['station_id'];

            if ($labId !== null && $labId !== $realLabId) {
                $messageStatus = false;
                $message = 'Selected station does not belong to the selected laboratory.';
                $selectedStation = null;
                $stationId = null;
            } else {
                $labId = $realLabId;
                $stationId = $realStationId;

                if ((int) $selectedStation['lab_is_active'] !== 1) {
                    $messageStatus = false;
                    $message = 'This laboratory is not active.';
                } elseif ($selectedStation['station_status'] !== 'active') {
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
                            $stationId,
                            $startTime,
                            $endTime
                        );

                        if (!$isAvailable) {
                            $messageStatus = false;
                            $message = 'This station is not available for the selected time slot.';

                            $conflicts = getConflictingReservations(
                                $pdo,
                                $stationId,
                                $startTime,
                                $endTime
                            );
                        } elseif ($action === 'create') {
                            try {
                                $pdo->beginTransaction();

                                $createdReservationId = createReservation(
                                    $pdo,
                                    (int) $userId,
                                    $labId,
                                    $stationId,
                                    $startTime,
                                    $endTime,
                                    $purposeValue !== '' ? mb_substr($purposeValue, 0, 255) : null
                                );

                                addReservationStatusHistory(
                                    $pdo,
                                    (int) $createdReservationId,
                                    null,
                                    'active',
                                    (int) $userId,
                                    'Reservation created.'
                                );

                                $pdo->commit();

                                $messageStatus = true;
                                $message = 'Reservation created successfully.';
                            } catch (Exception $e) {
                                if ($pdo->inTransaction()) {
                                    $pdo->rollBack();
                                }

                                $messageStatus = false;
                                $message = DEBUG_MODE
                                    ? 'Reservation creation failed: ' . $e->getMessage()
                                    : 'Reservation creation failed.';
                            }
                        } else {
                            $messageStatus = true;
                            $message = 'This station is available for the selected time slot.';
                        }
                    }
                }
            }
        }
    }

    if ($labId !== null) {
        $stations = getStationsByLab($pdo, $labId);
    }
}

$pageTitle = 'Reserve Station';
$pageCss = 'reservation.css';
$pageJs = 'reservation.js';

require_once __DIR__ . '/../includes/header.php';

?>

<section
    class="page-section"
    data-reservation-page="reserve"
    data-selected-lab-id="<?= $labId !== null ? (int) $labId : '' ?>"
    data-selected-station-id="<?= $stationId !== null ? (int) $stationId : '' ?>"
>
    <div class="container">

        <!-- HERO -->
        <div class="card reservation-hero-card" style="margin-bottom:32px;">
            <div class="reservation-hero-content">
                <div>
                    <span class="badge badge-info">
                        Laboratory Reservation
                    </span>

                    <h1 class="section-title" style="margin-bottom:8px; margin-top:16px;">
                        Reserve Station
                    </h1>

                    <p class="section-subtitle" style="margin-bottom:0;">
                        Choose a laboratory, select a station and create your reservation
                        with a clear and simple workflow.
                    </p>
                </div>

                <div class="reservation-hero-actions">
                    <a href="labs.php" class="btn btn-outline">
                        Browse Laboratories
                    </a>

                    <a href="my-reservations.php" class="btn btn-secondary">
                        My Reservations
                    </a>
                </div>
            </div>
        </div>

        <!-- FLOW STEPS -->
        <div class="reservation-stepper" style="margin-bottom:24px;">
            <div class="reservation-stepper-item <?= !$labId ? 'is-active' : 'is-complete' ?>">
                <span>1</span>
                <strong>Lab</strong>
            </div>

            <div class="reservation-stepper-item <?= $labId && !$selectedStation ? 'is-active' : ($selectedStation ? 'is-complete' : '') ?>">
                <span>2</span>
                <strong>Station</strong>
            </div>

            <div class="reservation-stepper-item <?= $selectedStation ? 'is-active' : '' ?>">
                <span>3</span>
                <strong>Details</strong>
            </div>

            <div class="reservation-stepper-item">
                <span>4</span>
                <strong>Confirm</strong>
            </div>
        </div>

        <!-- LABORATORY AND STATION SELECTION -->
        <div class="card" style="margin-bottom:24px;">
            <h2 style="margin-top:0;">Select Laboratory and Station</h2>

            <p class="section-subtitle" style="margin-bottom:24px;">
                Choose a laboratory first, then select an active station for your reservation.
            </p>

            <form
                method="GET"
                action=""
                id="reservationSelectionForm"
                data-selected-station-id="<?= $stationId !== null ? (int) $stationId : '' ?>"
            >
                <div class="grid grid-2">
                    <div class="form-group">
                        <label for="lab_id" class="form-label">Laboratory</label>

                        <select id="lab_id" name="lab_id" class="form-control" required>
                            <option value="">Select laboratory</option>

                            <?php foreach ($labs as $lab): ?>
                                <option
                                    value="<?= (int) $lab['lab_id'] ?>"
                                    <?= selectedOption($labId, $lab['lab_id']) ?>
                                >
                                    <?= htmlspecialchars($lab['lab_code'] . ' - ' . $lab['lab_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <small class="field-feedback">
                            Select a laboratory to view its stations.
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="station_id" class="form-label">Station</label>

                        <select
                            id="station_id"
                            name="station_id"
                            class="form-control"
                            <?= $labId ? '' : 'disabled' ?>
                            required
                        >
                            <option value="">
                                <?= $labId ? 'Select station' : 'Select laboratory first' ?>
                            </option>

                            <?php foreach ($stations as $station): ?>
                                <?php $isActiveStation = ($station['status'] === 'active'); ?>

                                <option
                                    value="<?= (int) $station['station_id'] ?>"
                                    data-status="<?= htmlspecialchars($station['status']) ?>"
                                    data-code="<?= htmlspecialchars($station['station_code']) ?>"
                                    data-name="<?= htmlspecialchars($station['station_name']) ?>"
                                    <?= selectedOption($stationId, $station['station_id']) ?>
                                    <?= $isActiveStation ? '' : 'disabled' ?>
                                >
                                    <?= htmlspecialchars(
                                        $station['station_code']
                                        . ' - '
                                        . $station['station_name']
                                        . ' ('
                                        . $station['status']
                                        . ')'
                                    ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <small class="field-feedback" id="stationSelectFeedback">
                            Only active stations can be selected.
                        </small>
                    </div>
                </div>

                <div class="flex" style="gap:16px; flex-wrap:wrap;">
                    <button type="submit" class="btn btn-primary">
                        Select Station
                    </button>

                    <a href="reserve.php" class="btn btn-outline">
                        Clear Selection
                    </a>
                </div>
            </form>
        </div>

        <!-- SELECTED STATION SUMMARY -->
        <div
            class="card"
            id="selectedStationCard"
            style="margin-bottom:24px; <?= $selectedStation ? '' : 'display:none;' ?>"
        >
            <h2 style="margin-top:0;">Selected Station Summary</h2>

            <?php if ($selectedStation): ?>
                <div class="grid grid-2">
                    <div>
                        <p>
                            <strong>Laboratory:</strong>
                            <?= htmlspecialchars($selectedStation['lab_name']) ?>
                        </p>

                        <p>
                            <strong>Station:</strong>
                            <?= htmlspecialchars($selectedStation['station_code'] . ' - ' . $selectedStation['station_name']) ?>
                        </p>

                        <p>
                            <strong>Type:</strong>
                            <?= htmlspecialchars(formatStationTypeName($selectedStation['type_name'] ?? null), ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    </div>

                    <div>
                        <p>
                            <strong>Capacity:</strong>
                            <?= (int) $selectedStation['capacity'] ?>
                        </p>

                        <p>
                            <strong>Status:</strong>
                            <span class="badge <?= $selectedStation['station_status'] === 'active' ? 'badge-success' : 'badge-warning' ?>">
                                <?= htmlspecialchars($selectedStation['station_status']) ?>
                            </span>
                        </p>

                        <p>
                            <strong>Location:</strong>
                            <?= htmlspecialchars($selectedStation['location'] ?? '-') ?>
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <div
                id="stationEquipmentPanel"
                class="reservation-equipment-panel"
                data-station-id="<?= $selectedStation ? (int) $selectedStation['station_id'] : '' ?>"
                style="margin-top:24px;"
            >
                <h3 style="margin-top:0;">Station Equipment</h3>

                <div id="stationEquipmentList" class="reservation-equipment-list">
                    <p style="color:var(--color-muted); margin-bottom:0;">
                        Equipment will be shown after a station is selected.
                    </p>
                </div>
            </div>
        </div>

        <!-- PHP FALLBACK MESSAGE -->
        <?php if ($message !== ''): ?>
            <div
                class="alert <?= $messageStatus ? 'alert-success' : 'alert-error' ?>"
                style="margin-bottom:24px;"
            >
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- CREATED -->
        <?php if ($createdReservationId): ?>
            <div class="card" style="margin-bottom:24px; text-align:center;">
                <span class="badge badge-success">
                    Success
                </span>

                <h3>Reservation Created Successfully</h3>

                <p>
                    You can view and manage your reservation from your reservations page.
                </p>

                <a href="my-reservations.php" class="btn btn-primary">
                    Go to My Reservations
                </a>
            </div>
        <?php endif; ?>

        <!-- CONFLICT -->
        <?php if (!empty($conflicts)): ?>
            <div class="card" style="margin-bottom:24px;">
                <h2 style="margin-top:0;">Unavailable Time Slot</h2>

                <p class="section-subtitle" style="margin-bottom:24px;">
                    The selected station is already reserved during the time below.
                    Please choose another time slot.
                </p>

                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Start</th>
                                <th>End</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($conflicts as $conflict): ?>
                                <tr>
                                    <td><?= htmlspecialchars($conflict['start_time']) ?></td>
                                    <td><?= htmlspecialchars($conflict['end_time']) ?></td>
                                    <td><?= htmlspecialchars($conflict['status']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- RESERVATION FORM -->
        <?php if ($selectedStation): ?>
            <div class="card" id="reservationFormCard">
                <h2 style="margin-top:0;">Reservation Details</h2>

                <p class="section-subtitle" style="margin-bottom:24px;">
                    Select a date within the next 15 days, then choose an available 2-hour time slot.
                </p>

                <form method="POST" action="" id="reservationForm">
                    <?= csrfInput() ?>

                    <input
                        type="hidden"
                        name="lab_id"
                        id="reservation_lab_id"
                        value="<?= (int) $selectedStation['lab_id'] ?>"
                    >

                    <input
                        type="hidden"
                        name="station_id"
                        id="reservation_station_id"
                        value="<?= (int) $selectedStation['station_id'] ?>"
                    >

                    <input
                        type="hidden"
                        id="start_time"
                        name="start_time"
                        value="<?= htmlspecialchars($startTimeValue) ?>"
                    >

                    <input
                        type="hidden"
                        id="end_time"
                        name="end_time"
                        value="<?= htmlspecialchars($endTimeValue) ?>"
                    >

                    <div class="form-group">
                        <label class="form-label">Reservation Date</label>

                        <p class="field-hint">
                            You can select today or one of the next 14 days.
                        </p>

                        <div
                            class="reservation-date-grid"
                            id="reservationDatePicker"
                            aria-label="Reservation date selection"
                        ></div>
                    </div>

                    <div
                        class="form-group reservation-slot-section"
                        id="reservationSlotSection"
                        hidden
                    >
                        <label class="form-label">Available Time Slots</label>

                        <p class="field-hint">
                            Each reservation slot is 2 hours. Unavailable slots are disabled.
                        </p>

                        <div
                            class="reservation-slot-grid"
                            id="reservationSlotGrid"
                            aria-label="Reservation time slot selection"
                        ></div>
                    </div>

                    <div
                        class="reservation-selected-slot"
                        id="reservationSelectedSlot"
                        hidden
                    >
                        <strong>Selected Slot:</strong>
                        <span id="reservationSelectedSlotText">-</span>
                    </div>

                    <div class="form-group">
                        <label for="purpose" class="form-label">Purpose</label>

                        <textarea
                            id="purpose"
                            name="purpose"
                            class="form-control"
                            rows="4"
                            maxlength="255"
                            placeholder="Example: Laboratory practice session"
                        ><?= htmlspecialchars($purposeValue) ?></textarea>

                        <p class="field-hint">
                            Optional. Maximum 255 characters.
                        </p>
                    </div>

                    <div
                        id="availabilityMessage"
                        class="reservation-availability-message"
                        style="display:none; margin-bottom:24px;"
                    ></div>

                    <div class="flex reservation-actions" style="gap:16px; flex-wrap:wrap;">
                        <button
                            type="button"
                            name="action"
                            value="check"
                            class="btn btn-secondary"
                            id="checkAvailabilityButton"
                            data-reservation-action="check"
                        >
                            Check Availability
                        </button>

                        <button
                            type="button"
                            name="action"
                            value="create"
                            class="btn btn-primary"
                            id="createReservationButton"
                            data-reservation-action="create"
                        >
                            Create Reservation
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>