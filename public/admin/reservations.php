<?php

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/lab_helper.php';
require_once __DIR__ . '/../../helpers/reservation_helper.php';

$adminUserId = getCurrentUserId();

syncExpiredReservations($pdo, (int) $adminUserId);

$pageTitle = 'Reservation Management';
$pageCss = 'admin-reservations.css';
$bodyClass = 'page-admin-reservations';

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

function formatAdminReservationDate(?string $value): string
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

function formatAdminReservationTime(?string $value): string
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

function adminReservationStatusClass(string $status): string
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

    return 'is-neutral';
}

function adminReservationInitials(?string $name): string
{
    $name = trim((string) $name);

    if ($name === '') {
        return 'U';
    }

    $parts = preg_split('/\s+/', $name);
    $first = mb_substr($parts[0] ?? '', 0, 1);
    $last = mb_substr($parts[count($parts) - 1] ?? '', 0, 1);

    return mb_strtoupper($first . $last);
}

function canAdminUpdateReservation(array $reservation): bool
{
    return $reservation['status'] === 'active';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
$itemsPerPage = 8;

$currentPage = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
$currentPage = $currentPage && $currentPage > 0 ? $currentPage : 1;

$totalPages = (int) ceil($totalCount / $itemsPerPage);
$totalPages = max($totalPages, 1);

if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
}

$offset = ($currentPage - 1) * $itemsPerPage;
$pagedReservations = array_slice($reservations, $offset, $itemsPerPage);

$paginationFilters = array_filter($filters, function ($value) {
    return $value !== '' && $value !== null;
});
$itemsPerPage = 8;

$currentPage = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
$currentPage = $currentPage && $currentPage > 0 ? $currentPage : 1;

$totalPages = (int) ceil($totalCount / $itemsPerPage);
$totalPages = max($totalPages, 1);

if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
}

$offset = ($currentPage - 1) * $itemsPerPage;

$pagedReservations = array_slice($reservations, $offset, $itemsPerPage);

$paginationFilters = array_filter($filters, function ($value) {
    return $value !== '' && $value !== null;
});

$itemsPerPage = 8;

$currentPage = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
$currentPage = $currentPage && $currentPage > 0 ? $currentPage : 1;

$totalPages = (int) ceil($totalCount / $itemsPerPage);
$totalPages = max($totalPages, 1);

if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
}

$offset = ($currentPage - 1) * $itemsPerPage;

$pagedReservations = array_slice($reservations, $offset, $itemsPerPage);

$paginationFilters = array_filter($filters, function ($value) {
    return $value !== '' && $value !== null;
});

require_once __DIR__ . '/../../includes/header.php';

?>

