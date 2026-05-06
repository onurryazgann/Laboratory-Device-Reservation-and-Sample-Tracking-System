<?php

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/lab_helper.php';
require_once __DIR__ . '/../../helpers/reservation_helper.php';
require_once __DIR__ . '/../../includes/csrf.php';

$adminUserId = getCurrentUserId();

syncExpiredReservations($pdo, (int) $adminUserId);

$pageTitle = 'Reservation Management';
$pageCss = 'admin.css';
$pageJs = 'admin-reservations.js';

$statusOptions = getReservationStatusOptions();
$labs = getAllLabs($pdo);

$message = '';
$messageStatus = false;

function selectedAdminOption($currentValue, $expectedValue): string
{
    return (string) $currentValue === (string) $expectedValue ? 'selected' : '';
}

function formatAdminReservationDateTime(?string $value): string
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

function adminReservationBadgeClass(string $status): string
{
    if ($status === 'active') {
        return 'badge-success';
    }

    if ($status === 'cancelled') {
        return 'badge-warning';
    }

    if ($status === 'completed') {
        return 'badge-secondary';
    }

    return 'badge-secondary';
}

function canAdminUpdateReservation(array $reservation): bool
{
    return $reservation['status'] === 'active';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();

    $action = $_POST['action'] ?? '';
    $reservationId = filter_input(INPUT_POST, 'reservation_id', FILTER_VALIDATE_INT);
    $newStatus = trim($_POST['new_status'] ?? '');

    if ($action === 'update_status') {
        if (!$reservationId) {
            $messageStatus = false;
            $message = 'Valid reservation ID is required.';
        } elseif (!isValidReservationStatus($newStatus)) {
            $messageStatus = false;
            $message = 'Invalid reservation status.';
        } else {
            $reservation = getReservationDetail($pdo, (int) $reservationId);

            if (!$reservation) {
                $messageStatus = false;
                $message = 'Reservation not found.';
            } elseif (!canAdminUpdateReservation($reservation)) {
                $messageStatus = false;
                $message = 'Only active reservations can be updated from this page.';
            } elseif ($reservation['status'] === $newStatus) {
                $messageStatus = false;
                $message = 'The selected status is already assigned to this reservation.';
            } else {
                try {
                    $pdo->beginTransaction();

                    $oldStatus = $reservation['status'];

                    updateReservationStatus(
                        $pdo,
                        (int) $reservationId,
                        $newStatus
                    );

                    addReservationStatusHistory(
                        $pdo,
                        (int) $reservationId,
                        $oldStatus,
                        $newStatus,
                        (int) $adminUserId,
                        'Reservation status updated by admin.'
                    );

                    $pdo->commit();

                    $messageStatus = true;
                    $message = 'Reservation status updated successfully.';
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    $messageStatus = false;
                    $message = DEBUG_MODE
                        ? 'Reservation status update failed: ' . $e->getMessage()
                        : 'Reservation status update failed.';
                }
            }
        }
    }
}

syncExpiredReservations($pdo, (int) $adminUserId);

$filters = [
    'status' => $_GET['status'] ?? '',
    'lab_id' => $_GET['lab_id'] ?? '',
    'q' => trim($_GET['q'] ?? ''),
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
];

if (
    $filters['status'] !== ''
    && !in_array($filters['status'], $statusOptions, true)
) {
    $filters['status'] = '';
}

if (
    $filters['lab_id'] !== ''
    && !filter_var($filters['lab_id'], FILTER_VALIDATE_INT)
) {
    $filters['lab_id'] = '';
}

$reservations = getAdminReservations($pdo, $filters);

$totalCount = count($reservations);
$activeCount = 0;
$cancelledCount = 0;
$completedCount = 0;

foreach ($reservations as $reservation) {
    if ($reservation['status'] === 'active') {
        $activeCount++;
    } elseif ($reservation['status'] === 'cancelled') {
        $cancelledCount++;
    } elseif ($reservation['status'] === 'completed') {
        $completedCount++;
    }
}

require_once __DIR__ . '/../../includes/header.php';

?>

