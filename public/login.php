<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth_helper.php';

if (isLoggedIn()) {
    if (isAdmin()) {
        header('Location: admin/index.php');
    } else {
        header('Location: dashboard.php');
    }
    exit;
}

$pageTitle = 'Login';
$pageCss = 'auth.css';
$pageJs = 'auth.js';

$error = '';
$successMessage = '';

if (isset($_GET['registered']) && $_GET['registered'] === '1') {
    $successMessage = 'Registration completed successfully. You can now log in.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Email and password are required.';
    } else {
        $stmt = $pdo->prepare("
            SELECT
                u.user_id,
                u.role_id,
                u.first_name,
                u.last_name,
                u.email,
                u.password_hash,
                u.password_salt,
                u.is_active,
                r.role_name
            FROM users u
            INNER JOIN roles r
                ON u.role_id = r.role_id
            WHERE u.email = :email
            LIMIT 1
        ");

        $stmt->execute([
            ':email' => $email
        ]);

        $user = $stmt->fetch();

        if (!$user) {
            $error = 'Invalid email or password.';
        } elseif ((int) $user['is_active'] !== 1) {
            $error = 'This account is not active.';
        } elseif (!verifyPassword($password, $user['password_salt'], $user['password_hash'])) {
            $error = 'Invalid email or password.';
        } else {
            session_regenerate_id(true);

            $_SESSION['user_id'] = (int) $user['user_id'];
            $_SESSION['role_id'] = (int) $user['role_id'];
            $_SESSION['role_name'] = $user['role_name'];
            $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['email'] = $user['email'];

            if ($user['role_name'] === 'admin') {
                header('Location: admin/index.php');
            } else {
                header('Location: dashboard.php');
            }
            exit;
        }
    }
}

require_once __DIR__ . '/../includes/header.php';

?>

<section class="page-section">
    <div class="container">

        <div class="grid grid-2" style="align-items:center; gap:48px;">

            <!-- LEFT -->
            <div>
                <h1 class="section-title" style="font-size:40px;">
                    Welcome Back
                </h1>

                <p class="section-subtitle" style="font-size:18px;">
                    Access your laboratory dashboard, manage reservations,
                    explore laboratories and continue your academic workflow.
                </p>

                <div class="card" style="margin-top:32px;">
                    <h3 style="margin-top:0;">System Access</h3>

                    <ul>
                        <li>Laboratory browsing</li>
                        <li>Station reservations</li>
                        <li>Reservation management</li>
                        <li>Academic dashboard</li>
                    </ul>
                </div>
            </div>

            <!-- RIGHT -->
            <div class="card" style="max-width:520px; width:100%; margin:0 auto;">

                <h2 style="margin-top:0;">Login</h2>

                <?php if ($successMessage !== ''): ?>
                    <div class="alert alert-success">
                        <?= htmlspecialchars($successMessage) ?>
                    </div>
                <?php endif; ?>

                <?php if ($error !== ''): ?>
                    <div class="alert alert-error">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">

                    <div class="form-group">
                        <label for="email" class="form-label">Email</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="form-control"
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-control"
                            required
                        >
                    </div>

                    <button type="submit" class="btn btn-primary" style="width:100%;">
                        Login
                    </button>

                </form>

                <p style="margin-top:24px; text-align:center;">
                    Do not have an account?
                    <a href="register.php" style="color:var(--color-primary); font-weight:600;">
                        Create an account
                    </a>
                </p>

                <hr style="margin:24px 0; border:none; border-top:1px solid var(--color-border);">

                <div style="font-size:14px; color:var(--color-muted);">
                    <p>
                        <strong>Test admin:</strong>
                        admin@lab.local / 123456
                    </p>

                    <p>
                        <strong>Test student:</strong>
                        onur.demo@ogrenci.karabuk.edu.tr / 123456
                    </p>
                </div>

            </div>

        </div>

    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>