<section class="adminres-page">

    <!-- HERO -->
    <section class="adminres-hero reveal-on-scroll" data-adminres-tilt-card>

        <div class="adminres-hero-content">

            <span class="adminres-eyebrow">
                Reservation Governance
            </span>

            <h1>
                Manage reservation activity across the whole system.
            </h1>

            <p>
                Monitor all reservations, filter operational records, review user and station details,
                and update active reservation lifecycle states.
            </p>

            <div class="adminres-hero-actions">
                <a href="reservations.php?status=active" class="adminres-btn adminres-btn-primary">
                    View Active Reservations
                </a>

                <a href="../reserve.php" class="adminres-btn adminres-btn-light">
                    Create Reservation
                </a>
            </div>

        </div>

        <div class="adminres-hero-visual">

            <div class="adminres-mini-panel">

                <div class="adminres-mini-header">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>

                <div class="adminres-mini-body">

                    <div class="adminres-mini-title">
                        <div>
                            <small>System Records</small>
                            <strong>Reservation Control</strong>
                        </div>

                        <span>Admin</span>
                    </div>

                    <div class="adminres-mini-list">

                        <div class="adminres-mini-item is-active">
                            <span>01</span>
                            <div>
                                <strong>Filter Records</strong>
                                <small>Search by user, laboratory, station or date range.</small>
                            </div>
                        </div>

                        <div class="adminres-mini-item">
                            <span>02</span>
                            <div>
                                <strong>Review Details</strong>
                                <small>Inspect schedule, purpose and status information.</small>
                            </div>
                        </div>

                        <div class="adminres-mini-item">
                            <span>03</span>
                            <div>
                                <strong>Update Status</strong>
                                <small>Change lifecycle state for active reservations.</small>
                            </div>
                        </div>

                    </div>

                </div>

            </div>

            <div class="adminres-floating-chip adminres-chip-one">
                <span>✓</span>
                Active <?= (int) $activeCount ?>
            </div>

            <div class="adminres-floating-chip adminres-chip-two">
                <span>⌕</span>
                Filter
            </div>

            <div class="adminres-floating-chip adminres-chip-three">
                <span>↗</span>
                Update
            </div>

        </div>

    </section>

    <!-- MESSAGE -->
    <?php if ($message !== ''): ?>
        <section class="adminres-alert <?= $messageStatus ? 'is-success' : 'is-error' ?> reveal-on-scroll">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        </section>
    <?php endif; ?>

    <!-- KPI -->
    <section class="adminres-kpi-grid">

        <article class="adminres-kpi-card reveal-on-scroll">
            <span>Total Shown</span>
            <strong><?= (int) $totalCount ?></strong>
            <p>Reservations matching current filters.</p>
        </article>

        <article class="adminres-kpi-card is-active reveal-on-scroll">
            <span>Active</span>
            <strong><?= (int) $activeCount ?></strong>
            <p>Reservations currently active.</p>
        </article>

        <article class="adminres-kpi-card is-cancelled reveal-on-scroll">
            <span>Cancelled</span>
            <strong><?= (int) $cancelledCount ?></strong>
            <p>Reservations cancelled by user or admin.</p>
        </article>

        <article class="adminres-kpi-card is-completed reveal-on-scroll">
            <span>Completed</span>
            <strong><?= (int) $completedCount ?></strong>
            <p>Reservations completed after usage period.</p>
        </article>

    </section>

    <!-- FILTERS -->
    <section class="adminres-panel reveal-on-scroll">

        <div class="adminres-section-header">
            <div>
                <span class="adminres-section-label">
                    Filters
                </span>

                <h2>
                    Search reservation records.
                </h2>

                <p>
                    Use filters to narrow down reservations by text, status, laboratory or date interval.
                </p>
            </div>

            <span class="adminres-status-badge is-info">
                <?= (int) $totalCount ?> Result<?= $totalCount === 1 ? '' : 's' ?>
            </span>
        </div>

        <form method="GET" action="" class="adminres-filter-form">

            <div class="adminres-filter-grid">

                <div class="adminres-form-group">
                    <label for="q">Search</label>

                    <input
                        type="text"
                        id="q"
                        name="q"
                        value="<?= htmlspecialchars($filters['q'], ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="User, email, lab, station, purpose..."
                    >
                </div>

                <div class="adminres-form-group">
                    <label for="status">Status</label>

                    <select id="status" name="status">
                        <option value="">All statuses</option>

                        <?php foreach ($statusOptions as $status): ?>
                            <option
                                value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"
                                <?= selectedAdminOption($filters['status'], $status) ?>
                            >
                                <?= htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="adminres-form-group">
                    <label for="lab_id">Laboratory</label>

                    <select id="lab_id" name="lab_id">
                        <option value="">All laboratories</option>

                        <?php foreach ($labs as $lab): ?>
                            <option
                                value="<?= (int) $lab['lab_id'] ?>"
                                <?= selectedAdminOption($filters['lab_id'], $lab['lab_id']) ?>
                            >
                                <?= htmlspecialchars($lab['lab_code'] . ' - ' . $lab['lab_name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

            </div>

            <div class="adminres-filter-grid is-two">

                <div class="adminres-form-group">
                    <label for="date_from">Date From</label>

                    <input
                        type="date"
                        id="date_from"
                        name="date_from"
                        value="<?= htmlspecialchars($filters['date_from'], ENT_QUOTES, 'UTF-8') ?>"
                    >
                </div>

                <div class="adminres-form-group">
                    <label for="date_to">Date To</label>

                    <input
                        type="date"
                        id="date_to"
                        name="date_to"
                        value="<?= htmlspecialchars($filters['date_to'], ENT_QUOTES, 'UTF-8') ?>"
                    >
                </div>

            </div>

            <div class="adminres-form-actions">
                <button type="submit" class="adminres-btn adminres-btn-primary">
                    Apply Filters
                </button>

                <a href="reservations.php" class="adminres-btn adminres-btn-outline">
                    Clear Filters
                </a>
            </div>

        </form>

    </section>

    <!-- LIST -->
    <!-- LIST -->
<section class="adminres-panel reveal-on-scroll">

<div class="adminres-section-header">
    <div>
        <span class="adminres-section-label">
            Reservation List
        </span>

        <h2>
            Recent reservation records.
        </h2>

        <p>
            Review reservation activity with user, laboratory, station and schedule details.
        </p>
    </div>

    <span class="adminres-status-badge is-info">
        <?= (int) $totalCount ?> Record<?= $totalCount === 1 ? '' : 's' ?>
    </span>
</div>

<?php if (count($reservations) > 0): ?>

    <div class="adminres-list-top">
        <div>
            <strong>Reservation Records</strong>
            <span>
                Showing <?= (int) ($offset + 1) ?> -
                <?= (int) min($offset + $itemsPerPage, $totalCount) ?>
                of <?= (int) $totalCount ?> records
            </span>
        </div>

        <span>
            Page <?= (int) $currentPage ?> / <?= (int) $totalPages ?>
        </span>
    </div>

    <div class="adminres-list-scroll">

        <div class="adminres-list adminres-dashboard-like-list">

            <?php foreach ($pagedReservations as $reservation): ?>
                <?php
                $status = $reservation['status'];
                $canUpdate = canAdminUpdateReservation($reservation);
                ?>

                <article class="adminres-row adminres-dashboard-like-row reveal-on-scroll">

                    <div class="adminres-main-cell adminres-main-cell-no-id">

                        <div class="adminres-user-cell">
                            <span class="adminres-user-avatar">
                                <?= htmlspecialchars(adminReservationInitials($reservation['user_full_name']), ENT_QUOTES, 'UTF-8') ?>
                            </span>

                            <div>
                                <strong>
                                    <?= htmlspecialchars($reservation['user_full_name'], ENT_QUOTES, 'UTF-8') ?>
                                </strong>

                                <small>
                                    <?= htmlspecialchars($reservation['user_email'] ?? 'Reservation owner', ENT_QUOTES, 'UTF-8') ?>
                                </small>
                            </div>
                        </div>

                    </div>

                    <div class="adminres-info-cell">
                        <span>Laboratory</span>

                        <strong>
                            <?= htmlspecialchars($reservation['lab_name'], ENT_QUOTES, 'UTF-8') ?>
                        </strong>

                        <small>
                            <?= htmlspecialchars($reservation['lab_code'], ENT_QUOTES, 'UTF-8') ?>
                        </small>
                    </div>

                    <div class="adminres-info-cell">
                        <span>Station</span>

                        <strong>
                            <?= htmlspecialchars($reservation['station_code'], ENT_QUOTES, 'UTF-8') ?>
                        </strong>

                        <small>
                            <?= htmlspecialchars($reservation['station_name'], ENT_QUOTES, 'UTF-8') ?>
                        </small>
                    </div>

                    <div class="adminres-time-cell">

                        <div>
                            <span>Start</span>

                            <strong>
                                <?= htmlspecialchars(formatAdminReservationDate($reservation['start_time']), ENT_QUOTES, 'UTF-8') ?>
                            </strong>

                            <small>
                                <?= htmlspecialchars(formatAdminReservationTime($reservation['start_time']), ENT_QUOTES, 'UTF-8') ?>
                            </small>
                        </div>

                        <div>
                            <span>End</span>

                            <strong>
                                <?= htmlspecialchars(formatAdminReservationDate($reservation['end_time']), ENT_QUOTES, 'UTF-8') ?>
                            </strong>

                            <small>
                                <?= htmlspecialchars(formatAdminReservationTime($reservation['end_time']), ENT_QUOTES, 'UTF-8') ?>
                            </small>
                        </div>

                    </div>

                    <div class="adminres-purpose-cell">
                        <span>Purpose</span>

                        <strong title="<?= htmlspecialchars($reservation['purpose'] ?? '-', ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($reservation['purpose'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                        </strong>
                    </div>

                    <div class="adminres-status-cell">
                        <span class="adminres-status-badge <?= adminReservationStatusClass($status) ?>">
                            <?= htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>

                    <div class="adminres-action-cell">

                        <?php if ($canUpdate): ?>
                            <form
                                method="POST"
                                action="reservations.php?<?= htmlspecialchars(http_build_query(array_merge($paginationFilters, ['page' => $currentPage])), ENT_QUOTES, 'UTF-8') ?>"
                                onsubmit="return confirm('Are you sure you want to update this reservation status?');"
                            >
                                <input
                                    type="hidden"
                                    name="reservation_id"
                                    value="<?= (int) $reservation['reservation_id'] ?>"
                                >

                                <select name="new_status" required>
                                    <?php foreach ($statusOptions as $statusOption): ?>
                                        <option
                                            value="<?= htmlspecialchars($statusOption, ENT_QUOTES, 'UTF-8') ?>"
                                            <?= selectedAdminOption($reservation['status'], $statusOption) ?>
                                        >
                                            <?= htmlspecialchars(ucfirst($statusOption), ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <button
                                    type="submit"
                                    name="action"
                                    value="update_status"
                                    class="adminres-small-btn"
                                >
                                    Update
                                </button>
                            </form>
                        <?php else: ?>
                            <span class="adminres-locked-label">
                                Locked
                            </span>
                        <?php endif; ?>

                    </div>

                </article>

            <?php endforeach; ?>

        </div>

    </div>

    <?php if ($totalPages > 1): ?>

        <div class="adminres-pagination">

            <?php if ($currentPage > 1): ?>
                <a
                    href="reservations.php?<?= htmlspecialchars(http_build_query(array_merge($paginationFilters, ['page' => $currentPage - 1])), ENT_QUOTES, 'UTF-8') ?>"
                    class="adminres-page-btn"
                >
                    ‹
                </a>
            <?php else: ?>
                <span class="adminres-page-btn is-disabled">
                    ‹
                </span>
            <?php endif; ?>

            <?php for ($page = 1; $page <= $totalPages; $page++): ?>
                <a
                    href="reservations.php?<?= htmlspecialchars(http_build_query(array_merge($paginationFilters, ['page' => $page])), ENT_QUOTES, 'UTF-8') ?>"
                    class="adminres-page-btn <?= $page === $currentPage ? 'is-active' : '' ?>"
                >
                    <?= (int) $page ?>
                </a>
            <?php endfor; ?>

            <?php if ($currentPage < $totalPages): ?>
                <a
                    href="reservations.php?<?= htmlspecialchars(http_build_query(array_merge($paginationFilters, ['page' => $currentPage + 1])), ENT_QUOTES, 'UTF-8') ?>"
                    class="adminres-page-btn"
                >
                    ›
                </a>
            <?php else: ?>
                <span class="adminres-page-btn is-disabled">
                    ›
                </span>
            <?php endif; ?>

        </div>

    <?php endif; ?>

<?php else: ?>

    <div class="adminres-empty-state">
        <span class="adminres-status-badge is-success">
            No Reservation
        </span>

        <h3>
            No reservation found.
        </h3>

        <p>
            Try changing filters or clearing the search conditions.
        </p>
    </div>

<?php endif; ?>

</section>

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

    const adminReservationTiltCard = document.querySelector('[data-adminres-tilt-card]');

    if (adminReservationTiltCard) {
        adminReservationTiltCard.addEventListener('pointermove', function (event) {
            const rect = adminReservationTiltCard.getBoundingClientRect();

            const x = event.clientX - rect.left;
            const y = event.clientY - rect.top;

            const rotateY = ((x / rect.width) - 0.5) * 5;
            const rotateX = ((y / rect.height) - 0.5) * -5;

            adminReservationTiltCard.style.transform =
                'perspective(1200px) rotateX(' + rotateX + 'deg) rotateY(' + rotateY + 'deg)';
        });

        adminReservationTiltCard.addEventListener('pointerleave', function () {
            adminReservationTiltCard.style.transform =
                'perspective(1200px) rotateX(0deg) rotateY(0deg)';
        });
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>