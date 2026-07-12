<?php
// =====================================================
// LOGIN PAGE (login.php)
// Authenticates a user and starts a session
// =====================================================
require_once 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Email and password are required.';
    } else {
        // Look up the user by email (prepared statement)
        $stmt = $pdo->prepare("SELECT id, name, password, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // password_verify compares the entered password to the stored bcrypt hash
        if ($user && password_verify($password, $user['password'])) {
            // Login successful — store user info in the session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['role']    = $user['role'];

            // Redirect based on role
            if ($user['role'] === 'admin')      header('Location: admin_dashboard.php');
            elseif ($user['role'] === 'manager') header('Location: manager_dashboard.php');
            else                                 header('Location: index.php');
            exit;
        } else {
            // Same message for "wrong email" or "wrong password"
            // so attackers can't tell which emails exist
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Hotel Marketplace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
</head>
<body>
<div class="auth-wrap">
    <div class="auth-card">
        <div class="auth-brand"><i class="bi bi-building-fill-check"></i> Hotel Marketplace</div>
        <div class="card shadow">
            <div class="card-body p-4 p-md-5">
                <h3 class="text-center mb-1">Welcome back</h3>
                <p class="text-center text-muted mb-4">Sign in to continue</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger d-flex align-items-center"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= safe($error) ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-envelope"></i></span>
                            <input type="email" name="email" class="form-control" placeholder="you@example.com" required>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-lock"></i></span>
                            <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-box-arrow-in-right me-1"></i>Login</button>
                </form>
                <p class="text-center mt-4 mb-0 text-muted">
                    No account? <a href="register.php" class="fw-semibold text-decoration-none">Register here</a>
                </p>
            </div>
        </div>
        <p class="text-center mt-3"><a href="index.php" class="text-muted text-decoration-none small"><i class="bi bi-arrow-left"></i> Back to home</a></p>
    </div>
</div>
</body>
</html>
