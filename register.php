<?php
// =====================================================
// REGISTER PAGE (register.php)
// Creates a new customer account
// =====================================================
require_once 'config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Step 1: Get and clean the inputs
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm']  ?? '';

    // Step 2: Validate
    if ($name === '' || $email === '' || $password === '') {
        $error = 'All fields except phone are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        // Step 3: Check if email already exists (prepared statement = no SQL injection)
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'This email is already registered. Please login.';
        } else {
            // Step 4: HASH the password — never store plain text!
            // password_hash uses bcrypt by default (industry standard)
            $hashed = password_hash($password, PASSWORD_DEFAULT);

            // Step 5: Insert the new user
            $stmt = $pdo->prepare(
                "INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, 'customer')"
            );
            $stmt->execute([$name, $email, $phone, $hashed]);

            $success = 'Registration successful! You can now login.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register - Hotel Marketplace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
</head>
<body>
<div class="auth-wrap">
    <div class="auth-card" style="max-width: 500px;">
        <div class="auth-brand"><i class="bi bi-building-fill-check"></i> Hotel Marketplace</div>
        <div class="card shadow">
            <div class="card-body p-4 p-md-5">
                <h3 class="text-center mb-1">Create your account</h3>
                <p class="text-center text-muted mb-4">Join us to book your next stay</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger d-flex align-items-center"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= safe($error) ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success d-flex align-items-center"><i class="bi bi-check-circle-fill me-2"></i><?= safe($success) ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone <span class="text-muted fw-normal">(optional)</span></label>
                        <input type="text" name="phone" class="form-control">
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" name="confirm" class="form-control" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-person-plus me-1"></i>Register</button>
                </form>
                <p class="text-center mt-4 mb-0 text-muted">
                    Already have an account? <a href="login.php" class="fw-semibold text-decoration-none">Login here</a>
                </p>
            </div>
        </div>
        <p class="text-center mt-3"><a href="index.php" class="text-muted text-decoration-none small"><i class="bi bi-arrow-left"></i> Back to home</a></p>
    </div>
</div>
</body>
</html>