<section class="page-section">
    <div class="container">

        <!-- HERO -->
        <div class="admin-card">

            <div class="admin-page-header">

                <div>
                    <h1 class="admin-page-title">
                        Reservation Governance Center
                    </h1>

                    <p class="section-subtitle">
                        Monitor all reservations, manage lifecycle states, and control operational reservation activity system-wide.
                    </p>
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

        <!-- FILTERS -->
        <div class="admin-card admin-filters">

            <h2>Filters</h2>

            <form method="GET" action="">
                <div class="grid grid-3">
                    <div class="form-group">
                        <label for="q" class="form-label">Search</label>

                        <input
                            type="text"
                            id="q"
                            name="q"
                            class="form-control"
                            value="<?= htmlspecialchars($filters['q']) ?>"
                            placeholder="User, email, lab, station, purpose..."
                        >
                    </div>

                    <div class="form-group">
                        <label for="status" class="form-label">Status</label>

                        <select id="status" name="status" class="form-control">
                            <option value="">All statuses</option>

                            <?php foreach ($statusOptions as $status): ?>
                                <option
                                    value="<?= htmlspecialchars($status) ?>"
                                    <?= selectedAdminOption($filters['status'], $status) ?>
                                >
                                    <?= htmlspecialchars(ucfirst($status)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="lab_id" class="form-label">Laboratory</label>

                        <select id="lab_id" name="lab_id" class="form-control">
                            <option value="">All laboratories</option>

                            <?php foreach ($labs as $lab): ?>
                                <option
                                    value="<?= (int) $lab['lab_id'] ?>"
                                    <?= selectedAdminOption($filters['lab_id'], $lab['lab_id']) ?>
                                >
                                    <?= htmlspecialchars($lab['lab_code'] . ' - ' . $lab['lab_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grid grid-2">
                    <div class="form-group">
                        <label for="date_from" class="form-label">Date From</label>

                        <input
                            type="date"
                            id="date_from"
                            name="date_from"
                            class="form-control"
                            value="<?= htmlspecialchars($filters['date_from']) ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label for="date_to" class="form-label">Date To</label>

                        <input
                            type="date"
                            id="date_to"
                            name="date_to"
                            class="form-control"
                            value="<?= htmlspecialchars($filters['date_to']) ?>"
                        >
                    </div>
                </div>

                <div class="admin-actions">
                    <button type="submit" class="btn btn-primary">
                        Apply Filters
                    </button>

                    <a href="reservations.php" class="btn btn-outline">
                        Clear Filters
                    </a>
                </div>
            </form>
        </div>

        <!-- KPI -->
        <div class="admin-kpi-grid">
            <div class="card card-hover">
                <h3>Total Shown</h3>

                <p style="font-size:36px; font-weight:700; margin:0; color:var(--color-primary);">
                    <span data-admin-reservation-kpi="total"><?= (int) $totalCount ?></span>
                </p>
            </div>

            <div class="card card-hover">
                <h3>Active</h3>

                <p style="font-size:36px; font-weight:700; margin:0;">
                    <span data-admin-reservation-kpi="active"><?= (int) $activeCount ?></span>
                </p>
            </div>

            <div class="card card-hover">
                <h3>Cancelled</h3>

                <p style="font-size:36px; font-weight:700; margin:0;">
                    <span data-admin-reservation-kpi="cancelled"><?= (int) $cancelledCount ?></span>
                </p>
            </div>

            <div class="card card-hover">
                <h3>Completed</h3>

                <p style="font-size:36px; font-weight:700; margin:0;">
                    <span data-admin-reservation-kpi="completed"><?= (int) $completedCount ?></span>
                </p>
            </div>
        </div>

        <!-- LIST -->
        <div class="admin-card">
            <h2>Reservation List</h2>

            <?php if (count($reservations) > 0): ?>
                <div class="table-wrapper admin-table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Email</th>
                                <th>Laboratory</th>
                                <th>Station</th>
                                <th>Start</th>
                                <th>End</th>
                                <th>Status</th>
                                <th>Purpose</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($reservations as $reservation): ?>
                                <tr data-admin-reservation-row="<?= (int) $reservation['reservation_id'] ?>">
                                    <td>
                                        <?= htmlspecialchars($reservation['user_full_name']) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($reservation['user_email'] ?? '-') ?>
                                    </td>

                                    <td>
                                        <strong>
                                            <?= htmlspecialchars($reservation['lab_code']) ?>
                                        </strong>

                                        <br>

                                        <span style="color:var(--color-muted);">
                                            <?= htmlspecialchars($reservation['lab_name']) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <strong>
                                            <?= htmlspecialchars($reservation['station_code']) ?>
                                        </strong>

                                        <br>

                                        <span style="color:var(--color-muted);">
                                            <?= htmlspecialchars($reservation['station_name']) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(formatAdminReservationDateTime($reservation['start_time'])) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(formatAdminReservationDateTime($reservation['end_time'])) ?>
                                    </td>

                                    <td>
                                        <span data-admin-reservation-status="<?= (int) $reservation['reservation_id'] ?>" class="badge <?= adminReservationBadgeClass($reservation['status']) ?>">
                                            <?= htmlspecialchars(ucfirst($reservation['status'])) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($reservation['purpose'] ?? '-') ?>
                                    </td>

                                    <td>
                                        <?php if (canAdminUpdateReservation($reservation)): ?>
                                            <form
                                                method="POST"
                                                action="reservations.php?<?= htmlspecialchars(http_build_query($filters)) ?>"
                                                class="js-admin-reservation-status-form"
                                                data-reservation-id="<?= (int) $reservation['reservation_id'] ?>"
                                                style="margin:0;"
                                            >
                                                <?= csrfInput() ?>
                                                <input
                                                    type="hidden"
                                                    name="reservation_id"
                                                    value="<?= (int) $reservation['reservation_id'] ?>"
                                                >

                                                <div class="admin-action-cell">
                                                    <select
                                                        name="new_status"
                                                        class="form-control"
                                                        required
                                                    >
                                                        <?php foreach ($statusOptions as $status): ?>
                                                            <option
                                                                value="<?= htmlspecialchars($status) ?>"
                                                                <?= selectedAdminOption($reservation['status'], $status) ?>
                                                            >
                                                                <?= htmlspecialchars(ucfirst($status)) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>

                                                    <button
                                                        type="submit"
                                                        name="action"
                                                        value="update_status"
                                                        class="btn btn-primary"
                                                        id="admin-update-btn-<?= (int) $reservation['reservation_id'] ?>"
                                                    >
                                                        Update
                                                    </button>
                                                </div>
                                            </form>
                                        <?php else: ?>
                                            <span style="color:var(--color-muted);">
                                                Locked
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-success">
                    No reservation found.
                </div>
            <?php endif; ?>
        </div>

    </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>