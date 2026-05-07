<?php

require_once __DIR__ . '/../../includes/admin_check.php';
require_once __DIR__ . '/../../config/database.php';

$pageTitle = 'Admin Users';
$pageCss = 'admin-users.css';

$filters = [
    'q' => trim($_GET['q'] ?? ''),
    'role' => $_GET['role'] ?? '',
    'is_active' => $_GET['is_active'] ?? ''
];

$allowedRoles = ['admin', 'student'];
$allowedActiveValues = ['1', '0'];

if ($filters['role'] !== '' && !in_array($filters['role'], $allowedRoles, true)) {
    $filters['role'] = '';
}

if ($filters['is_active'] !== '' && !in_array($filters['is_active'], $allowedActiveValues, true)) {
    $filters['is_active'] = '';
}

$sql = "
    SELECT
        u.user_id,
        u.role_id,
        u.first_name,
        u.last_name,
        u.email,
        u.phone,
        u.is_active,
        u.created_at,
        u.updated_at,
        r.role_name,
        sp.student_no,
        sp.class_year,
        sp.program_type,
        f.faculty_name,
        d.department_name,
        COUNT(res.reservation_id) AS total_reservation_count,
        COALESCE(SUM(CASE WHEN res.status = 'active' THEN 1 ELSE 0 END), 0) AS active_reservation_count
    FROM users u
    INNER JOIN roles r
        ON u.role_id = r.role_id
    LEFT JOIN student_profiles sp
        ON u.user_id = sp.user_id
    LEFT JOIN faculties f
        ON sp.faculty_id = f.faculty_id
    LEFT JOIN departments d
        ON sp.department_id = d.department_id
    LEFT JOIN reservations res
        ON u.user_id = res.user_id
    WHERE 1 = 1
";

$params = [];

if ($filters['q'] !== '') {
    $sql .= "
        AND (
            u.first_name LIKE :search_first_name
            OR u.last_name LIKE :search_last_name
            OR CONCAT(u.first_name, ' ', u.last_name) LIKE :search_full_name
            OR u.email LIKE :search_email
            OR u.phone LIKE :search_phone
            OR sp.student_no LIKE :search_student_no
            OR f.faculty_name LIKE :search_faculty
            OR d.department_name LIKE :search_department
        )
    ";

    $searchValue = '%' . $filters['q'] . '%';

    $params[':search_first_name'] = $searchValue;
    $params[':search_last_name'] = $searchValue;
    $params[':search_full_name'] = $searchValue;
    $params[':search_email'] = $searchValue;
    $params[':search_phone'] = $searchValue;
    $params[':search_student_no'] = $searchValue;
    $params[':search_faculty'] = $searchValue;
    $params[':search_department'] = $searchValue;
}

if ($filters['role'] !== '') {
    $sql .= " AND r.role_name = :role_name";
    $params[':role_name'] = $filters['role'];
}

if ($filters['is_active'] !== '') {
    $sql .= " AND u.is_active = :is_active";
    $params[':is_active'] = (int) $filters['is_active'];
}

$sql .= "
    GROUP BY
        u.user_id,
        u.role_id,
        u.first_name,
        u.last_name,
        u.email,
        u.phone,
        u.is_active,
        u.created_at,
        u.updated_at,
        r.role_name,
        sp.student_no,
        sp.class_year,
        sp.program_type,
        f.faculty_name,
        d.department_name
    ORDER BY u.created_at DESC, u.user_id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

function selectedUserAdminOption($currentValue, $expectedValue): string
{
    return (string) $currentValue === (string) $expectedValue
        ? 'selected'
        : '';
}

function adminUserInitials(?string $firstName, ?string $lastName): string
{
    $first = trim((string) $firstName);
    $last = trim((string) $lastName);

    $initialFirst = $first !== '' ? mb_substr($first, 0, 1) : '';
    $initialLast = $last !== '' ? mb_substr($last, 0, 1) : '';

    $initials = $initialFirst . $initialLast;

    return $initials !== '' ? mb_strtoupper($initials) : 'U';
}

