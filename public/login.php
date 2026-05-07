<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth_helper.php';
require_once __DIR__ . '/../includes/csrf.php';

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
$bodyClass = 'page-auth page-login';

$error = '';
$successMessage = '';

if (isset($_GET['registered']) && $_GET['registered'] === '1') {
    $successMessage = 'Registration completed successfully. You can now log in.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfVerify(csrfTokenFromRequest())) {
        $error = 'Form security token expired. Please refresh the page and try again.';
    } else {
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
}

require_once __DIR__ . '/../includes/header.php';

?>

<section class="auth-page">

    <div class="auth-shell">

        <!-- LEFT CONTENT -->
        <div class="auth-intro" data-auth-tilt-card>

            <span class="auth-eyebrow">
                Secure Access
            </span>

            <h1>
                Welcome back to your reservation workspace.
            </h1>

            <p>
                Sign in to browse laboratories, create station reservations,
                review your requests and continue your academic workflow.
            </p>
            <div class="auth-floating-elements" aria-hidden="true">
                <span class="auth-float-chip auth-float-one">
                    <span>✓</span>
                    Secure Login
                </span>

                <span class="auth-float-chip auth-float-two">
                    <span>⏱</span>
                    Quick Access
                </span>

                <span class="auth-float-chip auth-float-three">
                    <span>📌</span>
                    Track Requests
                </span>
            </div>
            <div class="auth-info-grid">

                <div class="auth-info-card">
                    <span class="auth-info-icon">01</span>
                    <div>
                        <strong>Browse Laboratories</strong>
                        <small>Explore available laboratory areas and stations.</small>
                    </div>
                </div>

                <div class="auth-info-card">
                    <span class="auth-info-icon">02</span>
                    <div>
                        <strong>Create Reservations</strong>
                        <small>Select a station and reserve a suitable time.</small>
                    </div>
                </div>

                <div class="auth-info-card">
                    <span class="auth-info-icon">03</span>
                    <div>
                        <strong>Track Requests</strong>
                        <small>View, edit or cancel your reservations.</small>
                    </div>
                </div>

            </div>

        </div>

        <!-- RIGHT FORM -->
        <div class="auth-card">

            <div class="auth-card-header">
                <span class="auth-card-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="12" cy="8" r="4"></circle>
                        <path d="M4 20c1.7-4 4.8-6 8-6s6.3 2 8 6"></path>
                    </svg>
                </span>

                <div>
                    <h2>Login</h2>
                    <p>Enter your credentials to continue.</p>
                </div>
            </div>

            <?php if ($successMessage !== ''): ?>
                <div class="auth-alert auth-alert-success">
                    <?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="auth-alert auth-alert-error">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="auth-form" novalidate>
                <?= csrfInput() ?>

                <div class="auth-form-group">
                    <label for="email">Email Address</label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="example@domain.com"
                        autocomplete="email"
                        required
                    >

                    <small class="auth-field-message" data-message-for="email"></small>
                </div>

                <div class="auth-form-group">
                    <label for="password">Password</label>

                    <div class="auth-password-wrap">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                        >

                        <button
                            type="button"
                            class="auth-password-toggle"
                            aria-label="Show or hide password"
                            data-toggle-password
                        >
                            Show
                        </button>
                    </div>

                    <small class="auth-field-message" data-message-for="password"></small>
                </div>

                <button type="submit" class="auth-submit-btn">
                    Login
                </button>

            </form>

            <div class="auth-switch">
                <span>Do not have an account?</span>
                <a href="register.php">Create an account</a>
            </div>

        

        </div>

    </div>

</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const passwordInput = document.getElementById('password');
    const toggleButton = document.querySelector('[data-toggle-password]');

    if (passwordInput && toggleButton) {
        toggleButton.addEventListener('click', function () {
            const isPassword = passwordInput.type === 'password';

            passwordInput.type = isPassword ? 'text' : 'password';
            toggleButton.textContent = isPassword ? 'Hide' : 'Show';
            toggleButton.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
            
        });
    }

    const form = document.querySelector('.auth-form');

    if (!form) {
        return;
    }

    form.addEventListener('submit', function (event) {
        const emailInput = document.getElementById('email');
        const passwordInput = document.getElementById('password');

        let isValid = true;

        document.querySelectorAll('.auth-field-message').forEach(function (message) {
            message.textContent = '';
        });

        if (!emailInput.value.trim()) {
            const message = document.querySelector('[data-message-for="email"]');

            if (message) {
                message.textContent = 'Email address is required.';
            }

            isValid = false;
        }

        if (!passwordInput.value.trim()) {
            const message = document.querySelector('[data-message-for="password"]');

            if (message) {
                message.textContent = 'Password is required.';
            }

            isValid = false;
        }

        if (!isValid) {
            event.preventDefault();
        }
    });
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