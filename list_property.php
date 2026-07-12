<?php
// =====================================================
// LIST YOUR PROPERTY (list_property.php)
// Booking.com-style partner onboarding.
// A property owner signs up (creates a 'manager' account)
// and is sent straight to add their first hotel.
// =====================================================
require_once 'config.php';

$error = '';

// Already logged in? Send them to the right next step.
if (is_logged_in()) {
    if ($_SESSION['role'] === 'manager') {
        header('Location: add_hotel.php');
        exit;
    }
    // A logged-in customer can upgrade to a host (see add_hotel.php).
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm']  ?? '';

    if ($name === '' || $email === '' || $password === '') {
        $error = 'Name, email and password are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'This email is already registered. Please <a href="login.php">sign in</a> instead.';
        } else {
            // Create a MANAGER (property partner) account
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare(
                "INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, 'manager')"
            );
            $stmt->execute([$name, $email, $phone, $hashed]);

            // Log them in and send them to add their first hotel
            $_SESSION['user_id'] = $pdo->lastInsertId();
            $_SESSION['name']    = $name;
            $_SESSION['role']    = 'manager';
            header('Location: add_hotel.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>List Your Property - Hotel Marketplace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="index.php"><i class="bi bi-building-fill-check me-1"></i> Hotel Marketplace</a>
        <div class="d-flex align-items-center gap-2">
            <?php if (is_logged_in()): ?>
                <span class="text-light d-none d-sm-inline">Hi, <?= safe($_SESSION['name']) ?></span>
                <a href="logout.php" class="btn btn-outline-warning btn-sm"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
            <?php else: ?>
                <a href="login.php" class="btn btn-outline-light btn-sm">Sign in</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<section class="hero">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-lg-6 text-start">
                <h1 class="display-5 fw-bold">List your property on Hotel Marketplace</h1>
                <p class="lead mb-4">Reach travellers heading to Moshi, Arusha and beyond. Create your listing in minutes — it's free to get started.</p>
                <ul class="list-unstyled fs-5">
                    <li class="mb-2"><i class="bi bi-check-circle-fill text-warning me-2"></i>Free to list — no upfront fees</li>
                    <li class="mb-2"><i class="bi bi-check-circle-fill text-warning me-2"></i>Manage rooms, prices &amp; availability</li>
                    <li class="mb-2"><i class="bi bi-check-circle-fill text-warning me-2"></i>Track every booking from one dashboard</li>
                    <li class="mb-2"><i class="bi bi-check-circle-fill text-warning me-2"></i>Get paid in TZS, set your own rates</li>
                </ul>
            </div>
            <div class="col-lg-6">
                <div class="card shadow auth-card mx-auto" style="max-width:460px;">
                    <div class="card-body p-4 p-md-5">
                        <?php if (is_logged_in()): ?>
                            <h3 class="mb-2">Welcome back!</h3>
                            <p class="text-muted mb-4">You're signed in as <strong><?= safe($_SESSION['name']) ?></strong>. Continue to add your property.</p>
                            <a href="add_hotel.php" class="btn btn-warning w-100 btn-lg"><i class="bi bi-plus-circle me-1"></i>Add my property</a>
                        <?php else: ?>
                            <h3 class="mb-1">Become a host</h3>
                            <p class="text-muted mb-4">Create your free partner account</p>

                            <?php if ($error): ?>
                                <div class="alert alert-danger d-flex align-items-center"><i class="bi bi-exclamation-triangle-fill me-2"></i><span><?= $error /* may contain a safe link */ ?></span></div>
                            <?php endif; ?>

                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Your full name</label>
                                    <input type="text" name="name" class="form-control" value="<?= safe($_POST['name'] ?? '') ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" value="<?= safe($_POST['email'] ?? '') ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Phone <span class="text-muted fw-normal">(optional)</span></label>
                                    <input type="text" name="phone" class="form-control" value="<?= safe($_POST['phone'] ?? '') ?>">
                                </div>
                                <div class="row g-2">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Password</label>
                                        <input type="password" name="password" class="form-control" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Confirm</label>
                                        <input type="password" name="confirm" class="form-control" required>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-warning w-100 btn-lg"><i class="bi bi-arrow-right-circle me-1"></i>Get started</button>
                            </form>
                            <p class="text-center mt-3 mb-0 text-muted small">
                                Already a partner? <a href="login.php" class="fw-semibold text-decoration-none">Sign in</a>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<main class="container my-5">
    <h2 class="section-title text-center mb-4">How it works</h2>
    <div class="row g-4 text-center">
        <div class="col-md-4">
            <div class="card hotel-card h-100"><div class="card-body p-4">
                <i class="bi bi-person-plus-fill text-primary" style="font-size:2.5rem;"></i>
                <h5 class="mt-3">1. Create your account</h5>
                <p class="text-secondary small mb-0">Sign up as a partner in under a minute — completely free.</p>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card hotel-card h-100"><div class="card-body p-4">
                <i class="bi bi-building-add text-primary" style="font-size:2.5rem;"></i>
                <h5 class="mt-3">2. Add your property</h5>
                <p class="text-secondary small mb-0">Describe your hotel, add photos, location, website and contact details.</p>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card hotel-card h-100"><div class="card-body p-4">
                <i class="bi bi-calendar2-check-fill text-primary" style="font-size:2.5rem;"></i>
                <h5 class="mt-3">3. Start receiving bookings</h5>
                <p class="text-secondary small mb-0">Set your rooms and prices, then manage every reservation in your dashboard.</p>
            </div></div>
        </div>
    </div>
</main>

<footer class="site-footer">
    Hotel Marketplace System &copy; <?= date('Y') ?> — Judith Antoni Obedi
</footer>
</body>
</html>
