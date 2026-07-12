<?php
// =====================================================
// ADMIN DASHBOARD (admin_dashboard.php)
// System administrator: view all users, hotels, bookings, reports
// =====================================================
require_once 'config.php';
require_role('admin');

$message = '';

$error = '';
$valid_roles = ['customer', 'manager', 'admin'];

// Admin can manage everything — including deleting any hotel.
// Deleting a hotel cascades to its rooms and bookings (see schema FKs).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_hotel'])) {
    $stmt = $pdo->prepare("DELETE FROM hotels WHERE id = ?");
    $stmt->execute([(int)$_POST['delete_hotel']]);
    $message = 'Hotel deleted (including its rooms and bookings).';
}

// ---- USER MANAGEMENT (full CRUD over all users, including managers) ----

// CREATE a new user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $name  = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role  = in_array($_POST['role'] ?? '', $valid_roles, true) ? $_POST['role'] : 'customer';
    $pass  = $_POST['password'] ?? '';

    if ($name === '' || $email === '' || $pass === '') {
        $error = 'Name, email and password are required to add a user.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($pass) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $dup = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $dup->execute([$email]);
        if ($dup->fetch()) {
            $error = 'That email is already registered.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, role, password) VALUES (?,?,?,?,?)");
            $stmt->execute([$name, $email, $phone, $role, password_hash($pass, PASSWORD_DEFAULT)]);
            $message = 'User created.';
        }
    }
}

// UPDATE an existing user (name, email, phone, role, optional new password)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $uid   = (int)$_POST['update_user'];
    $name  = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role  = in_array($_POST['role'] ?? '', $valid_roles, true) ? $_POST['role'] : 'customer';
    $pass  = $_POST['password'] ?? '';

    if ($name === '' || $email === '') {
        $error = 'Name and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Don't let an admin demote/lock out their own account by accident
        if ($uid === (int)$_SESSION['user_id'] && $role !== 'admin') {
            $role = 'admin';
            $message = 'Note: your own account was kept as admin. ';
        }
        $dup = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id <> ?");
        $dup->execute([$email, $uid]);
        if ($dup->fetch()) {
            $error = 'Another user already uses that email.';
        } else {
            if ($pass !== '') {
                if (strlen($pass) < 6) {
                    $error = 'New password must be at least 6 characters.';
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET name=?, email=?, phone=?, role=?, password=? WHERE id=?");
                    $stmt->execute([$name, $email, $phone, $role, password_hash($pass, PASSWORD_DEFAULT), $uid]);
                    $message .= 'User updated (password reset).';
                }
            } else {
                $stmt = $pdo->prepare("UPDATE users SET name=?, email=?, phone=?, role=? WHERE id=?");
                $stmt->execute([$name, $email, $phone, $role, $uid]);
                $message .= 'User updated.';
            }
            // Keep the live session in sync if the admin edited themselves
            if ($uid === (int)$_SESSION['user_id'] && !$error) {
                $_SESSION['name'] = $name;
                $_SESSION['role'] = $role;
            }
        }
    }
}

// DELETE a user (cannot delete your own account; cascades hotels/bookings)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $uid = (int)$_POST['delete_user'];
    if ($uid === (int)$_SESSION['user_id']) {
        $error = 'You cannot delete your own account while logged in.';
    } else {
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$uid]);
        $message = 'User deleted (their hotels and bookings were removed too).';
    }
}

// Which user (if any) is being edited inline
$edit_user = (int)($_GET['edit_user'] ?? 0);

// Statistics
$stats = [
    'users'    => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'hotels'   => $pdo->query("SELECT COUNT(*) FROM hotels")->fetchColumn(),
    'rooms'    => $pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn(),
    'bookings' => $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn(),
    'revenue'  => $pdo->query("SELECT COALESCE(SUM(total_price),0) FROM bookings WHERE status='confirmed'")->fetchColumn(),
];

