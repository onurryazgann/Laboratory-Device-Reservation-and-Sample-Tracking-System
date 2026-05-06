<?php

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/validation_helper.php';

$pageTitle = 'Profile';
$pageCss = 'profile.css';

$userId = getCurrentUserId();

$message = '';
$messageStatus = null;

function getCurrentUserProfile(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare("
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
            d.department_name
        FROM users u
        INNER JOIN roles r
            ON u.role_id = r.role_id
        LEFT JOIN student_profiles sp
            ON u.user_id = sp.user_id
        LEFT JOIN faculties f
            ON sp.faculty_id = f.faculty_id
        LEFT JOIN departments d
            ON sp.department_id = d.department_id
        WHERE u.user_id = :user_id
        LIMIT 1
    ");

    $stmt->execute([
        ':user_id' => $userId
    ]);

    $profile = $stmt->fetch();

    return $profile ?: null;
}

function formatProfileDateTime(?string $value): string
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

function profileRoleLabel(?string $roleName): string
{
    $roleName = trim((string) $roleName);

    if ($roleName === '') {
        return '-';
    }

    return ucfirst(str_replace('_', ' ', $roleName));
}

$profile = getCurrentUserProfile($pdo, (int) $userId);

if (!$profile) {
    http_response_code(404);
    die('Profile not found.');
}

$phone = $profile['phone'] ?? '';
$programType = $profile['program_type'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = cleanInput($_POST['phone'] ?? '');
    $programType = cleanInput($_POST['program_type'] ?? '');

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            UPDATE users
            SET phone = :phone
            WHERE user_id = :user_id
        ");

        $stmt->execute([
            ':phone' => $phone !== '' ? $phone : null,
            ':user_id' => (int) $userId
        ]);

        if ($profile['role_name'] === 'student') {
            $stmt = $pdo->prepare("
                UPDATE student_profiles
                SET program_type = :program_type
                WHERE user_id = :user_id
            ");

            $stmt->execute([
                ':program_type' => $programType !== '' ? $programType : null,
                ':user_id' => (int) $userId
            ]);
        }

        $pdo->commit();

        $message = 'Profile updated successfully.';
        $messageStatus = true;

        $profile = getCurrentUserProfile($pdo, (int) $userId);
        $phone = $profile['phone'] ?? '';
        $programType = $profile['program_type'] ?? '';

    } catch (Exception $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $message = DEBUG_MODE
            ? 'Profile update failed: ' . $e->getMessage()
            : 'Profile update failed.';

        $messageStatus = false;
    }
}

require_once __DIR__ . '/../includes/header.php';

?>

<section class="page-section profile-page">
    <div class="container">

        <!-- HERO -->
        <div class="card profile-hero-card" style="margin-bottom:32px;">
            <div class="profile-hero-content">

                <div>
                    <span class="badge badge-info">
                        Academic Identity
                    </span>

                    <h1 class="section-title" style="margin-bottom:8px; margin-top:16px;">
                        My Profile
                    </h1>

                    <p class="section-subtitle" style="margin-bottom:0;">
                        Manage your account information, student identity and academic profile settings.
                    </p>
                </div>

                <div class="profile-hero-actions">
                    <a href="dashboard.php" class="btn btn-primary">
                        Dashboard
                    </a>

                    <a href="my-reservations.php" class="btn btn-outline">
                        My Reservations
                    </a>
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

        <!-- PROFILE OVERVIEW -->
        <div class="profile-overview-grid" style="margin-bottom:32px;">

            <div class="card card-hover profile-overview-card">
                <span class="profile-overview-label">Member Since</span>

                <strong>
                    <?= htmlspecialchars(formatProfileDateTime($profile['created_at'] ?? null), ENT_QUOTES, 'UTF-8') ?>
                </strong>

                <p>Account creation date</p>
            </div>

            <div class="card card-hover profile-overview-card is-role">
                <span class="profile-overview-label">Role</span>

                <strong>
                    <?= htmlspecialchars(profileRoleLabel($profile['role_name'])) ?>
                </strong>

                <p>Current authorization level</p>
            </div>

            <div class="card card-hover profile-overview-card is-status">
                <span class="profile-overview-label">Account Status</span>

                <?php if ((int) $profile['is_active'] === 1): ?>
                    <strong>Active</strong>
                    <p>Your account can use the system.</p>
                <?php else: ?>
                    <strong>Passive</strong>
                    <p>Your account is currently inactive.</p>
                <?php endif; ?>
            </div>

        </div>

        <!-- MAIN GRID -->
        <div class="profile-main-grid">

            <!-- ACCOUNT INFORMATION -->
            <div class="card profile-section-card">

                <div class="profile-section-header">
                    <div>
                        <h2 style="margin-top:0; margin-bottom:8px;">
                            Account Information
                        </h2>

                        <p class="section-subtitle" style="margin-bottom:0;">
                            Core login and identity information.
                        </p>
                    </div>

                    <?php if ((int) $profile['is_active'] === 1): ?>
                        <span class="badge badge-success">Active</span>
                    <?php else: ?>
                        <span class="badge badge-warning">Passive</span>
                    <?php endif; ?>
                </div>

                <div class="profile-info-grid">

                    <div class="profile-info-row">
                        <span>Full Name</span>
                        <strong>
                            <?= htmlspecialchars($profile['first_name'] . ' ' . $profile['last_name']) ?>
                        </strong>
                    </div>

                    <div class="profile-info-row">
                        <span>Email</span>
                        <strong>
                            <?= htmlspecialchars($profile['email']) ?>
                        </strong>
                    </div>

                    <div class="profile-info-row">
                        <span>Phone</span>
                        <strong>
                            <?= htmlspecialchars($profile['phone'] ?? '-') ?>
                        </strong>
                    </div>

                    <div class="profile-info-row">
                        <span>Role</span>
                        <strong>
                            <?= htmlspecialchars(profileRoleLabel($profile['role_name'])) ?>
                        </strong>
                    </div>

                    <div class="profile-info-row">
                        <span>Created At</span>
                        <strong>
                            <?= htmlspecialchars(formatProfileDateTime($profile['created_at'] ?? null)) ?>
                        </strong>
                    </div>

                    <div class="profile-info-row">
                        <span>Updated At</span>
                        <strong>
                            <?= htmlspecialchars(formatProfileDateTime($profile['updated_at'] ?? null)) ?>
                        </strong>
                    </div>

                </div>

            </div>

            <!-- STUDENT INFORMATION -->
            <?php if ($profile['role_name'] === 'student'): ?>

                <div class="card profile-section-card">

                    <div class="profile-section-header">
                        <div>
                            <h2 style="margin-top:0; margin-bottom:8px;">
                                Student Information
                            </h2>

                            <p class="section-subtitle" style="margin-bottom:0;">
                                Academic profile and department information.
                            </p>
                        </div>

                        <span class="badge badge-info">
                            Student
                        </span>
                    </div>

                    <div class="profile-info-grid">

                        <div class="profile-info-row">
                            <span>Student No</span>
                            <strong>
                                <?= htmlspecialchars($profile['student_no'] ?? '-') ?>
                            </strong>
                        </div>

                        <div class="profile-info-row">
                            <span>Faculty</span>
                            <strong>
                                <?= htmlspecialchars($profile['faculty_name'] ?? '-') ?>
                            </strong>
                        </div>

                        <div class="profile-info-row">
                            <span>Department</span>
                            <strong>
                                <?= htmlspecialchars($profile['department_name'] ?? '-') ?>
                            </strong>
                        </div>

                        <div class="profile-info-row">
                            <span>Class Year</span>
                            <strong>
                                <?= $profile['class_year'] !== null ? (int) $profile['class_year'] : '-' ?>
                            </strong>
                        </div>

                        <div class="profile-info-row">
                            <span>Program Type</span>
                            <strong>
                                <?= htmlspecialchars($profile['program_type'] ?? '-') ?>
                            </strong>
                        </div>

                    </div>

                </div>

            <?php endif; ?>

        </div>

        <!-- QUICK ACTIONS -->
        <div class="card profile-actions-card" style="margin-top:32px; margin-bottom:32px;">

            <div class="profile-section-header">
                <div>
                    <h2 style="margin-top:0; margin-bottom:8px;">
                        Quick Actions
                    </h2>

                    <p class="section-subtitle" style="margin-bottom:0;">
                        Continue your academic reservation workflow.
                    </p>
                </div>

                <span class="badge badge-info">
                    Workflow
                </span>
            </div>

            <div class="profile-action-grid">

                <a href="labs.php" class="profile-action-item">
                    <span>01</span>

                    <div>
                        <strong>Browse Laboratories</strong>
                        <p>Explore laboratories and available stations.</p>
                    </div>
                </a>

                <a href="reserve.php" class="profile-action-item">
                    <span>02</span>

                    <div>
                        <strong>New Reservation</strong>
                        <p>Create a new reservation with availability check.</p>
                    </div>
                </a>

                <a href="my-reservations.php" class="profile-action-item">
                    <span>03</span>

                    <div>
                        <strong>My Reservations</strong>
                        <p>View, edit or cancel your reservation records.</p>
                    </div>
                </a>

            </div>

        </div>

        <!-- EDIT -->
        <div class="card profile-edit-card">

            <div class="profile-section-header">
                <div>
                    <h2 style="margin-top:0; margin-bottom:8px;">
                        Edit Profile
                    </h2>

                    <p class="section-subtitle" style="margin-bottom:0;">
                        You can update editable contact and program information.
                    </p>
                </div>

                <span class="badge badge-info">
                    Editable
                </span>
            </div>

            <form method="POST" action="">

                <div class="grid grid-2">

                    <div class="form-group">
                        <label for="phone" class="form-label">Phone</label>

                        <input
                            type="text"
                            id="phone"
                            name="phone"
                            class="form-control"
                            value="<?= htmlspecialchars($phone) ?>"
                            placeholder="Example: 0555 111 2233"
                        >

                        <small class="field-feedback">
                            Optional contact number.
                        </small>
                    </div>

                    <?php if ($profile['role_name'] === 'student'): ?>

                        <div class="form-group">
                            <label for="program_type" class="form-label">Program Type</label>

                            <input
                                type="text"
                                id="program_type"
                                name="program_type"
                                class="form-control"
                                value="<?= htmlspecialchars($programType) ?>"
                                placeholder="Example: 100% Turkish"
                            >

                            <small class="field-feedback">
                                Optional academic program detail.
                            </small>
                        </div>

                    <?php endif; ?>

                </div>

                <div class="profile-edit-actions">
                    <button type="submit" class="btn btn-primary">
                        Update Profile
                    </button>

                    <a href="dashboard.php" class="btn btn-outline">
                        Cancel
                    </a>
                </div>

            </form>

        </div>

    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>