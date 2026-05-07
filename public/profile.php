<?php

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/validation_helper.php';
require_once __DIR__ . '/../includes/csrf.php';

$pageTitle = 'Profile';
$pageCss = 'profile.css';
$bodyClass = 'page-profile';

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

function profileStatusClass(int $isActive): string
{
    return $isActive === 1 ? 'is-success' : 'is-warning';
}

$profile = getCurrentUserProfile($pdo, (int) $userId);

if (!$profile) {
    http_response_code(404);
    die('Profile not found.');
}

$phone = $profile['phone'] ?? '';
$programType = $profile['program_type'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();

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

$fullName = trim($profile['first_name'] . ' ' . $profile['last_name']);
$roleLabel = profileRoleLabel($profile['role_name']);
$isActive = (int) $profile['is_active'] === 1;

require_once __DIR__ . '/../includes/header.php';

?>

<section class="profile-page">

    <!-- HERO -->
    <section class="profile-hero" data-profile-tilt-card>

        <div class="profile-hero-content">

            <span class="profile-eyebrow">
                Academic Identity
            </span>

            <h1>
                Manage your profile and academic information.
            </h1>

            <p>
                Review your account details, student information and editable contact fields
                from one clean profile workspace.
            </p>

            <div class="profile-hero-actions">
                <a href="dashboard.php" class="profile-btn profile-btn-primary">
                    Dashboard
                </a>

                <a href="my-reservations.php" class="profile-btn profile-btn-light">
                    My Reservations
                </a>
            </div>

        </div>

        <div class="profile-hero-visual">

            <div class="profile-mini-panel">

                <div class="profile-mini-header">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>

                <div class="profile-mini-body">

                    <div class="profile-user-avatar">
                        <?= htmlspecialchars(mb_substr($profile['first_name'], 0, 1) . mb_substr($profile['last_name'], 0, 1), ENT_QUOTES, 'UTF-8') ?>
                    </div>

                    <h3>
                        <?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?>
                    </h3>

                    <p>
                        <?= htmlspecialchars($profile['email'], ENT_QUOTES, 'UTF-8') ?>
                    </p>

                    <div class="profile-mini-list">

                        <div class="profile-mini-item is-active">
                            <span>01</span>
                            <div>
                                <strong>Role</strong>
                                <small><?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?></small>
                            </div>
                        </div>

                        <div class="profile-mini-item">
                            <span>02</span>
                            <div>
                                <strong>Status</strong>
                                <small><?= $isActive ? 'Active account' : 'Passive account' ?></small>
                            </div>
                        </div>

                        <div class="profile-mini-item">
                            <span>03</span>
                            <div>
                                <strong>Profile Update</strong>
                                <small>Phone and program information can be updated.</small>
                            </div>
                        </div>

                    </div>

                </div>

            </div>

            <div class="profile-floating-chip profile-chip-one">
                <span>✓</span>
                Account
            </div>

            <div class="profile-floating-chip profile-chip-two">
                <span>👤</span>
                Identity
            </div>

            <div class="profile-floating-chip profile-chip-three">
                <span>✎</span>
                Editable
            </div>

        </div>

    </section>

    <!-- MESSAGE -->
    <?php if ($message !== ''): ?>
        <section class="profile-alert <?= $messageStatus ? 'is-success' : 'is-error' ?> reveal-on-scroll">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        </section>
    <?php endif; ?>

    <!-- OVERVIEW -->
    <section class="profile-kpi-grid">

        <article class="profile-kpi-card reveal-on-scroll">
            <span>Member Since</span>

            <strong>
                <?= htmlspecialchars(formatProfileDateTime($profile['created_at'] ?? null), ENT_QUOTES, 'UTF-8') ?>
            </strong>

            <p>
                Account creation date.
            </p>
        </article>

        <article class="profile-kpi-card is-role reveal-on-scroll">
            <span>Role</span>

            <strong>
                <?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?>
            </strong>

            <p>
                Current authorization level.
            </p>
        </article>

        <article class="profile-kpi-card is-status reveal-on-scroll">
            <span>Account Status</span>

            <strong>
                <?= $isActive ? 'Active' : 'Passive' ?>
            </strong>

            <p>
                <?= $isActive ? 'Your account can use the system.' : 'Your account is currently inactive.' ?>
            </p>
        </article>

    </section>

    <!-- MAIN INFORMATION -->
    <section class="profile-main-grid">

        <!-- ACCOUNT INFORMATION -->
        <article class="profile-panel reveal-on-scroll">

            <div class="profile-section-header">
                <div>
                    <span class="profile-section-label">
                        Account
                    </span>

                    <h2>
                        Account Information
                    </h2>

                    <p>
                        Core login and identity information.
                    </p>
                </div>

                <span class="profile-status-badge <?= profileStatusClass((int) $profile['is_active']) ?>">
                    <?= $isActive ? 'Active' : 'Passive' ?>
                </span>
            </div>

            <div class="profile-info-grid">

                <div class="profile-info-row">
                    <span>Full Name</span>
                    <strong><?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?></strong>
                </div>

                <div class="profile-info-row">
                    <span>Email</span>
                    <strong><?= htmlspecialchars($profile['email'], ENT_QUOTES, 'UTF-8') ?></strong>
                </div>

                <div class="profile-info-row">
                    <span>Phone</span>
                    <strong><?= htmlspecialchars($profile['phone'] ?? '-', ENT_QUOTES, 'UTF-8') ?></strong>
                </div>

                <div class="profile-info-row">
                    <span>Role</span>
                    <strong><?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?></strong>
                </div>

                <div class="profile-info-row">
                    <span>Created At</span>
                    <strong><?= htmlspecialchars(formatProfileDateTime($profile['created_at'] ?? null), ENT_QUOTES, 'UTF-8') ?></strong>
                </div>

                <div class="profile-info-row">
                    <span>Updated At</span>
                    <strong><?= htmlspecialchars(formatProfileDateTime($profile['updated_at'] ?? null), ENT_QUOTES, 'UTF-8') ?></strong>
                </div>

            </div>

        </article>

        <!-- STUDENT INFORMATION -->
        <?php if ($profile['role_name'] === 'student'): ?>

            <article class="profile-panel reveal-on-scroll">

                <div class="profile-section-header">
                    <div>
                        <span class="profile-section-label">
                            Student
                        </span>

                        <h2>
                            Student Information
                        </h2>

                        <p>
                            Academic profile and department information.
                        </p>
                    </div>

                    <span class="profile-status-badge is-info">
                        Student
                    </span>
                </div>

                <div class="profile-info-grid">

                    <div class="profile-info-row">
                        <span>Student No</span>
                        <strong><?= htmlspecialchars($profile['student_no'] ?? '-', ENT_QUOTES, 'UTF-8') ?></strong>
                    </div>

                    <div class="profile-info-row">
                        <span>Faculty</span>
                        <strong><?= htmlspecialchars($profile['faculty_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></strong>
                    </div>

                    <div class="profile-info-row">
                        <span>Department</span>
                        <strong><?= htmlspecialchars($profile['department_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></strong>
                    </div>

                    <div class="profile-info-row">
                        <span>Class Year</span>
                        <strong><?= $profile['class_year'] !== null ? (int) $profile['class_year'] : '-' ?></strong>
                    </div>

                    <div class="profile-info-row">
                        <span>Program Type</span>
                        <strong><?= htmlspecialchars($profile['program_type'] ?? '-', ENT_QUOTES, 'UTF-8') ?></strong>
                    </div>

                </div>

            </article>

        <?php endif; ?>

    </section>

    <!-- QUICK ACTIONS -->
    <section class="profile-panel reveal-on-scroll">

        <div class="profile-section-header">
            <div>
                <span class="profile-section-label">
                    Workflow
                </span>

                <h2>
                    Quick Actions
                </h2>

                <p>
                    Continue your academic reservation workflow.
                </p>
            </div>

            <span class="profile-status-badge is-info">
                Shortcut
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

    </section>

    <!-- EDIT -->
    <section class="profile-panel reveal-on-scroll">

        <div class="profile-section-header">
            <div>
                <span class="profile-section-label">
                    Editable
                </span>

                <h2>
                    Edit Profile
                </h2>

                <p>
                    You can update editable contact and program information.
                </p>
            </div>

            <span class="profile-status-badge is-info">
                Update
            </span>
        </div>

        <form method="POST" action="" class="profile-form">

            <div class="profile-form-grid">

                <div class="profile-form-group">
                    <label for="phone">Phone</label>

                    <input
                        type="text"
                        id="phone"
                        name="phone"
                        value="<?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="Example: 0555 111 2233"
                    >

                    <small>
                        Optional contact number.
                    </small>
                </div>

                <?php if ($profile['role_name'] === 'student'): ?>

                    <div class="profile-form-group">
                        <label for="program_type">Program Type</label>

                        <input
                            type="text"
                            id="program_type"
                            name="program_type"
                            value="<?= htmlspecialchars($programType, ENT_QUOTES, 'UTF-8') ?>"
                            placeholder="Example: 100% Turkish"
                        >

                        <small>
                            Optional academic program detail.
                        </small>
                    </div>

                <?php endif; ?>

            </div>

            <div class="profile-form-actions">
                <button type="submit" class="profile-btn profile-btn-primary">
                    Update Profile
                </button>

                <a href="dashboard.php" class="profile-btn profile-btn-outline">
                    Cancel
                </a>
            </div>

        </form>

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

    const profileTiltCard = document.querySelector('[data-profile-tilt-card]');

    if (profileTiltCard) {
        profileTiltCard.addEventListener('pointermove', function (event) {
            const rect = profileTiltCard.getBoundingClientRect();

            const x = event.clientX - rect.left;
            const y = event.clientY - rect.top;

            const rotateY = ((x / rect.width) - 0.5) * 5;
            const rotateX = ((y / rect.height) - 0.5) * -5;

            profileTiltCard.style.transform =
                'perspective(1200px) rotateX(' + rotateX + 'deg) rotateY(' + rotateY + 'deg)';
        });

        profileTiltCard.addEventListener('pointerleave', function () {
            profileTiltCard.style.transform =
                'perspective(1200px) rotateX(0deg) rotateY(0deg)';
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>