// Recent bookings
$recent = $pdo->query("
    SELECT b.*, u.name AS customer, h.name AS hotel
    FROM bookings b
    JOIN users u  ON b.user_id = u.id
    JOIN rooms r  ON b.room_id = r.id
    JOIN hotels h ON r.hotel_id = h.id
    ORDER BY b.created_at DESC LIMIT 10
")->fetchAll();

// All users
$users = $pdo->query("SELECT id, name, email, phone, role, created_at FROM users ORDER BY created_at DESC")->fetchAll();

// All hotels with their owner and room count (admin manages every one)
$hotels = $pdo->query("
    SELECT h.*, u.name AS owner_name, u.email AS owner_email,
           (SELECT COUNT(*) FROM rooms r WHERE r.hotel_id = h.id AND r.is_active = 1) AS room_count
    FROM hotels h JOIN users u ON h.manager_id = u.id
    ORDER BY h.location, h.name
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-dark bg-danger">
    <div class="container">
        <a class="navbar-brand"><i class="bi bi-gear-fill me-1"></i> Admin Dashboard</a>
        <a href="logout.php" class="btn btn-outline-light btn-sm"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
    </div>
</nav>

<main class="container my-4">
    <?php if ($message): ?>
        <div class="alert alert-success d-flex align-items-center"><i class="bi bi-check-circle-fill me-2"></i><?= safe($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger d-flex align-items-center"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= safe($error) ?></div>
    <?php endif; ?>
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card stat-card" style="background:linear-gradient(135deg,#1e3c72,#2a5298);">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div><div class="stat-label">Users</div><p class="stat-value"><?= $stats['users'] ?></p></div>
                    <i class="bi bi-people-fill"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card stat-card" style="background:linear-gradient(135deg,#0891b2,#06b6d4);">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div><div class="stat-label">Hotels</div><p class="stat-value"><?= $stats['hotels'] ?></p></div>
                    <i class="bi bi-buildings-fill"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card stat-card" style="background:linear-gradient(135deg,#d97706,#f59e0b);">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div><div class="stat-label">Bookings</div><p class="stat-value"><?= $stats['bookings'] ?></p></div>
                    <i class="bi bi-calendar-check-fill"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card stat-card" style="background:linear-gradient(135deg,#15803d,#22c55e);">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div><div class="stat-label">Revenue</div><p class="stat-value fs-5">TZS <?= number_format($stats['revenue']) ?></p></div>
                    <i class="bi bi-cash-stack"></i>
                </div>
            </div>
        </div>
    </div>

    <h3 class="section-title">Recent Bookings</h3>
    <div class="table-wrap mb-4">
    <table class="table align-middle mb-0">
        <thead><tr>
            <th>ID</th><th>Customer</th><th>Hotel</th>
            <th>Dates</th><th>Total</th><th>Status</th>
        </tr></thead>
        <tbody>
        <?php foreach ($recent as $b): ?>
            <tr>
                <td>#<?= (int)$b['id'] ?></td>
                <td><?= safe($b['customer']) ?></td>
                <td><?= safe($b['hotel']) ?></td>
                <td><?= safe($b['check_in']) ?> <i class="bi bi-arrow-right text-muted"></i> <?= safe($b['check_out']) ?></td>
                <td class="fw-semibold">TZS <?= number_format($b['total_price']) ?></td>
                <td><span class="badge bg-<?= $b['status']==='confirmed'?'success':($b['status']==='cancelled'?'danger':'warning') ?>"><?= safe(ucfirst($b['status'])) ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <h3 class="section-title">All Hotels <span class="text-muted fs-6">(<?= count($hotels) ?>)</span></h3>
    <div class="table-wrap mb-4">
    <table class="table align-middle mb-0">
        <thead><tr>
            <th>ID</th><th>Hotel</th><th>Location</th><th>Owner</th><th>Rooms</th><th>Manage</th>
        </tr></thead>
        <tbody>
        <?php if (empty($hotels)): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">No hotels listed yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($hotels as $h): ?>
            <tr>
                <td>#<?= (int)$h['id'] ?></td>
                <td class="fw-semibold"><?= safe($h['name']) ?></td>
                <td><i class="bi bi-geo-alt-fill text-danger me-1"></i><?= safe($h['location']) ?></td>
                <td><?= safe($h['owner_name']) ?><br><small class="text-muted"><?= safe($h['owner_email']) ?></small></td>
                <td><span class="badge bg-light text-dark border"><?= (int)$h['room_count'] ?></span></td>
                <td class="d-flex gap-2">
                    <a href="manage_rooms.php?hotel_id=<?= (int)$h['id'] ?>" class="btn btn-sm btn-primary"><i class="bi bi-door-open me-1"></i>Rooms</a>
                    <form method="POST" onsubmit="return confirm('Delete &quot;<?= safe(addslashes($h['name'])) ?>&quot;? This also removes its rooms and bookings.');">
                        <input type="hidden" name="delete_hotel" value="<?= (int)$h['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <h3 class="section-title">All Users <span class="text-muted fs-6">(<?= count($users) ?>)</span></h3>

    <div class="card mb-4">
        <div class="card-body p-4">
            <h5 class="mb-3"><i class="bi bi-person-plus me-1 text-success"></i>Add New User</h5>
            <form method="POST" class="row g-2 align-items-end">
                <div class="col-md-3"><label class="form-label small">Name</label><input class="form-control" name="name" required></div>
                <div class="col-md-3"><label class="form-label small">Email</label><input class="form-control" name="email" type="email" required></div>
                <div class="col-md-2"><label class="form-label small">Phone</label><input class="form-control" name="phone"></div>
                <div class="col-md-2"><label class="form-label small">Role</label>
                    <select class="form-select" name="role">
                        <option value="customer">Customer</option>
                        <option value="manager">Manager</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="col-md-1"><label class="form-label small">Password</label><input class="form-control" name="password" type="text" placeholder="min 6" required></div>
                <div class="col-md-1"><button name="add_user" class="btn btn-success w-100"><i class="bi bi-plus-lg"></i></button></div>
            </form>
        </div>
    </div>

    <div class="table-wrap">
    <table class="table align-middle mb-0">
        <thead><tr>
            <th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Joined</th><th>Actions</th>
        </tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <?php if ($edit_user === (int)$u['id']): ?>
                <!-- Inline edit row -->
                <tr class="table-warning">
                    <form method="POST">
                        <input type="hidden" name="update_user" value="<?= (int)$u['id'] ?>">
                        <td>#<?= (int)$u['id'] ?></td>
                        <td><input class="form-control form-control-sm" name="name" value="<?= safe($u['name']) ?>" required></td>
                        <td><input class="form-control form-control-sm" name="email" type="email" value="<?= safe($u['email']) ?>" required></td>
                        <td><input class="form-control form-control-sm" name="phone" value="<?= safe($u['phone']) ?>" style="max-width:130px;"></td>
                        <td>
                            <select class="form-select form-select-sm" name="role" style="max-width:120px;">
                                <?php foreach ($valid_roles as $role): ?>
                                    <option value="<?= $role ?>" <?= $u['role']===$role?'selected':'' ?>><?= ucfirst($role) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input class="form-control form-control-sm" name="password" type="text" placeholder="new password (optional)" style="max-width:160px;"></td>
                        <td class="d-flex gap-1">
                            <button class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i></button>
                            <a href="admin_dashboard.php" class="btn btn-sm btn-outline-secondary">Cancel</a>
                        </td>
                    </form>
                </tr>
            <?php else: ?>
                <tr>
                    <td>#<?= (int)$u['id'] ?></td>
                    <td><?= safe($u['name']) ?><?php if ((int)$u['id'] === (int)$_SESSION['user_id']): ?> <span class="badge bg-info text-dark">you</span><?php endif; ?></td>
                    <td><?= safe($u['email']) ?></td>
                    <td><?= safe($u['phone']) ?></td>
                    <td><span class="badge bg-<?= $u['role']==='admin'?'danger':($u['role']==='manager'?'success':'secondary') ?>"><?= safe($u['role']) ?></span></td>
                    <td><?= safe($u['created_at']) ?></td>
                    <td class="d-flex gap-2">
                        <a href="admin_dashboard.php?edit_user=<?= (int)$u['id'] ?>#users" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                        <?php if ((int)$u['id'] !== (int)$_SESSION['user_id']): ?>
                            <form method="POST" onsubmit="return confirm('Delete user &quot;<?= safe(addslashes($u['name'])) ?>&quot;?<?= $u['role']==='manager' ? ' This also removes their hotels, rooms and bookings.' : '' ?>');">
                                <input type="hidden" name="delete_user" value="<?= (int)$u['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endif; ?>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</main>

<footer class="site-footer">
    Hotel Marketplace System &copy; <?= date('Y') ?> — Judith Antoni Obedi
</footer>
</body>
</html>
