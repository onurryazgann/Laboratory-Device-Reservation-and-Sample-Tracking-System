<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/auth_helper.php';

$pageTitle = 'Home';
$pageCss = 'index.css';

require_once __DIR__ . '/../includes/header.php';

?>

<section class="page-section index-page">

    <div class="container">

        <!-- HERO -->
        <div class="index-hero-card card">

            <div class="index-hero-grid">

                <div class="index-hero-content">

                    <span class="badge badge-info">
                        Laboratory Reservation System
                    </span>

                    <h1 class="index-hero-title">
                        Laboratory Reservation & Station Management System
                    </h1>

                    <p class="index-hero-description">
                        Browse laboratories, choose suitable workstations, review assigned
                        equipment and manage your academic reservations through a clean,
                        structured and easy-to-use system.
                    </p>

                    <div class="index-hero-actions">

                        <?php if (!isLoggedIn()): ?>
                            <a href="login.php" class="btn btn-primary">
                                Login
                            </a>

                            <a href="register.php" class="btn btn-secondary">
                                Register
                            </a>
                        <?php else: ?>
                            <a href="dashboard.php" class="btn btn-primary">
                                Dashboard
                            </a>

                            <a href="reserve.php" class="btn btn-secondary">
                                New Reservation
                            </a>
                        <?php endif; ?>

                        <a href="labs.php" class="btn btn-outline">
                            Explore Laboratories
                        </a>

                    </div>

                </div>

                <div class="index-workflow-card">

                    <div class="index-workflow-header">
                        <span class="badge badge-success">
                            How It Works
                        </span>

                        <h2>
                            Reservation Workflow
                        </h2>

                        <p>
                            Follow a simple path from selecting a laboratory to managing
                            your reservation.
                        </p>
                    </div>

                    <div class="index-workflow-list">

                        <div class="index-workflow-item">
                            <span>1</span>
                            <div>
                                <strong>Register / Login</strong>
                                <p>Create an account or access your dashboard.</p>
                            </div>
                        </div>

                        <div class="index-workflow-item">
                            <span>2</span>
                            <div>
                                <strong>Choose Laboratory</strong>
                                <p>Browse laboratories by department and type.</p>
                            </div>
                        </div>

                        <div class="index-workflow-item">
                            <span>3</span>
                            <div>
                                <strong>Select Station</strong>
                                <p>Choose an active workstation or experiment station.</p>
                            </div>
                        </div>

                        <div class="index-workflow-item">
                            <span>4</span>
                            <div>
                                <strong>Check Availability</strong>
                                <p>Check whether the selected date and time are available.</p>
                            </div>
                        </div>

                        <div class="index-workflow-item">
                            <span>5</span>
                            <div>
                                <strong>Create Reservation</strong>
                                <p>Create and manage your reservation from your account.</p>
                            </div>
                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

</section>

<section class="page-section-sm">
    <div class="container">

        <!-- FEATURED LABS -->
        <div class="index-section-header">

            <span class="badge badge-info">
                Laboratory Categories
            </span>

            <h2 class="section-title">
                Featured Laboratory Categories
            </h2>

            <p class="section-subtitle">
                Explore different laboratory environments and choose a suitable station
                for your reservation.
            </p>

        </div>

        <div class="index-category-grid">

            <article class="card card-hover index-category-card">
                <div class="index-category-icon">
                    PC
                </div>

                <h3>
                    Computer Labs
                </h3>

                <p>
                    Computer desks and software-focused workstations for academic
                    study and project work.
                </p>

                <a href="labs.php?lab_type=computer" class="btn btn-outline">
                    View Computer Labs
                </a>
            </article>

            <article class="card card-hover index-category-card">
                <div class="index-category-icon">
                    NET
                </div>

                <h3>
                    Network Labs
                </h3>

                <p>
                    Network stations with equipment such as routers, switches and
                    computers for applied practice.
                </p>

                <a href="labs.php?lab_type=network" class="btn btn-outline">
                    View Network Labs
                </a>
            </article>

            <article class="card card-hover index-category-card">
                <div class="index-category-icon">
                    ENG
                </div>

                <h3>
                    Electronics / Machine Labs
                </h3>

                <p>
                    Electronics benches, machine stations and technical workstations
                    for engineering applications.
                </p>

                <a href="labs.php" class="btn btn-outline">
                    Explore All Labs
                </a>
            </article>

        </div>

    </div>
</section>

<section class="page-section-sm">
    <div class="container">

        <!-- MAIN FEATURES -->
        <div class="index-section-header">

            <span class="badge badge-info">
                System Features
            </span>

            <h2 class="section-title">
                Main Features
            </h2>

            <p class="section-subtitle">
                Everything students need to browse laboratories, select stations
                and manage reservations.
            </p>

        </div>

        <div class="index-feature-grid">

            <div class="card index-feature-card">
                <span class="badge badge-success">
                    Reservations
                </span>

                <h3>
                    Reservation Management
                </h3>

                <ul>
                    <li>View active laboratories and stations</li>
                    <li>Check date and time availability</li>
                    <li>Create new reservations</li>
                    <li>View, edit and cancel your reservations</li>
                </ul>
            </div>

            <div class="card index-feature-card">
                <span class="badge badge-info">
                    Laboratories
                </span>

                <h3>
                    Laboratory Browsing
                </h3>

                <ul>
                    <li>Browse laboratories by category</li>
                    <li>View laboratory details</li>
                    <li>See available stations</li>
                    <li>Review station capacity and status</li>
                </ul>
            </div>

            <div class="card index-feature-card">
                <span class="badge badge-warning">
                    Equipment
                </span>

                <h3>
                    Equipment Review
                </h3>

                <ul>
                    <li>View equipment assigned to stations</li>
                    <li>Check station-related resources</li>
                    <li>Review equipment before reservation</li>
                    <li>Choose the most suitable station</li>
                </ul>
            </div>

            <div class="card index-feature-card">
                <span class="badge badge-success">
                    Account
                </span>

                <h3>
                    Personal Reservation Area
                </h3>

                <ul>
                    <li>Create an account and sign in</li>
                    <li>Access your dashboard</li>
                    <li>Track your active reservations</li>
                    <li>Manage your reservation history</li>
                </ul>
            </div>

        </div>

    </div>
</section>

<section class="page-section-sm">
    <div class="container">

        <!-- CTA -->
        <div class="card index-cta-card">

            <span class="badge badge-info">
                Start Now
            </span>

            <h2 class="section-title">
                Start Your Reservation Journey
            </h2>

            <p class="section-subtitle">
                Simple, organized and reliable laboratory reservation management.
            </p>

            <div class="index-cta-actions">

                <?php if (!isLoggedIn()): ?>
                    <a href="register.php" class="btn btn-primary">
                        Create Account
                    </a>

                    <a href="login.php" class="btn btn-outline">
                        Login
                    </a>
                <?php else: ?>
                    <a href="reserve.php" class="btn btn-primary">
                        Create Reservation
                    </a>

                    <a href="my-reservations.php" class="btn btn-outline">
                        My Reservations
                    </a>
                <?php endif; ?>

            </div>

        </div>

    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>