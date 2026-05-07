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

$pageTitle = 'Laboratory Form';
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

require_once __DIR__ . '/../../includes/header.php';
?>

<main class="page-container admin-form-page">
    <section class="card">
        <h1><?= $isEditMode ? 'Edit Laboratory' : 'Add Laboratory' ?></h1>
        <p>Manage laboratory definition, academic department, type and activity status.</p>

        <?php if ($successMessage !== ''): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" class="form-grid">
            <?= csrfInput() ?>

            <label>
                Department
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

            <label>
                Laboratory Name
                <input type="text" name="lab_name" value="<?= htmlspecialchars($form['lab_name'], ENT_QUOTES, 'UTF-8') ?>" required>
            </label>

            <label>
                Laboratory Code
                <input type="text" name="lab_code" value="<?= htmlspecialchars($form['lab_code'], ENT_QUOTES, 'UTF-8') ?>" required>
            </label>

            <label>
                Laboratory Type
                <select name="lab_type" required>
                    <?php foreach ($labTypes as $value => $label): ?>
                        <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"
                            <?= $form['lab_type'] === $value ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                Location
                <input type="text" name="location" value="<?= htmlspecialchars($form['location'], ENT_QUOTES, 'UTF-8') ?>">
            </label>

            <label>
                Phone
                <input type="text" name="phone" value="<?= htmlspecialchars($form['phone'], ENT_QUOTES, 'UTF-8') ?>">
            </label>

            <label class="form-full">
                Description
                <textarea name="description" rows="4"><?= htmlspecialchars($form['description'], ENT_QUOTES, 'UTF-8') ?></textarea>
            </label>

            <label class="form-full checkbox-line">
                <input type="checkbox" name="is_active" value="1" <?= $form['is_active'] === '1' ? 'checked' : '' ?>>
                Active laboratory
            </label>

            <div class="form-actions form-full">
                <button type="submit" class="btn btn-primary">
                    <?= $isEditMode ? 'Update Laboratory' : 'Create Laboratory' ?>
                </button>

                <a class="btn btn-secondary" href="<?= BASE_URL ?>admin/labs.php">
                    Back to Laboratories
                </a>
            </div>
        </form>
    </section>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>