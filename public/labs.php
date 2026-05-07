<?php

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/lab_helper.php';

$pageTitle = 'Laboratories';
$pageCss = 'labs.css';
$pageJs = 'labs.js';
$bodyClass = 'page-labs';

$filters = [
    'q' => trim($_GET['q'] ?? ''),
    'faculty_id' => $_GET['faculty_id'] ?? '',
    'department_id' => $_GET['department_id'] ?? '',
    'lab_type' => trim($_GET['lab_type'] ?? '')
];

$faculties = getActiveFaculties($pdo);
$departments = getActiveDepartments($pdo);
$labTypes = getLabTypes($pdo);
$labs = getAllLabs($pdo, $filters);

function isSelectedOption($currentValue, $expectedValue): string
{
    return (string) $currentValue === (string) $expectedValue ? 'selected' : '';
}

function formatLabTypeLabel(string $type): string
{
    return ucwords(str_replace('_', ' ', $type));
}

require_once __DIR__ . '/../includes/header.php';

?>

<section class="labs-page" data-labs-page="true">

    <!-- HERO -->
    <section class="labs-hero" data-labs-tilt-card>

        <div class="labs-hero-content">

            <span class="labs-eyebrow">
                Laboratory Directory
            </span>

            <h1>
                Explore laboratories and find the right station for your reservation.
            </h1>

            <p>
                Compare departments, laboratory types and station availability before creating
                a reservation. Use filters to quickly find the most suitable environment.
            </p>

            <div class="labs-hero-actions">
                <a href="reserve.php" class="labs-btn labs-btn-primary">
                    New Reservation
                </a>

                <a href="my-reservations.php" class="labs-btn labs-btn-light">
                    My Reservations
                </a>
            </div>

        </div>

        <div class="labs-hero-visual">

            <div class="labs-mini-panel">

                <div class="labs-mini-header">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>

                <div class="labs-mini-body">

                    <div class="labs-mini-title">
                        <div>
                            <small>Directory Status</small>
                            <strong>Laboratory Overview</strong>
                        </div>

                        <span>
                            Live
                        </span>
                    </div>

                    <div class="labs-mini-list">

                        <div class="labs-mini-item">
                            <span>01</span>
                            <div>
                                <strong>Search Laboratories</strong>
                                <small>Find laboratories by name, code or location.</small>
                            </div>
                        </div>

                        <div class="labs-mini-item">
                            <span>02</span>
                            <div>
                                <strong>Compare Stations</strong>
                                <small>Review active and total station availability.</small>
                            </div>
                        </div>

                        <div class="labs-mini-item">
                            <span>03</span>
                            <div>
                                <strong>Start Reservation</strong>
                                <small>Continue directly to the reservation process.</small>
                            </div>
                        </div>

                    </div>

                </div>

            </div>

            <div class="labs-floating-chip labs-chip-one">
                <span>✓</span>
                Available Labs
            </div>

            <div class="labs-floating-chip labs-chip-two">
                <span>⌕</span>
                Smart Filter
            </div>

            <div class="labs-floating-chip labs-chip-three">
                <span>↗</span>
                Reserve Fast
            </div>

        </div>

    </section>

    <!-- FILTER -->
    <section class="labs-filter-card reveal-on-scroll">

        <div class="labs-filter-header">
            <div>
                <span class="labs-section-label">
                    Search & Filter
                </span>

                <h2>
                    Find a suitable laboratory.
                </h2>

                <p>
                    Search by laboratory name, code, faculty, department, type or location.
                </p>
            </div>

            <span class="labs-filter-badge" id="labsFilterModeBadge">
                Dynamic Filter Ready
            </span>
        </div>

        <form method="GET" action="" id="labsFilterForm">

            <div class="labs-filter-grid">

                <div class="labs-form-group">
                    <label for="q">Search</label>

                    <input
                        type="text"
                        id="q"
                        name="q"
                        value="<?= htmlspecialchars($filters['q'], ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="Search by laboratory, code, faculty or department"
                        autocomplete="off"
                    >

                    <small>
                        You can search by lab name, lab code, faculty, department or location.
                    </small>
                </div>

                <div class="labs-form-group">
                    <label for="lab_type">Laboratory Type</label>

                    <select id="lab_type" name="lab_type">
                        <option value="">All types</option>

                        <?php foreach ($labTypes as $type): ?>
                            <option
                                value="<?= htmlspecialchars($type['lab_type'], ENT_QUOTES, 'UTF-8') ?>"
                                <?= isSelectedOption($filters['lab_type'], $type['lab_type']) ?>
                            >
                                <?= htmlspecialchars(formatLabTypeLabel($type['lab_type']), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

            </div>

            <div class="labs-filter-grid">

                <div class="labs-form-group">
                    <label for="faculty_id">Faculty</label>

                    <select id="faculty_id" name="faculty_id">
                        <option value="">All faculties</option>

                        <?php foreach ($faculties as $faculty): ?>
                            <option
                                value="<?= (int) $faculty['faculty_id'] ?>"
                                <?= isSelectedOption($filters['faculty_id'], $faculty['faculty_id']) ?>
                            >
                                <?= htmlspecialchars($faculty['faculty_name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="labs-form-group">
                    <label for="department_id">Department</label>

                    <select id="department_id" name="department_id">
                        <option value="">All departments</option>

                        <?php foreach ($departments as $department): ?>
                            <option
                                value="<?= (int) $department['department_id'] ?>"
                                data-faculty-id="<?= (int) $department['faculty_id'] ?>"
                                <?= isSelectedOption($filters['department_id'], $department['department_id']) ?>
                            >
                                <?= htmlspecialchars($department['department_name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <small>
                        Department options can be filtered by selected faculty.
                    </small>
                </div>

            </div>

            <div class="labs-filter-actions">
                <button type="submit" class="labs-btn labs-btn-primary">
                    Apply Filters
                </button>

                <button type="button" class="labs-btn labs-btn-light" id="labsClientFilterButton">
                    Filter on Page
                </button>

                <a href="labs.php" class="labs-btn labs-btn-outline">
                    Clear Filters
                </a>
            </div>

        </form>

    </section>

    <!-- LAB CAROUSEL -->
    <section class="labs-carousel-section reveal-on-scroll">

        <div class="labs-carousel-header">
            <div>
                <span class="labs-section-label">
                    Laboratory List
                </span>

                <h2>
                    Browse available laboratories.
                </h2>

                <p>
                    Move sideways to review laboratories without crowding the page.
                </p>
            </div>
        </div>

        <div class="labs-carousel-shell">

            <button
                type="button"
                class="labs-carousel-side-btn labs-carousel-side-left"
                data-labs-prev
                aria-label="Previous laboratories"
            >
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M15 6L9 12L15 18"></path>
                </svg>
            </button>

            <section
                class="labs-grid"
                id="labsGrid"
                data-total-labs="<?= count($labs) ?>"
            >

                <?php if (count($labs) > 0): ?>

                    <?php foreach ($labs as $lab): ?>
                        <?php
                        $activeStationCount = (int) ($lab['active_station_count'] ?? 0);
                        $totalStationCount = (int) ($lab['total_station_count'] ?? 0);
                        $isAvailable = $activeStationCount > 0;

                        $searchText = strtolower(
                            ($lab['lab_name'] ?? '') . ' '
                            . ($lab['lab_code'] ?? '') . ' '
                            . ($lab['faculty_name'] ?? '') . ' '
                            . ($lab['department_name'] ?? '') . ' '
                            . ($lab['lab_type'] ?? '') . ' '
                            . ($lab['location'] ?? '')
                        );

                        $labTypeLabel = formatLabTypeLabel((string) $lab['lab_type']);
                        $labTypeShort = mb_substr($labTypeLabel, 0, 3);
                        ?>

                        <article
                            class="lab-card reveal-on-scroll"
                            data-lab-card="true"
                            data-search="<?= htmlspecialchars($searchText, ENT_QUOTES, 'UTF-8') ?>"
                            data-faculty-id="<?= (int) $lab['faculty_id'] ?>"
                            data-department-id="<?= (int) $lab['department_id'] ?>"
                            data-lab-type="<?= htmlspecialchars($lab['lab_type'], ENT_QUOTES, 'UTF-8') ?>"
                            data-active-stations="<?= $activeStationCount ?>"
                        >

                            <div class="lab-card-top">
                                <div class="lab-type-icon">
                                    <span>
                                        <?= htmlspecialchars($labTypeShort, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </div>

                                <span class="lab-availability-badge <?= $isAvailable ? 'is-available' : 'is-unavailable' ?>">
                                    <?= $isAvailable ? 'Available' : 'No Active Station' ?>
                                </span>
                            </div>

                            <div class="lab-code-row">
                                <span>
                                    <?= htmlspecialchars($lab['lab_code'], ENT_QUOTES, 'UTF-8') ?>
                                </span>

                                <strong>
                                    <?= htmlspecialchars($labTypeLabel, ENT_QUOTES, 'UTF-8') ?>
                                </strong>
                            </div>

                            <h3>
                                <?= htmlspecialchars($lab['lab_name'], ENT_QUOTES, 'UTF-8') ?>
                            </h3>

                            <div class="lab-card-meta">

                                <div class="lab-meta-pill">
                                    <span>Faculty</span>
                                    <strong><?= htmlspecialchars($lab['faculty_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                </div>

                                <div class="lab-meta-pill">
                                    <span>Department</span>
                                    <strong><?= htmlspecialchars($lab['department_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                </div>

                                <div class="lab-meta-pill">
                                    <span>Location</span>
                                    <strong><?= htmlspecialchars($lab['location'] ?? '-', ENT_QUOTES, 'UTF-8') ?></strong>
                                </div>

                            </div>

                            <div class="lab-station-summary">
                                <div>
                                    <span class="lab-station-number">
                                        <?= $activeStationCount ?>
                                    </span>

                                    <span class="lab-station-label">
                                        active stations
                                    </span>
                                </div>

                                <div>
                                    <span class="lab-station-number muted">
                                        <?= $totalStationCount ?>
                                    </span>

                                    <span class="lab-station-label">
                                        total stations
                                    </span>
                                </div>
                            </div>

                            <div class="lab-card-actions">
                                <a
                                    href="lab-detail.php?id=<?= (int) $lab['lab_id'] ?>"
                                    class="labs-card-btn labs-card-btn-primary"
                                >
                                    View Details
                                </a>

                                <a
                                    href="reserve.php?lab_id=<?= (int) $lab['lab_id'] ?>"
                                    class="labs-card-btn labs-card-btn-outline"
                                >
                                    Reserve
                                </a>
                            </div>

                        </article>

                    <?php endforeach; ?>

                <?php endif; ?>

            </section>

            <button
                type="button"
                class="labs-carousel-side-btn labs-carousel-side-right"
                data-labs-next
                aria-label="Next laboratories"
            >
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M9 6L15 12L9 18"></path>
                </svg>
            </button>

        </div>

    </section>

    <!-- EMPTY STATE -->
    <section
        class="labs-empty-state reveal-on-scroll"
        id="labsEmptyState"
        style="<?= count($labs) > 0 ? 'display:none;' : '' ?>"
    >
        <span class="labs-filter-badge is-warning">
            No Result
        </span>

        <h3>
            No laboratory found
        </h3>

        <p>
            Try changing your filters or search terms.
        </p>

        <a href="labs.php" class="labs-btn labs-btn-primary">
            Clear Filters
        </a>
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

    /*
    |--------------------------------------------------------------------------
    | Laboratory Carousel Buttons
    |--------------------------------------------------------------------------
    */

    const labsCarousel = document.getElementById('labsGrid');
    const labsPrevButton = document.querySelector('[data-labs-prev]');
    const labsNextButton = document.querySelector('[data-labs-next]');

    if (labsCarousel && labsPrevButton && labsNextButton) {
        function getLabsScrollAmount() {
            const visibleCard = labsCarousel.querySelector('.lab-card:not([style*="display: none"])');

            if (!visibleCard) {
                return 400;
            }

            const carouselStyle = window.getComputedStyle(labsCarousel);
            const gap = parseInt(carouselStyle.columnGap || carouselStyle.gap || '22', 10);

            return visibleCard.offsetWidth + gap;
        }

        labsPrevButton.addEventListener('click', function () {
            labsCarousel.scrollBy({
                left: -getLabsScrollAmount(),
                behavior: 'smooth'
            });
        });

        labsNextButton.addEventListener('click', function () {
            labsCarousel.scrollBy({
                left: getLabsScrollAmount(),
                behavior: 'smooth'
            });
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Hero Tilt Effect
    |--------------------------------------------------------------------------
    */

    const labsTiltCard = document.querySelector('[data-labs-tilt-card]');

    if (labsTiltCard) {
        labsTiltCard.addEventListener('pointermove', function (event) {
            const rect = labsTiltCard.getBoundingClientRect();

            const x = event.clientX - rect.left;
            const y = event.clientY - rect.top;

            const rotateY = ((x / rect.width) - 0.5) * 5;
            const rotateX = ((y / rect.height) - 0.5) * -5;

            labsTiltCard.style.transform =
                'perspective(1200px) rotateX(' + rotateX + 'deg) rotateY(' + rotateY + 'deg)';
        });

        labsTiltCard.addEventListener('pointerleave', function () {
            labsTiltCard.style.transform =
                'perspective(1200px) rotateX(0deg) rotateY(0deg)';
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>