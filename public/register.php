<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth_helper.php';
require_once __DIR__ . '/../helpers/validation_helper.php';

if (isLoggedIn()) {
    if (isAdmin()) {
        header('Location: admin/index.php');
    } else {
        header('Location: dashboard.php');
    }

    exit;
}

$pageTitle = 'Register';
$pageCss = 'auth.css';
$pageJs = 'auth.js';
$bodyClass = 'page-auth page-register';

$errors = [];

$firstName = '';
$lastName = '';
$email = '';
$phone = '';
$studentNo = '';
$facultyId = '';
$departmentId = '';
$classYear = '';
$programType = '';

$faculties = $pdo->query("
    SELECT faculty_id, faculty_name
    FROM faculties
    WHERE is_active = 1
    ORDER BY faculty_name ASC
")->fetchAll();

$departments = $pdo->query("
    SELECT department_id, faculty_id, department_name
    FROM departments
    WHERE is_active = 1
    ORDER BY department_name ASC
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = cleanInput($_POST['first_name'] ?? '');
    $lastName = cleanInput($_POST['last_name'] ?? '');
    $email = cleanInput($_POST['email'] ?? '');
    $phone = cleanInput($_POST['phone'] ?? '');
    $studentNo = cleanInput($_POST['student_no'] ?? '');
    $facultyId = $_POST['faculty_id'] ?? '';
    $departmentId = $_POST['department_id'] ?? '';
    $classYear = $_POST['class_year'] ?? '';
    $programType = cleanInput($_POST['program_type'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (isEmptyValue($firstName)) {
        $errors[] = 'First name is required.';
    }

    if (isEmptyValue($lastName)) {
        $errors[] = 'Last name is required.';
    }

    if (isEmptyValue($email)) {
        $errors[] = 'Email is required.';
    } elseif (!isValidEmailAddress($email)) {
        $errors[] = 'Email format is invalid.';
    }

    if (isEmptyValue($studentNo)) {
        $errors[] = 'Student number is required.';
    } elseif (!isValidStudentNumber($studentNo)) {
        $errors[] = 'Student number format is invalid.';
    }

    if (!isPositiveInteger($facultyId)) {
        $errors[] = 'Faculty selection is required.';
    }

    if (!isPositiveInteger($departmentId)) {
        $errors[] = 'Department selection is required.';
    }

    if (!isValidClassYear($classYear)) {
        $errors[] = 'Class year must be between 1 and 6.';
    }

    if ($password === '') {
        $errors[] = 'Password is required.';
    } elseif (!isValidPasswordLength($password, 6)) {
        $errors[] = 'Password must be at least 6 characters.';
    }

    if ($confirmPassword === '') {
        $errors[] = 'Password confirmation is required.';
    } elseif (!doPasswordsMatch($password, $confirmPassword)) {
        $errors[] = 'Passwords do not match.';
    }

    if ($email !== '') {
        $stmt = $pdo->prepare("
            SELECT user_id
            FROM users
            WHERE email = :email
            LIMIT 1
        ");

        $stmt->execute([
            ':email' => $email
        ]);

        if ($stmt->fetch()) {
            $errors[] = 'This email is already registered.';
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $studentRole = $pdo->query("
                SELECT role_id
                FROM roles
                WHERE role_name = 'student'
                LIMIT 1
            ")->fetch();

            if (!$studentRole) {
                throw new Exception('Student role not found.');
            }

            $passwordData = hashPassword($password);
            $passwordHash = $passwordData['hash'];
            $salt = $passwordData['salt'];

            $stmt = $pdo->prepare("
                INSERT INTO users (
                    role_id,
                    first_name,
                    last_name,
                    email,
                    password_hash,
                    password_salt,
                    phone,
                    is_active
                ) VALUES (
                    :role_id,
                    :first_name,
                    :last_name,
                    :email,
                    :password_hash,
                    :password_salt,
                    :phone,
                    1
                )
            ");

            $stmt->execute([
                ':role_id' => (int) $studentRole['role_id'],
                ':first_name' => $firstName,
                ':last_name' => $lastName,
                ':email' => $email,
                ':password_hash' => $passwordHash,
                ':password_salt' => $salt,
                ':phone' => $phone !== '' ? $phone : null
            ]);

            $newUserId = (int) $pdo->lastInsertId();

            $stmt = $pdo->prepare("
                INSERT INTO student_profiles (
                    user_id,
                    student_no,
                    faculty_id,
                    department_id,
                    class_year,
                    program_type
                ) VALUES (
                    :user_id,
                    :student_no,
                    :faculty_id,
                    :department_id,
                    :class_year,
                    :program_type
                )
            ");

            $stmt->execute([
                ':user_id' => $newUserId,
                ':student_no' => $studentNo,
                ':faculty_id' => (int) $facultyId,
                ':department_id' => (int) $departmentId,
                ':class_year' => (int) $classYear,
                ':program_type' => $programType !== '' ? $programType : null
            ]);

            $pdo->commit();

            header('Location: login.php?registered=1');
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errors[] = defined('DEBUG_MODE') && DEBUG_MODE
                ? 'Registration failed: ' . $e->getMessage()
                : 'Registration failed. Please try again.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';

?>

<section class="auth-page">

    <div class="auth-shell">

        <!-- LEFT CONTENT -->
        <div class="auth-intro" data-auth-tilt-card>

            <span class="auth-eyebrow">
                Student Regıstratıon
            </span>

            <h1>
                Create your academic reservation account.
            </h1>

            <p>
                Register with your student information, select your faculty and department,
                then start managing laboratory reservations through your personal workspace.
            </p>

            <div class="auth-floating-elements" aria-hidden="true">
                <span class="auth-float-chip auth-float-one">
                    <span>✓</span>
                    Student Access
                </span>

                <span class="auth-float-chip auth-float-two">
                    <span>⏱</span>
                    Fast Setup
                </span>

                <span class="auth-float-chip auth-float-three">
                    <span>📌</span>
                    Reservation Ready
                </span>
            </div>

            <div class="auth-info-grid">

                <div class="auth-info-card">
                    <span class="auth-info-icon">01</span>
                    <div>
                        <strong>Student Identity</strong>
                        <small>Create your account with your academic information.</small>
                    </div>
                </div>

                <div class="auth-info-card">
                    <span class="auth-info-icon">02</span>
                    <div>
                        <strong>Faculty & Department</strong>
                        <small>Select your faculty and department for system records.</small>
                    </div>
                </div>

                <div class="auth-info-card">
                    <span class="auth-info-icon">03</span>
                    <div>
                        <strong>Reservation Access</strong>
                        <small>Browse laboratories and create station reservations.</small>
                    </div>
                </div>

            </div>

        </div>

        <!-- RIGHT FORM -->
        <div class="auth-card auth-card-wide">

            <div class="auth-card-header">
                <span class="auth-card-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M4 6.5C4 5.67 4.67 5 5.5 5H18.5C19.33 5 20 5.67 20 6.5V17.5C20 18.33 19.33 19 18.5 19H5.5C4.67 19 4 18.33 4 17.5V6.5Z"></path>
                        <path d="M8 9H16"></path>
                        <path d="M8 13H13"></path>
                        <path d="M8 16H11"></path>
                    </svg>
                </span>

                <div>
                    <h2>Register</h2>
                    <p>Fill in your information to create an account.</p>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="auth-alert auth-alert-error">
                    <ul class="auth-error-list">
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="auth-form" novalidate>

                <div class="auth-form-grid">

                    <div class="auth-form-group">
                        <label for="first_name">First Name</label>

                        <input
                            type="text"
                            id="first_name"
                            name="first_name"
                            value="<?= htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8') ?>"
                            placeholder="Enter first name"
                            autocomplete="given-name"
                            required
                        >

                        <small class="auth-field-message" data-message-for="first_name"></small>
                    </div>

                    <div class="auth-form-group">
                        <label for="last_name">Last Name</label>

                        <input
                            type="text"
                            id="last_name"
                            name="last_name"
                            value="<?= htmlspecialchars($lastName, ENT_QUOTES, 'UTF-8') ?>"
                            placeholder="Enter last name"
                            autocomplete="family-name"
                            required
                        >

                        <small class="auth-field-message" data-message-for="last_name"></small>
                    </div>

                </div>

                <div class="auth-form-grid">

                    <div class="auth-form-group">
                        <label for="email">Email Address</label>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
                            placeholder="example@domain.com"
                            autocomplete="email"
                            required
                        >

                        <small class="auth-field-message" data-message-for="email"></small>
                    </div>

                    <div class="auth-form-group">
                        <label for="phone">Phone</label>

                        <input
                            type="text"
                            id="phone"
                            name="phone"
                            value="<?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?>"
                            placeholder="Optional"
                            autocomplete="tel"
                        >

                        <small class="auth-field-message" data-message-for="phone"></small>
                    </div>

                </div>

                <div class="auth-form-group">
                    <label for="student_no">Student Number</label>

                    <input
                        type="text"
                        id="student_no"
                        name="student_no"
                        value="<?= htmlspecialchars($studentNo, ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="Enter student number"
                        required
                    >

                    <small class="auth-field-message" data-message-for="student_no"></small>
                </div>

                <div class="auth-form-grid">

                    <div class="auth-form-group">
                        <label for="faculty_id">Faculty</label>

                        <select id="faculty_id" name="faculty_id" required>
                            <option value="">Select faculty</option>

                            <?php foreach ($faculties as $faculty): ?>
                                <option
                                    value="<?= (int) $faculty['faculty_id'] ?>"
                                    <?= (string) $facultyId === (string) $faculty['faculty_id'] ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars($faculty['faculty_name'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <small class="auth-field-message" data-message-for="faculty_id"></small>
                    </div>

                    <div class="auth-form-group">
                        <label for="department_id">Department</label>

                        <select id="department_id" name="department_id" required>
                            <option value="">Select department</option>

                            <?php foreach ($departments as $department): ?>
                                <option
                                    value="<?= (int) $department['department_id'] ?>"
                                    data-faculty-id="<?= (int) $department['faculty_id'] ?>"
                                    <?= (string) $departmentId === (string) $department['department_id'] ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars($department['department_name'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <small class="auth-field-message" data-message-for="department_id"></small>
                    </div>

                </div>

                <div class="auth-form-grid">

                    <div class="auth-form-group">
                        <label for="class_year">Class Year</label>

                        <select id="class_year" name="class_year" required>
                            <option value="">Select class year</option>

                            <?php for ($i = 1; $i <= 6; $i++): ?>
                                <option
                                    value="<?= $i ?>"
                                    <?= (string) $classYear === (string) $i ? 'selected' : '' ?>
                                >
                                    <?= $i ?>
                                </option>
                            <?php endfor; ?>
                        </select>

                        <small class="auth-field-message" data-message-for="class_year"></small>
                    </div>

                    <div class="auth-form-group">
                        <label for="program_type">Program Type</label>

                        <input
                            type="text"
                            id="program_type"
                            name="program_type"
                            value="<?= htmlspecialchars($programType, ENT_QUOTES, 'UTF-8') ?>"
                            placeholder="Example: 100% English"
                        >

                        <small class="auth-field-message" data-message-for="program_type"></small>
                    </div>

                </div>

                <div class="auth-form-grid">

                    <div class="auth-form-group">
                        <label for="password">Password</label>

                        <div class="auth-password-wrap">
                            <input
                                type="password"
                                id="password"
                                name="password"
                                placeholder="At least 6 characters"
                                autocomplete="new-password"
                                required
                            >

                            <button
                                type="button"
                                class="auth-password-toggle"
                                aria-label="Show password"
                                data-toggle-password-for="password"
                            >
                                Show
                            </button>
                        </div>

                        <small class="auth-field-message" data-message-for="password"></small>
                    </div>

                    <div class="auth-form-group">
                        <label for="confirm_password">Confirm Password</label>

                        <div class="auth-password-wrap">
                            <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                placeholder="Repeat password"
                                autocomplete="new-password"
                                required
                            >

                            <button
                                type="button"
                                class="auth-password-toggle"
                                aria-label="Show password"
                                data-toggle-password-for="confirm_password"
                            >
                                Show
                            </button>
                        </div>

                        <small class="auth-field-message" data-message-for="confirm_password"></small>
                    </div>

                </div>

                <button type="submit" class="auth-submit-btn">
                    Create Account
                </button>

            </form>

            <div class="auth-switch">
                <span>Already have an account?</span>
                <a href="login.php">Login</a>
            </div>

        </div>

    </div>

</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const passwordButtons = document.querySelectorAll('[data-toggle-password-for]');

    passwordButtons.forEach(function (button) {
        const inputId = button.getAttribute('data-toggle-password-for');
        const input = document.getElementById(inputId);

        if (!input) {
            return;
        }

        button.addEventListener('click', function () {
            const isPassword = input.type === 'password';

            input.type = isPassword ? 'text' : 'password';
            button.textContent = isPassword ? 'Hide' : 'Show';
            button.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
        });
    });

    const facultySelect = document.getElementById('faculty_id');
    const departmentSelect = document.getElementById('department_id');

    if (facultySelect && departmentSelect) {
        const departmentOptions = Array.from(departmentSelect.options);

        function filterDepartments() {
            const selectedFacultyId = facultySelect.value;

            departmentOptions.forEach(function (option) {
                if (!option.value) {
                    option.hidden = false;
                    return;
                }

                const optionFacultyId = option.getAttribute('data-faculty-id');
                option.hidden = selectedFacultyId !== '' && optionFacultyId !== selectedFacultyId;
            });

            const selectedOption = departmentSelect.options[departmentSelect.selectedIndex];

            if (selectedOption && selectedOption.hidden) {
                departmentSelect.value = '';
            }
        }

        facultySelect.addEventListener('change', filterDepartments);
        filterDepartments();
    }

    const form = document.querySelector('.auth-form');

    if (form) {
        form.addEventListener('submit', function (event) {
            let isValid = true;

            const requiredFields = [
                'first_name',
                'last_name',
                'email',
                'student_no',
                'faculty_id',
                'department_id',
                'class_year',
                'password',
                'confirm_password'
            ];

            document.querySelectorAll('.auth-field-message').forEach(function (message) {
                message.textContent = '';
            });

            requiredFields.forEach(function (fieldId) {
                const field = document.getElementById(fieldId);

                if (!field || field.value.trim() !== '') {
                    return;
                }

                const message = document.querySelector('[data-message-for="' + fieldId + '"]');

                if (message) {
                    message.textContent = 'This field is required.';
                }

                isValid = false;
            });

            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');

            if (password && confirmPassword && password.value && confirmPassword.value) {
                if (password.value !== confirmPassword.value) {
                    const message = document.querySelector('[data-message-for="confirm_password"]');

                    if (message) {
                        message.textContent = 'Passwords do not match.';
                    }

                    isValid = false;
                }
            }

            if (!isValid) {
                event.preventDefault();
            }
        });
    }

    const authTiltCard = document.querySelector('[data-auth-tilt-card]');

    if (authTiltCard) {
        authTiltCard.addEventListener('pointermove', function (event) {
            const rect = authTiltCard.getBoundingClientRect();

            const x = event.clientX - rect.left;
            const y = event.clientY - rect.top;

            const rotateY = ((x / rect.width) - 0.5) * 7;
            const rotateX = ((y / rect.height) - 0.5) * -7;

            authTiltCard.style.transform =
                'perspective(1100px) rotateX(' + rotateX + 'deg) rotateY(' + rotateY + 'deg) translateY(-4px)';
        });

        authTiltCard.addEventListener('pointerleave', function () {
            authTiltCard.style.transform =
                'perspective(1100px) rotateX(0deg) rotateY(0deg) translateY(0)';
        });

        authTiltCard.addEventListener('pointerdown', function () {
            authTiltCard.classList.add('is-touching');
        });

        authTiltCard.addEventListener('pointerup', function () {
            authTiltCard.classList.remove('is-touching');
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>