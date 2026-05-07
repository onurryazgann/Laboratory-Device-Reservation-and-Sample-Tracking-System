<?php

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/lab_helper.php';
require_once __DIR__ . '/../helpers/reservation_helper.php';
require_once __DIR__ . '/../helpers/validation_helper.php';

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
 * GET request:
 * Works while the user selects a laboratory/station.
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
 * POST request:
 * Safe fallback if AJAX does not work.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
$bodyClass = 'page-reserve';

require_once __DIR__ . '/../includes/header.php';

?>

<section
    class="reserve-page"
    data-reservation-page="reserve"
    data-selected-lab-id="<?= $labId !== null ? (int) $labId : '' ?>"
    data-selected-station-id="<?= $stationId !== null ? (int) $stationId : '' ?>"
>

    <!-- HERO -->
    <section class="reserve-hero" data-reserve-tilt-card>

        <div class="reserve-hero-content">

            <span class="reserve-eyebrow">
                Laboratory Reservation
            </span>

            <h1>
                Reserve a station with a clear step-by-step workflow.
            </h1>

            <p>
                Choose a laboratory, select an active station, review station equipment,
                check available time slots and create your reservation.
            </p>

            <div class="reserve-hero-actions">
                <a href="labs.php" class="reserve-btn reserve-btn-light">
                    Browse Laboratories
                </a>

                <a href="my-reservations.php" class="reserve-btn reserve-btn-primary">
                    My Reservations
                </a>
            </div>

        </div>

        <div class="reserve-hero-visual">

            <div class="reserve-mini-panel">

                <div class="reserve-mini-header">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>

                <div class="reserve-mini-body">

                    <div class="reserve-mini-title">
                        <div>
                            <small>Reservation Flow</small>
                            <strong>Station Booking</strong>
                        </div>

                        <span>
                            Live
                        </span>
                    </div>

                    <div class="reserve-mini-list">

                        <div class="reserve-mini-item <?= !$labId ? 'is-active' : 'is-complete' ?>">
                            <span>01</span>
                            <div>
                                <strong>Choose Laboratory</strong>
                                <small>Select the laboratory you want to use.</small>
                            </div>
                        </div>

                        <div class="reserve-mini-item <?= $labId && !$selectedStation ? 'is-active' : ($selectedStation ? 'is-complete' : '') ?>">
                            <span>02</span>
                            <div>
                                <strong>Select Station</strong>
                                <small>Choose an active station for reservation.</small>
                            </div>
                        </div>

                        <div class="reserve-mini-item <?= $selectedStation ? 'is-active' : '' ?>">
                            <span>03</span>
                            <div>
                                <strong>Pick Time Slot</strong>
                                <small>Check availability before creating a request.</small>
                            </div>
                        </div>

                    </div>

                </div>

            </div>

            <div class="reserve-floating-chip reserve-chip-one">
                <span>✓</span>
                Station Check
            </div>

            <div class="reserve-floating-chip reserve-chip-two">
                <span>⏱</span>
                Time Slot
            </div>

            <div class="reserve-floating-chip reserve-chip-three">
                <span>📌</span>
                Reservation
            </div>

        </div>

    </section>

    <!-- STEP INDICATOR -->
    <section class="reserve-stepper">

        <div class="reserve-stepper-item <?= !$labId ? 'is-active' : 'is-complete' ?>">
            <span>1</span>
            <strong>Laboratory</strong>
        </div>

        <div class="reserve-stepper-item <?= $labId && !$selectedStation ? 'is-active' : ($selectedStation ? 'is-complete' : '') ?>">
            <span>2</span>
            <strong>Station</strong>
        </div>

        <div class="reserve-stepper-item <?= $selectedStation ? 'is-active' : '' ?>">
            <span>3</span>
            <strong>Details</strong>
        </div>

        <div class="reserve-stepper-item">
            <span>4</span>
            <strong>Confirm</strong>
        </div>

    </section>

    <!-- SELECTION FORM -->
    <section class="reserve-panel reveal-on-scroll">

        <div class="reserve-section-header">
            <div>
                <span class="reserve-section-label">
                    Select Station
                </span>

                <h2>
                    Choose laboratory and station.
                </h2>

                <p>
                    Select a laboratory first, then choose an active station for your reservation.
                </p>
            </div>

            <span class="reserve-status-badge <?= $selectedStation ? 'is-success' : 'is-info' ?>">
                <?= $selectedStation ? 'Station Selected' : 'Selection Required' ?>
            </span>
        </div>

        <form
            method="GET"
            action=""
            id="reservationSelectionForm"
            data-selected-station-id="<?= $stationId !== null ? (int) $stationId : '' ?>"
            class="reserve-form"
        >

            <div class="reserve-form-grid">

                <div class="reserve-form-group">
                    <label for="lab_id">Laboratory</label>

                    <select id="lab_id" name="lab_id" required>
                        <option value="">Select laboratory</option>

                        <?php foreach ($labs as $lab): ?>
                            <option
                                value="<?= (int) $lab['lab_id'] ?>"
                                <?= selectedOption($labId, $lab['lab_id']) ?>
                            >
                                <?= htmlspecialchars($lab['lab_code'] . ' - ' . $lab['lab_name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <small>
                        Select a laboratory to view its stations.
                    </small>
                </div>

                <div class="reserve-form-group">
                    <label for="station_id">Station</label>

                    <select
                        id="station_id"
                        name="station_id"
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
                                data-status="<?= htmlspecialchars($station['status'], ENT_QUOTES, 'UTF-8') ?>"
                                data-code="<?= htmlspecialchars($station['station_code'], ENT_QUOTES, 'UTF-8') ?>"
                                data-name="<?= htmlspecialchars($station['station_name'], ENT_QUOTES, 'UTF-8') ?>"
                                <?= selectedOption($stationId, $station['station_id']) ?>
                                <?= $isActiveStation ? '' : 'disabled' ?>
                            >
                                <?= htmlspecialchars(
                                    $station['station_code']
                                    . ' - '
                                    . $station['station_name']
                                    . ' ('
                                    . $station['status']
                                    . ')',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <small id="stationSelectFeedback">
                        Only active stations can be selected.
                    </small>
                </div>

            </div>

            <div class="reserve-form-actions">
                <button type="submit" class="reserve-btn reserve-btn-primary">
                    Select Station
                </button>

                <a href="reserve.php" class="reserve-btn reserve-btn-outline">
                    Clear Selection
                </a>
            </div>

        </form>

    </section>

    <!-- SELECTED STATION SUMMARY -->
    <section
        class="reserve-panel reveal-on-scroll"
        id="selectedStationCard"
        style="<?= $selectedStation ? '' : 'display:none;' ?>"
    >

        <div class="reserve-section-header">
            <div>
                <span class="reserve-section-label">
                    Station Summary
                </span>

                <h2>
                    Selected station details.
                </h2>

                <p>
                    Review the selected laboratory, station details and assigned equipment.
                </p>
            </div>

            <?php if ($selectedStation): ?>
                <span class="reserve-status-badge <?= $selectedStation['station_status'] === 'active' ? 'is-success' : 'is-warning' ?>">
                    <?= htmlspecialchars(ucfirst($selectedStation['station_status']), ENT_QUOTES, 'UTF-8') ?>
                </span>
            <?php endif; ?>
        </div>

        <?php if ($selectedStation): ?>

            <div class="reserve-summary-grid">

                <div class="reserve-summary-card">
                    <span>Laboratory</span>
                    <strong><?= htmlspecialchars($selectedStation['lab_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                </div>

                <div class="reserve-summary-card">
                    <span>Station</span>
                    <strong><?= htmlspecialchars($selectedStation['station_code'] . ' - ' . $selectedStation['station_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                </div>

                <div class="reserve-summary-card">
                    <span>Type</span>
                    <strong><?= htmlspecialchars(formatStationTypeName($selectedStation['type_name'] ?? null), ENT_QUOTES, 'UTF-8') ?></strong>
                </div>

                <div class="reserve-summary-card">
                    <span>Capacity</span>
                    <strong><?= (int) $selectedStation['capacity'] ?></strong>
                </div>

                <div class="reserve-summary-card">
                    <span>Status</span>
                    <strong><?= htmlspecialchars(ucfirst($selectedStation['station_status']), ENT_QUOTES, 'UTF-8') ?></strong>
                </div>

                <div class="reserve-summary-card">
                    <span>Location</span>
                    <strong><?= htmlspecialchars($selectedStation['location'] ?? '-', ENT_QUOTES, 'UTF-8') ?></strong>
                </div>

            </div>

        <?php endif; ?>

        <div
            id="stationEquipmentPanel"
            class="reservation-equipment-panel reserve-equipment-panel"
            data-station-id="<?= $selectedStation ? (int) $selectedStation['station_id'] : '' ?>"
        >
            <div class="reserve-subsection-heading">
                <h3>
                    Station Equipment
                </h3>

                <p>
                    Equipment information is loaded after a station is selected.
                </p>
            </div>

            <div id="stationEquipmentList" class="reservation-equipment-list reserve-equipment-list">
                <p class="reserve-muted-text">
                    Equipment will be shown after a station is selected.
                </p>
            </div>
        </div>

    </section>

    <!-- PHP FALLBACK MESSAGE -->
    <?php if ($message !== ''): ?>
        <section class="reserve-alert <?= $messageStatus ? 'is-success' : 'is-error' ?> reveal-on-scroll">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        </section>
    <?php endif; ?>

    <!-- CREATED -->
    <?php if ($createdReservationId): ?>
        <section class="reserve-success-card reveal-on-scroll">

            <span class="reserve-status-badge is-success">
                Success
            </span>

            <h3>
                Reservation Created Successfully
            </h3>

            <p>
                You can view and manage your reservation from your reservations page.
            </p>

            <a href="my-reservations.php" class="reserve-btn reserve-btn-primary">
                Go to My Reservations
            </a>

        </section>
    <?php endif; ?>

    <!-- CONFLICT -->
    <?php if (!empty($conflicts)): ?>
        <section class="reserve-panel reveal-on-scroll">

            <div class="reserve-section-header">
                <div>
                    <span class="reserve-section-label">
                        Conflict
                    </span>

                    <h2>
                        Unavailable time slot.
                    </h2>

                    <p>
                        The selected station is already reserved during the time below.
                        Please choose another time slot.
                    </p>
                </div>

                <span class="reserve-status-badge is-warning">
                    Unavailable
                </span>
            </div>

            <div class="reserve-table-wrapper">
                <table class="reserve-table">
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
                                <td><?= htmlspecialchars($conflict['start_time'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($conflict['end_time'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($conflict['status'], ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </section>
    <?php endif; ?>

    <!-- RESERVATION FORM -->
    <?php if ($selectedStation): ?>
        <section class="reserve-panel reveal-on-scroll" id="reservationFormCard">

            <div class="reserve-section-header">
                <div>
                    <span class="reserve-section-label">
                        Reservation Details
                    </span>

                    <h2>
                        Select date and time.
                    </h2>

                    <p>
                        Select a date within the next 15 days, then choose an available 2-hour time slot.
                    </p>
                </div>

                <span class="reserve-status-badge is-info">
                    Time Selection
                </span>
            </div>

            <form method="POST" action="" id="reservationForm" class="reserve-form">

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
                    value="<?= htmlspecialchars($startTimeValue, ENT_QUOTES, 'UTF-8') ?>"
                >

                <input
                    type="hidden"
                    id="end_time"
                    name="end_time"
                    value="<?= htmlspecialchars($endTimeValue, ENT_QUOTES, 'UTF-8') ?>"
                >

                <div class="reserve-form-group">
                    <label>Reservation Date</label>

                    <small>
                        You can select today or one of the next 14 days.
                    </small>

                    <div
                        class="reservation-date-grid reserve-date-grid"
                        id="reservationDatePicker"
                        aria-label="Reservation date selection"
                    ></div>
                </div>

                <div
                    class="reserve-form-group reservation-slot-section"
                    id="reservationSlotSection"
                    hidden
                >
                    <label>Available Time Slots</label>

                    <small>
                        Each reservation slot is 2 hours. Unavailable slots are disabled.
                    </small>

                    <div
                        class="reservation-slot-grid reserve-slot-grid"
                        id="reservationSlotGrid"
                        aria-label="Reservation time slot selection"
                    ></div>
                </div>

                <div
                    class="reservation-selected-slot reserve-selected-slot"
                    id="reservationSelectedSlot"
                    hidden
                >
                    <strong>Selected Slot:</strong>
                    <span id="reservationSelectedSlotText">-</span>
                </div>

                <div class="reserve-form-group">
                    <label for="purpose">Purpose</label>

                    <textarea
                        id="purpose"
                        name="purpose"
                        rows="4"
                        maxlength="255"
                        placeholder="Example: Laboratory practice session"
                    ><?= htmlspecialchars($purposeValue, ENT_QUOTES, 'UTF-8') ?></textarea>

                    <small>
                        Optional. Maximum 255 characters.
                    </small>
                </div>

                <div
                    id="availabilityMessage"
                    class="reservation-availability-message reserve-availability-message"
                    style="display:none;"
                ></div>

                <div class="reserve-form-actions">
                    <button
                        type="button"
                        name="action"
                        value="check"
                        class="reserve-btn reserve-btn-light"
                        id="checkAvailabilityButton"
                        data-reservation-action="check"
                    >
                        Check Availability
                    </button>

                    <button
                        type="button"
                        name="action"
                        value="create"
                        class="reserve-btn reserve-btn-primary"
                        id="createReservationButton"
                        data-reservation-action="create"
                    >
                        Create Reservation
                    </button>
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

    const reserveTiltCard = document.querySelector('[data-reserve-tilt-card]');

    if (reserveTiltCard) {
        reserveTiltCard.addEventListener('pointermove', function (event) {
            const rect = reserveTiltCard.getBoundingClientRect();

            const x = event.clientX - rect.left;
            const y = event.clientY - rect.top;

            const rotateY = ((x / rect.width) - 0.5) * 5;
            const rotateX = ((y / rect.height) - 0.5) * -5;

            reserveTiltCard.style.transform =
                'perspective(1200px) rotateX(' + rotateX + 'deg) rotateY(' + rotateY + 'deg)';
        });

        reserveTiltCard.addEventListener('pointerleave', function () {
            reserveTiltCard.style.transform =
                'perspective(1200px) rotateX(0deg) rotateY(0deg)';
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>