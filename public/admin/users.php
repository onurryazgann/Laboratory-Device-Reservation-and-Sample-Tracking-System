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

require_once __DIR__ . '/../../includes/header.php';

?>

<section class="page-section">
    <div class="container">

        <!-- HERO -->
        <div class="admin-card">

            <div class="admin-page-header">

                <div>
                    <h1 class="admin-page-title">
                        User Governance Center
                    </h1>

                    <p class="section-subtitle">
                        Monitor user ecosystem, manage identity visibility,
                        and oversee account lifecycle across the system.
                    </p>
                </div>

            </div>

        </div>

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
                            placeholder="Name, email, phone, student no, faculty or department"
                        >
                    </div>

                    <div class="form-group">
                        <label for="role" class="form-label">Role</label>

                        <select
                            id="role"
                            name="role"
                            class="form-control"
                        >
                            <option value="">All roles</option>

                            <?php foreach ($allowedRoles as $role): ?>
                                <option
                                    value="<?= htmlspecialchars($role) ?>"
                                    <?= selectedUserAdminOption($filters['role'], $role) ?>
                                >
                                    <?= htmlspecialchars(ucfirst($role)) ?>
                                </option>
                            <?php endforeach; ?>

                        </select>
                    </div>

                    <div class="form-group">
                        <label for="is_active" class="form-label">
                            Account Status
                        </label>

                        <select
                            id="is_active"
                            name="is_active"
                            class="form-control"
                        >
                            <option value="">All accounts</option>

                            <option
                                value="1"
                                <?= selectedUserAdminOption($filters['is_active'], '1') ?>
                            >
                                Active
                            </option>

                            <option
                                value="0"
                                <?= selectedUserAdminOption($filters['is_active'], '0') ?>
                            >
                                Inactive
                            </option>

                        </select>
                    </div>

                </div>

                <div class="admin-actions">

                    <button type="submit" class="btn btn-primary">
                        Apply Filters
                    </button>

                    <a href="users.php" class="btn btn-outline">
                        Clear Filters
                    </a>

                </div>

            </form>

        </div>

        <!-- SUMMARY -->
        <div class="admin-card">

            <h2>Results Summary</h2>

            <p style="margin-bottom:0;">
                Total users shown:
                <strong><?= count($users) ?></strong>
            </p>

        </div>

        <!-- TABLE -->
        <div class="admin-card">

            <h2>User Directory</h2>

            <?php if (count($users) > 0): ?>

                <div class="table-wrapper admin-table-wrapper">

                    <table class="table">

                        <thead>
                            <tr>
                                <th>Role</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Student No</th>
                                <th>Faculty</th>
                                <th>Department</th>
                                <th>Class</th>
                                <th>Program</th>
                                <th>Status</th>
                                <th>Total Reservations</th>
                                <th>Active Reservations</th>
                                <th>Created At</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($users as $user): ?>

                                <tr>

                                    <td>

                                        <?php if ($user['role_name'] === 'admin'): ?>
                                            <span class="badge badge-warning">
                                                Admin
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-info">
                                                Student
                                            </span>
                                        <?php endif; ?>

                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $user['first_name'] . ' ' . $user['last_name']
                                        ) ?>
                                    </td>

                                    <td><?= htmlspecialchars($user['email']) ?></td>

                                    <td><?= htmlspecialchars($user['phone'] ?? '-') ?></td>

                                    <td><?= htmlspecialchars($user['student_no'] ?? '-') ?></td>

                                    <td><?= htmlspecialchars($user['faculty_name'] ?? '-') ?></td>

                                    <td><?= htmlspecialchars($user['department_name'] ?? '-') ?></td>

                                    <td>
                                        <?= $user['class_year'] !== null
                                            ? (int) $user['class_year']
                                            : '-' ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($user['program_type'] ?? '-') ?>
                                    </td>

                                    <td>

                                        <?php if ((int) $user['is_active'] === 1): ?>
                                            <span class="badge badge-success">
                                                Active
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">
                                                Inactive
                                            </span>
                                        <?php endif; ?>

                                    </td>

                                    <td>
                                        <?= (int) $user['total_reservation_count'] ?>
                                    </td>

                                    <td>
                                        <span class="badge badge-info">
                                            <?= (int) $user['active_reservation_count'] ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($user['created_at']) ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="alert alert-success">
                    No user found.
                </div>

            <?php endif; ?>

        </div>

    </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>