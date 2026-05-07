<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth_helper.php';
require_once __DIR__ . '/../../helpers/lab_helper.php';
require_once __DIR__ . '/../../includes/csrf.php';

if (!isLoggedIn()) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

if (!isAdmin()) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit;
}

$errors = [];
$successMessage = '';

$labTypes = [
    'computer' => 'Computer',
    'network' => 'Network',
    'electronics' => 'Electronics',
    'machine' => 'Machine',
    'general' => 'General',
];

$departments = getActiveDepartments($pdo);

$labId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$isEditMode = $labId !== null && $labId !== false && $labId > 0;

$form = [
    'department_id' => '',
    'lab_name' => '',
    'lab_code' => '',
    'lab_type' => 'computer',
    'location' => '',
    'phone' => '',
    'description' => '',
    'is_active' => '1',
];

if ($isEditMode) {
    $stmt = $pdo->prepare("
        SELECT
            lab_id,
            department_id,
            lab_name,
            lab_code,
            lab_type,
            location,
            phone,
            description,
            is_active
        FROM laboratories
        WHERE lab_id = :lab_id
        LIMIT 1
    ");

    $stmt->execute([
        ':lab_id' => (int) $labId,
    ]);

    $existingLab = $stmt->fetch();

    if (!$existingLab) {
        header('Location: ' . BASE_URL . 'admin/labs.php');
        exit;
    }

    $form = [
        'department_id' => (string) $existingLab['department_id'],
        'lab_name' => (string) $existingLab['lab_name'],
        'lab_code' => (string) $existingLab['lab_code'],
        'lab_type' => (string) $existingLab['lab_type'],
        'location' => (string) ($existingLab['location'] ?? ''),
        'phone' => (string) ($existingLab['phone'] ?? ''),
        'description' => (string) ($existingLab['description'] ?? ''),
        'is_active' => (string) $existingLab['is_active'],
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfVerify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Security token is invalid. Please refresh the page and try again.';
    }

    $form = [
        'department_id' => trim((string) ($_POST['department_id'] ?? '')),
        'lab_name' => trim((string) ($_POST['lab_name'] ?? '')),
        'lab_code' => strtoupper(trim((string) ($_POST['lab_code'] ?? ''))),
        'lab_type' => trim((string) ($_POST['lab_type'] ?? '')),
        'location' => trim((string) ($_POST['location'] ?? '')),
        'phone' => trim((string) ($_POST['phone'] ?? '')),
        'description' => trim((string) ($_POST['description'] ?? '')),
        'is_active' => isset($_POST['is_active']) ? '1' : '0',
    ];

    if (!filter_var($form['department_id'], FILTER_VALIDATE_INT)) {
        $errors[] = 'Department is required.';
    }

    if ($form['lab_name'] === '') {
        $errors[] = 'Laboratory name is required.';
    }

    if ($form['lab_code'] === '') {
        $errors[] = 'Laboratory code is required.';
    }

    if (!array_key_exists($form['lab_type'], $labTypes)) {
        $errors[] = 'Valid laboratory type is required.';
    }

    if (!$errors) {
        try {
            if ($isEditMode) {
                $stmt = $pdo->prepare("
                    UPDATE laboratories
                    SET
                        department_id = :department_id,
                        lab_name = :lab_name,
                        lab_code = :lab_code,
                        lab_type = :lab_type,
                        location = :location,
                        phone = :phone,
                        description = :description,
                        is_active = :is_active
                    WHERE lab_id = :lab_id
                ");

                $stmt->execute([
                    ':department_id' => (int) $form['department_id'],
                    ':lab_name' => $form['lab_name'],
                    ':lab_code' => $form['lab_code'],
                    ':lab_type' => $form['lab_type'],
                    ':location' => $form['location'] !== '' ? $form['location'] : null,
                    ':phone' => $form['phone'] !== '' ? $form['phone'] : null,
                    ':description' => $form['description'] !== '' ? $form['description'] : null,
                    ':is_active' => (int) $form['is_active'],
                    ':lab_id' => (int) $labId,
                ]);

                $successMessage = 'Laboratory updated successfully.';
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO laboratories
                        (department_id, lab_name, lab_code, lab_type, location, phone, description, is_active)
                    VALUES
                        (:department_id, :lab_name, :lab_code, :lab_type, :location, :phone, :description, :is_active)
                ");

                $stmt->execute([
                    ':department_id' => (int) $form['department_id'],
                    ':lab_name' => $form['lab_name'],
                    ':lab_code' => $form['lab_code'],
                    ':lab_type' => $form['lab_type'],
                    ':location' => $form['location'] !== '' ? $form['location'] : null,
                    ':phone' => $form['phone'] !== '' ? $form['phone'] : null,
                    ':description' => $form['description'] !== '' ? $form['description'] : null,
                    ':is_active' => (int) $form['is_active'],
                ]);

                $labId = (int) $pdo->lastInsertId();
                $isEditMode = true;
                $successMessage = 'Laboratory created successfully.';
            }
        } catch (Throwable $e) {
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                $errors[] = 'Database error: ' . $e->getMessage();
            } else {
                $errors[] = 'Laboratory could not be saved.';
            }
        }
    }
}

$pageTitle = $isEditMode ? 'Edit Laboratory' : 'Add Laboratory';
$pageCss = 'admin-forms.css';

$totalDepartments = count($departments);
$totalLabTypes = count($labTypes);
$currentLabTypeLabel = $labTypes[$form['lab_type']] ?? 'Computer';
$currentVisibilityLabel = $form['is_active'] === '1' ? 'Active' : 'Passive';

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-container adminform-page">
    <section class="adminform-hero reveal-on-scroll">
        <div class="adminform-hero-content">
            <span class="adminform-eyebrow">
                Laboratory Registry
            </span>

            <h1 class="adminform-title">
                <?= $isEditMode ? 'Update laboratory workspace.' : 'Create a laboratory workspace.' ?>
            </h1>

            <p class="adminform-description">
                Manage academic ownership, laboratory identity, type, contact information and visibility status from one consistent admin form.
            </p>

            <div class="adminform-hero-actions">
                <a class="adminform-btn adminform-btn-primary" href="<?= BASE_URL ?>admin/labs.php">
                    Laboratory List
                </a>

                <a class="adminform-btn adminform-btn-light" href="<?= BASE_URL ?>admin/index.php">
                    Admin Dashboard
                </a>
            </div>
        </div>

        <div class="adminform-hero-visual" aria-hidden="true">
            <div class="adminform-mini-panel">
                <div class="adminform-mini-header">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>

                <div class="adminform-mini-body">
                    <div class="adminform-mini-title-row">
                        <div>
                            <small>Laboratory Workflow</small>
                            <strong><?= $isEditMode ? 'Update Mode' : 'Create Mode' ?></strong>
                        </div>

                        <span><?= htmlspecialchars($currentVisibilityLabel, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>

                    <div class="adminform-mini-list">
                        <div class="adminform-mini-item is-active">
                            <span>01</span>
                            <div>
                                <strong>Academic Unit</strong>
                                <small>Connect the lab to a department.</small>
                            </div>
                        </div>

                        <div class="adminform-mini-item">
                            <span>02</span>
                            <div>
                                <strong>Lab Identity</strong>
                                <small>Define name, code and laboratory type.</small>
                            </div>
                        </div>

                        <div class="adminform-mini-item">
                            <span>03</span>
                            <div>
                                <strong>Availability</strong>
                                <small>Control active/passive visibility.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="adminform-floating-chip adminform-chip-one">
                <span>✓</span>
                <?= $isEditMode ? 'Editable Lab' : 'New Lab' ?>
            </div>

            <div class="adminform-floating-chip adminform-chip-two">
                <span>↗</span>
                <?= htmlspecialchars($currentLabTypeLabel, ENT_QUOTES, 'UTF-8') ?>
            </div>
        </div>
    </section>

    <section class="adminform-kpi-grid reveal-on-scroll" aria-label="Laboratory form summary">
        <div class="adminform-kpi-card">
            <span>Departments</span>
            <strong><?= (int) $totalDepartments ?></strong>
            <p>Active academic departments available for laboratory ownership.</p>
        </div>

        <div class="adminform-kpi-card">
            <span>Lab Types</span>
            <strong><?= (int) $totalLabTypes ?></strong>
            <p>Supported laboratory categories used across the system.</p>
        </div>

        <div class="adminform-kpi-card">
            <span>Visibility</span>
            <strong><?= htmlspecialchars($currentVisibilityLabel, ENT_QUOTES, 'UTF-8') ?></strong>
            <p>Current laboratory availability setting for users and admins.</p>
        </div>
    </section>

    <section class="adminform-card reveal-on-scroll">
        <div class="adminform-section-header">
            <div>
                <span class="adminform-section-label">
                    <?= $isEditMode ? 'Edit Form' : 'Create Form' ?>
                </span>

                <h2>
                    <?= $isEditMode ? 'Update laboratory information.' : 'Create laboratory information.' ?>
                </h2>

                <p>
                    Required fields are department, laboratory name, laboratory code and laboratory type. Contact fields and description are optional.
                </p>
            </div>

            <span class="adminform-status-pill">
                <?= $isEditMode ? 'Editing' : 'Creating' ?>
            </span>
        </div>

        <?php if ($successMessage !== ''): ?>
            <div class="adminform-alert adminform-alert-success">
                <?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="adminform-alert adminform-alert-error">
                <strong>Please check the following:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" class="adminform-form" novalidate>
            <?= csrfInput() ?>

            <div class="adminform-section">
                <div class="adminform-section-title">
                    <span>01</span>
                    <div>
                        <h3>Academic Ownership</h3>
                        <p>Choose the department that owns or manages this laboratory.</p>
                    </div>
                </div>

                <div class="adminform-grid">
                    <label class="adminform-field">
                        <span>Department</span>
                        <select name="department_id" required>
                            <option value="">Select department</option>
                            <?php foreach ($departments as $department): ?>
                                <option value="<?= (int) $department['department_id'] ?>"
                                    <?= (string) $form['department_id'] === (string) $department['department_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($department['faculty_name'] . ' - ' . $department['department_name'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="adminform-field">
                        <span>Laboratory Type</span>
                        <select name="lab_type" required>
                            <?php foreach ($labTypes as $value => $label): ?>
                                <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"
                                    <?= $form['lab_type'] === $value ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
            </div>

            <div class="adminform-section">
                <div class="adminform-section-title">
                    <span>02</span>
                    <div>
                        <h3>Laboratory Identity</h3>
                        <p>Define the public-facing name and the short code used in lists, filters and stations.</p>
                    </div>
                </div>

                <div class="adminform-grid">
                    <label class="adminform-field">
                        <span>Laboratory Name</span>
                        <input
                            type="text"
                            name="lab_name"
                            value="<?= htmlspecialchars($form['lab_name'], ENT_QUOTES, 'UTF-8') ?>"
                            placeholder="Example: Bilgisayar Ağları Laboratuvarı"
                            required
                        >
                    </label>

                    <label class="adminform-field">
                        <span>Laboratory Code</span>
                        <input
                            type="text"
                            name="lab_code"
                            value="<?= htmlspecialchars($form['lab_code'], ENT_QUOTES, 'UTF-8') ?>"
                            placeholder="Example: NET-LAB"
                            required
                        >
                    </label>
                </div>
            </div>

            <div class="adminform-section">
                <div class="adminform-section-title">
                    <span>03</span>
                    <div>
                        <h3>Contact & Location</h3>
                        <p>Add optional location and phone details for admin and student guidance.</p>
                    </div>
                </div>

                <div class="adminform-grid">
                    <label class="adminform-field">
                        <span>Location</span>
                        <input
                            type="text"
                            name="location"
                            value="<?= htmlspecialchars($form['location'], ENT_QUOTES, 'UTF-8') ?>"
                            placeholder="Example: Mühendislik Fakültesi C Blok"
                        >
                    </label>

                    <label class="adminform-field">
                        <span>Phone</span>
                        <input
                            type="text"
                            name="phone"
                            value="<?= htmlspecialchars($form['phone'], ENT_QUOTES, 'UTF-8') ?>"
                            placeholder="Example: 0370 000 00 00"
                        >
                    </label>
                </div>
            </div>

            <div class="adminform-section">
                <div class="adminform-section-title">
                    <span>04</span>
                    <div>
                        <h3>Description & Visibility</h3>
                        <p>Write a short description and decide whether the laboratory is active in the system.</p>
                    </div>
                </div>

                <div class="adminform-grid">
                    <label class="adminform-field adminform-field-full">
                        <span>Description</span>
                        <textarea
                            name="description"
                            rows="5"
                            placeholder="Optional summary about laboratory capabilities, usage area or reservation notes."
                        ><?= htmlspecialchars($form['description'], ENT_QUOTES, 'UTF-8') ?></textarea>
                    </label>

                    <label class="adminform-checkline adminform-field-full">
                        <input
                            type="checkbox"
                            name="is_active"
                            value="1"
                            <?= $form['is_active'] === '1' ? 'checked' : '' ?>
                        >
                        <span>
                            <strong>Active laboratory</strong>
                            <small>Active laboratories can be displayed and used across the reservation workflow.</small>
                        </span>
                    </label>
                </div>
            </div>

            <div class="adminform-actions">
                <button type="submit" class="adminform-btn adminform-btn-primary">
                    <?= $isEditMode ? 'Update Laboratory' : 'Create Laboratory' ?>
                </button>

                <a class="adminform-btn adminform-btn-light" href="<?= BASE_URL ?>admin/labs.php">
                    Back to Laboratories
                </a>
            </div>
        </form>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const revealItems = document.querySelectorAll('.reveal-on-scroll');

    if ('IntersectionObserver' in window && revealItems.length > 0) {
        const observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.12
        });

        revealItems.forEach(function (item) {
            observer.observe(item);
        });
    } else {
        revealItems.forEach(function (item) {
            item.classList.add('is-visible');
        });
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>