function formatAdminUserDate(?string $value): string
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

function formatAdminUserTime(?string $value): string
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

function adminUserRoleClass(?string $roleName): string
{
    return $roleName === 'admin' ? 'is-warning' : 'is-info';
}

function adminUserStatusClass($isActive): string
{
    return (int) $isActive === 1 ? 'is-success' : 'is-warning';
}

$totalCount = count($users);
$adminCount = 0;
$studentCount = 0;
$activeCount = 0;
$inactiveCount = 0;

foreach ($users as $user) {
    if ($user['role_name'] === 'admin') {
        $adminCount++;
    }

    if ($user['role_name'] === 'student') {
        $studentCount++;
    }

    if ((int) $user['is_active'] === 1) {
        $activeCount++;
    } else {
        $inactiveCount++;
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
$pagedUsers = array_slice($users, $offset, $itemsPerPage);

$paginationFilters = array_filter($filters, function ($value) {
    return $value !== '' && $value !== null;
});

require_once __DIR__ . '/../../includes/header.php';

?>

<section class="adminusers-page">

<!-- HERO -->
<section class="adminusers-hero reveal-on-scroll" data-adminusers-tilt-card>

    <div class="adminusers-hero-content">

        <span class="adminusers-eyebrow">
            User Governance
        </span>

        <h1>
            User Governance Center
        </h1>

        <p>
            Monitor users, review student profile details, check reservation activity
            and filter account records from one clean admin screen.
        </p>

        <div class="adminusers-hero-actions">
            <a href="#user-directory" class="adminusers-btn adminusers-btn-primary">
                View User Records
            </a>

            <a href="reservations.php" class="adminusers-btn adminusers-btn-light">
                Manage Reservations
            </a>
        </div>

    </div>

    <div class="adminusers-hero-visual">

        <div class="adminusers-mini-panel">

            <div class="adminusers-mini-header">
                <span></span>
                <span></span>
                <span></span>
            </div>

            <div class="adminusers-mini-body">

                <div class="adminusers-mini-title">
                    <div>
                        <small>Current Overview</small>
                        <strong>User Records</strong>
                    </div>

                    <span>Live</span>
                </div>

                <div class="adminusers-mini-list">

                    <div class="adminusers-mini-item is-active">
                        <span>01</span>

                        <div>
                            <strong>Total Users</strong>
                            <small><?= (int) $totalCount ?> matching current filters</small>
                        </div>
                    </div>

                    <div class="adminusers-mini-item">
                        <span>02</span>

                        <div>
                            <strong>Active Accounts</strong>
                            <small><?= (int) $activeCount ?> active user records</small>
                        </div>
                    </div>

                    <div class="adminusers-mini-item">
                        <span>03</span>

                        <div>
                            <strong>Students / Admins</strong>
                            <small><?= (int) $studentCount ?> students, <?= (int) $adminCount ?> admins</small>
                        </div>
                    </div>

                </div>

            </div>

        </div>

        <div class="adminusers-floating-chip adminusers-chip-one">
            <span>✓</span>
            <?= (int) $activeCount ?> Active
        </div>

        <div class="adminusers-floating-chip adminusers-chip-two">
            <span>⌕</span>
            Filter
        </div>

        <div class="adminusers-floating-chip adminusers-chip-three">
            <span>↗</span>
            Review
        </div>

    </div>

</section>

    <!-- KPI -->
    <section class="adminusers-kpi-grid">
        <article class="adminusers-kpi-card reveal-on-scroll">
            <span>Total Shown</span>
            <strong><?= (int) $totalCount ?></strong>
            <p>Users matching current filters.</p>
        </article>

        <article class="adminusers-kpi-card reveal-on-scroll">
            <span>Students</span>
            <strong><?= (int) $studentCount ?></strong>
            <p>Registered student accounts.</p>
        </article>

        <article class="adminusers-kpi-card reveal-on-scroll">
            <span>Admins</span>
            <strong><?= (int) $adminCount ?></strong>
            <p>Administrative users.</p>
        </article>

        <article class="adminusers-kpi-card reveal-on-scroll">
            <span>Active</span>
            <strong><?= (int) $activeCount ?></strong>
            <p>Currently active accounts.</p>
        </article>
    </section>

    <!-- FILTERS -->
    <section class="adminusers-panel reveal-on-scroll">
        <div class="adminusers-section-header">
            <div>
                <span class="adminusers-section-label">
                    Filters
                </span>

                <h2>
                    Search user records.
                </h2>

                <p>
                    Filter users by name, email, phone, student number, faculty, department,
                    role or account status.
                </p>
            </div>

            <span class="adminusers-status-badge is-info">
                <?= (int) $totalCount ?> Result<?= $totalCount === 1 ? '' : 's' ?>
            </span>
        </div>

        <form method="GET" action="" class="adminusers-filter-form">
            <div class="adminusers-filter-grid">
                <div class="adminusers-form-group">
                    <label for="q">Search</label>
                    <input
                        type="text"
                        id="q"
                        name="q"
                        value="<?= htmlspecialchars($filters['q'], ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="Name, email, phone, student no, faculty or department"
                    >
                </div>

                <div class="adminusers-form-group">
                    <label for="role">Role</label>
                    <select id="role" name="role">
                        <option value="">All roles</option>

                        <?php foreach ($allowedRoles as $role): ?>
                            <option
                                value="<?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?>"
                                <?= selectedUserAdminOption($filters['role'], $role) ?>
                            >
                                <?= htmlspecialchars(ucfirst($role), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="adminusers-form-group">
                    <label for="is_active">Account Status</label>
                    <select id="is_active" name="is_active">
                        <option value="">All accounts</option>
                        <option value="1" <?= selectedUserAdminOption($filters['is_active'], '1') ?>>Active</option>
                        <option value="0" <?= selectedUserAdminOption($filters['is_active'], '0') ?>>Inactive</option>
                    </select>
                </div>
            </div>

            <div class="adminusers-form-actions">
                <button type="submit" class="adminusers-btn adminusers-btn-primary">
                    Apply Filters
                </button>

                <a href="users.php" class="adminusers-btn adminusers-btn-outline">
                    Clear Filters
                </a>
            </div>
        </form>
    </section>

    <!-- USER DIRECTORY -->
    <section id="user-directory" class="adminusers-panel reveal-on-scroll">
        <div class="adminusers-section-header">
            <div>
                <span class="adminusers-section-label">
                    User Directory
                </span>

                <h2>
                    Manage user records.
                </h2>

                <p>
                    Each page shows 8 user records. Scroll horizontally to review all details.
                </p>
            </div>

            <span class="adminusers-status-badge is-info">
                Page <?= (int) $currentPage ?> / <?= (int) $totalPages ?>
            </span>
        </div>

        <?php if (count($users) > 0): ?>

            <div class="adminusers-list-top">
                <div>
                    <strong>User Records</strong>
                    <span>
                        Showing <?= (int) ($offset + 1) ?> -
                        <?= (int) min($offset + $itemsPerPage, $totalCount) ?>
                        of <?= (int) $totalCount ?> records
                    </span>
                </div>

                <span>
                    <?= (int) $activeCount ?> Active / <?= (int) $inactiveCount ?> Inactive
                </span>
            </div>

            <div class="adminusers-list-scroll">
                <div class="adminusers-list">
                    <?php foreach ($pagedUsers as $user): ?>
                        <article class="adminusers-row reveal-on-scroll">
                            <div class="adminusers-user-cell">
                                <span class="adminusers-user-avatar">
                                    <?= htmlspecialchars(adminUserInitials($user['first_name'], $user['last_name']), ENT_QUOTES, 'UTF-8') ?>
                                </span>

                                <div>
                                    <strong>
                                        <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name'], ENT_QUOTES, 'UTF-8') ?>
                                    </strong>
                                    <small>
                                        <?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?>
                                    </small>
                                </div>
                            </div>

                            <div class="adminusers-info-cell">
                                <span>Role</span>
                                <strong>
                                    <span class="adminusers-status-badge <?= adminUserRoleClass($user['role_name']) ?>">
                                        <?= htmlspecialchars(ucfirst($user['role_name']), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </strong>
                            </div>

                            <div class="adminusers-info-cell">
                                <span>Phone</span>
                                <strong>
                                    <?= htmlspecialchars($user['phone'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                </strong>
                                <small>Contact number</small>
                            </div>

                            <div class="adminusers-info-cell">
                                <span>Student No</span>
                                <strong>
                                    <?= htmlspecialchars($user['student_no'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                </strong>
                                <small>
                                    <?= $user['class_year'] !== null ? 'Class ' . (int) $user['class_year'] : 'Class -' ?>
                                </small>
                            </div>

                            <div class="adminusers-info-cell">
                                <span>Faculty</span>
                                <strong>
                                    <?= htmlspecialchars($user['faculty_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                </strong>
                                <small>
                                    <?= htmlspecialchars($user['department_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                </small>
                            </div>

                            <div class="adminusers-info-cell">
                                <span>Program</span>
                                <strong>
                                    <?= htmlspecialchars($user['program_type'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                </strong>
                                <small>Education type</small>
                            </div>

                            <div class="adminusers-reservation-cell">
                                <div>
                                    <span>Total</span>
                                    <strong><?= (int) $user['total_reservation_count'] ?></strong>
                                </div>

                                <div>
                                    <span>Active</span>
                                    <strong><?= (int) $user['active_reservation_count'] ?></strong>
                                </div>
                            </div>

                            <div class="adminusers-info-cell">
                                <span>Status</span>
                                <strong>
                                    <span class="adminusers-status-badge <?= adminUserStatusClass($user['is_active']) ?>">
                                        <?= (int) $user['is_active'] === 1 ? 'Active' : 'Inactive' ?>
                                    </span>
                                </strong>
                            </div>

                            <div class="adminusers-info-cell">
                                <span>Created</span>
                                <strong>
                                    <?= htmlspecialchars(formatAdminUserDate($user['created_at']), ENT_QUOTES, 'UTF-8') ?>
                                </strong>
                                <small>
                                    <?= htmlspecialchars(formatAdminUserTime($user['created_at']), ENT_QUOTES, 'UTF-8') ?>
                                </small>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="adminusers-pagination">
                    <?php if ($currentPage > 1): ?>
                        <a
                            href="users.php?<?= htmlspecialchars(http_build_query(array_merge($paginationFilters, ['page' => $currentPage - 1])), ENT_QUOTES, 'UTF-8') ?>"
                            class="adminusers-page-btn"
                        >
                            ‹
                        </a>
                    <?php else: ?>
                        <span class="adminusers-page-btn is-disabled">‹</span>
                    <?php endif; ?>

                    <?php for ($page = 1; $page <= $totalPages; $page++): ?>
                        <a
                            href="users.php?<?= htmlspecialchars(http_build_query(array_merge($paginationFilters, ['page' => $page])), ENT_QUOTES, 'UTF-8') ?>"
                            class="adminusers-page-btn <?= $page === $currentPage ? 'is-active' : '' ?>"
                        >
                            <?= (int) $page ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($currentPage < $totalPages): ?>
                        <a
                            href="users.php?<?= htmlspecialchars(http_build_query(array_merge($paginationFilters, ['page' => $currentPage + 1])), ENT_QUOTES, 'UTF-8') ?>"
                            class="adminusers-page-btn"
                        >
                            ›
                        </a>
                    <?php else: ?>
                        <span class="adminusers-page-btn is-disabled">›</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        <?php else: ?>

            <div class="adminusers-empty-state">
                <span class="adminusers-status-badge is-success">
                    No User
                </span>

                <h3>
                    No user found.
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
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>