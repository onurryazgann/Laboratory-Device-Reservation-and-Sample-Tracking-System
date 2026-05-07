<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/auth_helper.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'Home';
$pageCss = 'index.css';
$bodyClass = 'page-home';

$isLoggedIn = isLoggedIn();

$featuredLabs = [];

try {
    $labQuery = $pdo->query("
        SELECT
            l.lab_id,
            l.lab_name,
            l.lab_code,
            l.lab_type,
            l.location,
            l.description,
            d.department_name,
            f.faculty_name,
            COUNT(DISTINCT w.station_id) AS station_count,
            COUNT(DISTINCT e.equipment_id) AS equipment_count
        FROM laboratories l
        INNER JOIN departments d ON d.department_id = l.department_id
        INNER JOIN faculties f ON f.faculty_id = d.faculty_id
        LEFT JOIN workstations w ON w.lab_id = l.lab_id AND w.status = 'active'
        LEFT JOIN equipment_instances e ON e.lab_id = l.lab_id AND e.status = 'available'
        WHERE l.is_active = 1
        GROUP BY
            l.lab_id,
            l.lab_name,
            l.lab_code,
            l.lab_type,
            l.location,
            l.description,
            d.department_name,
            f.faculty_name
        ORDER BY l.created_at DESC, l.lab_name ASC
        LIMIT 12
    ");

    $featuredLabs = $labQuery->fetchAll();
} catch (Throwable $e) {
    $featuredLabs = [];
}

require_once __DIR__ . '/../includes/header.php';

?>

<section class="home-page">

    <!-- HERO SECTION -->
    <section class="home-hero">

        <div class="home-hero-left">

            <span class="home-eyebrow">
                Smart Academic Reservation Platform
            </span>

            <h1 class="home-title">
                Manage laboratory reservations with a cleaner and smarter workflow.
            </h1>

            <p class="home-description">
                Browse laboratories, select workstations, check equipment details and
                create reservations through a simple, modern and student-friendly system.
            </p>

            <div class="home-actions">

                <?php if (!$isLoggedIn): ?>

                    <a href="register.php" class="home-btn home-btn-primary">
                        Create Account
                    </a>

                    <a href="login.php" class="home-btn home-btn-light">
                        Login
                    </a>

                <?php else: ?>

                    <a href="dashboard.php" class="home-btn home-btn-primary">
                        Go to Dashboard
                    </a>

                    <a href="reserve.php" class="home-btn home-btn-light">
                        New Reservation
                    </a>

                <?php endif; ?>

                <a href="labs.php" class="home-btn home-btn-outline">
                    Explore Laboratories
                </a>

            </div>

            <div class="home-stats">

                <div class="home-stat-item">
                    <strong>5</strong>
                    <span>Step workflow</span>
                </div>

                <div class="home-stat-item">
                    <strong>24/7</strong>
                    <span>Online access</span>
                </div>

                <div class="home-stat-item">
                    <strong>Live</strong>
                    <span>Availability check</span>
                </div>

            </div>

        </div>

        <div class="home-hero-right">

            <div class="home-visual-card" data-tilt-card>

                <div class="home-device-panel">

                    <div class="home-device-header">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>

                    <div class="home-device-body">

                        <div class="home-device-title-row">
                            <div>
                                <small>Current Process</small>
                                <h3>Station Reservation</h3>
                            </div>

                            <span class="home-live-badge">
                                Live
                            </span>
                        </div>

                        <div class="home-process-list">

                            <div class="home-process-item active">
                                <span>01</span>
                                <div>
                                    <strong>Choose Laboratory</strong>
                                    <small>Filter by faculty and department</small>
                                </div>
                            </div>

                            <div class="home-process-item active">
                                <span>02</span>
                                <div>
                                    <strong>Select Station</strong>
                                    <small>Review workstation and equipment</small>
                                </div>
                            </div>

                            <div class="home-process-item">
                                <span>03</span>
                                <div>
                                    <strong>Check Availability</strong>
                                    <small>Instant time control</small>
                                </div>
                            </div>

                            <div class="home-process-item">
                                <span>04</span>
                                <div>
                                    <strong>Create Reservation</strong>
                                    <small>Save request to database</small>
                                </div>
                            </div>

                        </div>

                    </div>

                </div>

                <div class="home-floating-chip chip-one">
                    <span>✓</span>
                    Available
                </div>

                <div class="home-floating-chip chip-two">
                    <span>⚙</span>
                    Equipment Ready
                </div>

                <div class="home-floating-chip chip-three">
                    <span>⏱</span>
                    Time Checked
                </div>

            </div>

        </div>

    </section>

    <!-- QUICK WORKFLOW -->
    <section class="home-section">

        <div class="home-section-heading">
            <span class="home-section-label">
                How It Works
            </span>

            <h2>
                A clear reservation flow from selection to approval.
            </h2>

            <p>
                The system separates each action into simple steps so users can easily
                understand and complete the reservation process.
            </p>
        </div>

        <div class="home-flow-grid">

            <article class="home-flow-card reveal-on-scroll">
                <span class="home-flow-number">01</span>
                <h3>Login or Register</h3>
                <p>
                    Users create an account and access the reservation dashboard securely.
                </p>
            </article>

            <article class="home-flow-card reveal-on-scroll">
                <span class="home-flow-number">02</span>
                <h3>Browse Laboratories</h3>
                <p>
                    Laboratories can be explored according to their department, type and purpose.
                </p>
            </article>

            <article class="home-flow-card reveal-on-scroll">
                <span class="home-flow-number">03</span>
                <h3>Select Station</h3>
                <p>
                    Users choose a workstation and review related equipment before reserving.
                </p>
            </article>

            <article class="home-flow-card reveal-on-scroll">
                <span class="home-flow-number">04</span>
                <h3>Check Time</h3>
                <p>
                    The selected time range is checked before the reservation is created.
                </p>
            </article>

            <article class="home-flow-card reveal-on-scroll">
                <span class="home-flow-number">05</span>
                <h3>Manage Reservation</h3>
                <p>
                    Created reservations can be viewed, edited or cancelled from the user panel.
                </p>
            </article>

        </div>

    </section>

    <!-- LIVE LABORATORY AREAS -->
    <section class="home-section home-lab-section">

        <div class="home-section-heading">
            <span class="home-section-label">
                Laboratory Areas
            </span>

            <h2>
                Explore active laboratories from the database.
            </h2>

            <p>
                Active laboratories are loaded directly from the database. Use the side arrows
                to browse more laboratory options.
            </p>
        </div>

        <?php if (!empty($featuredLabs)): ?>

            <div class="home-lab-carousel-shell">

                <button
                    type="button"
                    class="home-carousel-side-btn home-carousel-side-left"
                    data-lab-prev
                    aria-label="Previous laboratories"
                >
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M15 6L9 12L15 18"></path>
                    </svg>
                </button>

                <div class="home-lab-carousel-wrapper">

                    <div class="home-lab-carousel" data-lab-carousel>

                        <?php foreach ($featuredLabs as $lab): ?>

                            <?php
                                $labName = trim((string)($lab['lab_name'] ?? 'Laboratory'));
                                $labType = trim((string)($lab['lab_type'] ?? 'General'));
                                $description = trim((string)($lab['description'] ?? ''));

                                if ($description === '') {
                                    $description = 'This laboratory is available for academic reservation and station-based usage.';
                                }

                                if (mb_strlen($description) > 135) {
                                    $description = mb_substr($description, 0, 135) . '...';
                                }

                                $departmentName = trim((string)($lab['department_name'] ?? 'Department'));
                                $locationName = trim((string)($lab['location'] ?? ''));

                                $labTypeLabel = ucfirst($labType);
                                $labTypeShort = mb_substr($labTypeLabel, 0, 3);
                            ?>

                            <article class="home-lab-card reveal-on-scroll">

                                <div class="home-lab-card-top">
                                    <div class="home-lab-icon">
                                        <span><?= htmlspecialchars($labTypeShort, ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>

                                    <span class="home-lab-type">
                                        <?= htmlspecialchars($labTypeLabel, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </div>

                                <h3>
                                    <?= htmlspecialchars($labName, ENT_QUOTES, 'UTF-8') ?>
                                </h3>

                                <p>
                                    <?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?>
                                </p>

                                <div class="home-lab-meta">
                                    <span>
                                        <?= htmlspecialchars($departmentName, ENT_QUOTES, 'UTF-8') ?>
                                    </span>

                                    <?php if (!empty($locationName)): ?>
                                        <span>
                                            <?= htmlspecialchars($locationName, ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="home-lab-stats">
                                    <span>
                                        <?= (int) $lab['station_count'] ?> Stations
                                    </span>

                                    <span>
                                        <?= (int) $lab['equipment_count'] ?> Devices
                                    </span>
                                </div>

                                <a href="labs.php">
                                    View Details
                                    <span>→</span>
                                </a>

                            </article>

                        <?php endforeach; ?>

                    </div>

                </div>

                <button
                    type="button"
                    class="home-carousel-side-btn home-carousel-side-right"
                    data-lab-next
                    aria-label="Next laboratories"
                >
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M9 6L15 12L9 18"></path>
                    </svg>
                </button>

            </div>

        <?php else: ?>

            <div class="home-empty-labs reveal-on-scroll">
                <h3>No active laboratories found</h3>
                <p>
                    Active laboratories will be listed here when records are added to the database.
                </p>
            </div>

        <?php endif; ?>

    </section>

    <!-- CTA -->
    <section class="home-cta reveal-on-scroll">

        <div>
            <span class="home-section-label">
                Start Now
            </span>

            <h2>
                Ready to create your next laboratory reservation?
            </h2>

            <p>
                Continue with your account or explore available laboratories before reserving.
            </p>
        </div>

        <div class="home-cta-actions">

            <?php if (!$isLoggedIn): ?>

                <a href="register.php" class="home-btn home-btn-primary">
                    Create Account
                </a>

                <a href="login.php" class="home-btn home-btn-outline">
                    Login
                </a>

            <?php else: ?>

                <a href="reserve.php" class="home-btn home-btn-primary">
                    Create Reservation
                </a>

                <a href="my-reservations.php" class="home-btn home-btn-outline">
                    My Reservations
                </a>

            <?php endif; ?>

        </div>

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
        threshold: 0.15
    });

    revealItems.forEach(function (item) {
        observer.observe(item);
    });

    const labCarousel = document.querySelector('[data-lab-carousel]');
    const labPrevButton = document.querySelector('[data-lab-prev]');
    const labNextButton = document.querySelector('[data-lab-next]');

    if (labCarousel && labPrevButton && labNextButton) {
        function getScrollAmount() {
            const firstCard = labCarousel.querySelector('.home-lab-card');

            if (!firstCard) {
                return 380;
            }

            return firstCard.offsetWidth + 22;
        }

        labPrevButton.addEventListener('click', function () {
            labCarousel.scrollBy({
                left: -getScrollAmount(),
                behavior: 'smooth'
            });
        });

        labNextButton.addEventListener('click', function () {
            labCarousel.scrollBy({
                left: getScrollAmount(),
                behavior: 'smooth'
            });
        });
    }

    const tiltCard = document.querySelector('[data-tilt-card]');

    if (tiltCard) {
        tiltCard.addEventListener('mousemove', function (event) {
            const rect = tiltCard.getBoundingClientRect();
            const x = event.clientX - rect.left;
            const y = event.clientY - rect.top;

            const rotateY = ((x / rect.width) - 0.5) * 8;
            const rotateX = ((y / rect.height) - 0.5) * -8;

            tiltCard.style.transform = 'rotateX(' + rotateX + 'deg) rotateY(' + rotateY + 'deg)';
        });

        tiltCard.addEventListener('mouseleave', function () {
            tiltCard.style.transform = 'rotateX(0deg) rotateY(0deg)';
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>