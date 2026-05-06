<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth_helper.php';
require_once __DIR__ . '/../helpers/validation_helper.php';
require_once __DIR__ . '/../includes/csrf.php';

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
    requireCsrfToken();

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

            $errors[] = DEBUG_MODE
                ? 'Registration failed: ' . $e->getMessage()
                : 'Registration failed. Please try again.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';

?>

<section class="page-section">
    <div class="container">

        <div class="grid grid-2" style="align-items:start; gap:48px;">

            <!-- LEFT -->
            <div>

                <h1 class="section-title" style="font-size:40px;">
                    Create Your Academic Account
                </h1>

                <p class="section-subtitle" style="font-size:18px;">
                    Join the laboratory reservation system to explore laboratories,
                    select stations, and manage academic reservations professionally.
                </p>

                <div class="auth-feature-list">

                    <div class="auth-feature-item">
                        <span class="badge badge-info">1</span>
                        <span>Register with student identity</span>
                    </div>

                    <div class="auth-feature-item">
                        <span class="badge badge-info">2</span>
                        <span>Select faculty and department</span>
                    </div>

                    <div class="auth-feature-item">
                        <span class="badge badge-info">3</span>
                        <span>Access laboratories and stations</span>
                    </div>

                    <div class="auth-feature-item">
                        <span class="badge badge-info">4</span>
                        <span>Create and manage reservations</span>
                    </div>

                </div>

            </div>

            <!-- RIGHT -->
            <div class="card">

                <h2 style="margin-top:0;">Register</h2>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <ul style="margin:0; padding-left:18px;">
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <?= csrfInput() ?>

                    <div class="grid grid-2">

                        <div class="form-group">
                            <label for="first_name" class="form-label">First Name</label>
                            <input type="text" id="first_name" name="first_name" class="form-control" value="<?= htmlspecialchars($firstName) ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="last_name" class="form-label">Last Name</label>
                            <input type="text" id="last_name" name="last_name" class="form-control" value="<?= htmlspecialchars($lastName) ?>" required>
                        </div>

                    </div>

                    <div class="form-group">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($email) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="phone" class="form-label">Phone</label>
                        <input type="text" id="phone" name="phone" class="form-control" value="<?= htmlspecialchars($phone) ?>">
                    </div>

                    <div class="form-group">
                        <label for="student_no" class="form-label">Student Number</label>
                        <input type="text" id="student_no" name="student_no" class="form-control" value="<?= htmlspecialchars($studentNo) ?>" required>
                    </div>

                    <div class="grid grid-2">

                        <div class="form-group">
                            <label for="faculty_id" class="form-label">Faculty</label>

                            <select id="faculty_id" name="faculty_id" class="form-control" required>
                                <option value="">Select faculty</option>

                                <?php foreach ($faculties as $faculty): ?>
                                    <option
                                        value="<?= (int) $faculty['faculty_id'] ?>"
                                        <?= (string) $facultyId === (string) $faculty['faculty_id'] ? 'selected' : '' ?>
                                    >
                                        <?= htmlspecialchars($faculty['faculty_name']) ?>
                                    </option>
                                <?php endforeach; ?>

                            </select>
                        </div>

                        <div class="form-group">
                            <label for="department_id" class="form-label">Department</label>

                            <select id="department_id" name="department_id" class="form-control" required>
                                <option value="">Select department</option>

                                <?php foreach ($departments as $department): ?>
                                    <option
                                        value="<?= (int) $department['department_id'] ?>"
                                        <?= (string) $departmentId === (string) $department['department_id'] ? 'selected' : '' ?>
                                    >
                                        <?= htmlspecialchars($department['department_name']) ?>
                                    </option>
                                <?php endforeach; ?>

                            </select>
                        </div>

                    </div>

                    <div class="grid grid-2">

                        <div class="form-group">
                            <label for="class_year" class="form-label">Class Year</label>

                            <select id="class_year" name="class_year" class="form-control" required>
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
                        </div>

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
                        </div>

                    </div>

                    <div class="grid grid-2">

                        <div class="form-group">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" id="password" name="password" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                        </div>

                    </div>

                    <button type="submit" class="btn btn-primary" style="width:100%; margin-top:8px;">
                        Create Account
                    </button>

                </form>

                <p style="margin-top:24px; text-align:center;">
                    Already have an account?
                    <a href="login.php" class="auth-link">
                        Login
                    </a>
                </p>

            </div>

        </div>

    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>