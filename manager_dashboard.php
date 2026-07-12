<?php
// =====================================================
// MANAGER DASHBOARD (manager_dashboard.php)
// Hotel managers manage rooms and view bookings
// =====================================================
require_once 'config.php';
require_role('manager');

$message = '';

// Remove a hotel — a manager may delete ONLY their own hotel.
// The WHERE manager_id guard makes it impossible to delete someone else's.
// Deleting cascades to that hotel's rooms and bookings (see schema FKs).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_hotel'])) {
    $stmt = $pdo->prepare("DELETE FROM hotels WHERE id = ? AND manager_id = ?");
    $stmt->execute([(int)$_POST['delete_hotel'], $_SESSION['user_id']]);
    $message = $stmt->rowCount()
        ? 'Hotel removed (including its rooms and bookings).'
        : 'That hotel could not be removed — it is not one of yours.';
}

// Get hotels owned by this manager
$stmt = $pdo->prepare("SELECT * FROM hotels WHERE manager_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$hotels = $stmt->fetchAll();

// Get all bookings for this manager's rooms
$stmt = $pdo->prepare("
    SELECT b.*, r.room_type, h.name AS hotel_name, u.name AS customer_name, u.email
    FROM bookings b
    JOIN rooms r ON b.room_id = r.id
    JOIN hotels h ON r.hotel_id = h.id
    JOIN users u ON b.user_id = u.id
    WHERE h.manager_id = ?
    ORDER BY b.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$bookings = $stmt->fetchAll();

// Summary statistics
$total_bookings = count($bookings);
$total_revenue  = array_sum(array_map(fn($b) => $b['status']==='confirmed' ? $b['total_price'] : 0, $bookings));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manager Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-dark bg-success">
    <div class="container">
        <a class="navbar-brand"><i class="bi bi-building-fill-check me-1"></i> Manager Dashboard</a>
        <div class="d-flex align-items-center gap-2">
            <span class="text-light d-none d-sm-inline"><?= safe($_SESSION['name']) ?></span>
            <a href="logout.php" class="btn btn-outline-light btn-sm"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
        </div>
    </div>
</nav>

<main class="container my-4">
    <?php if ($message): ?>
        <div class="alert alert-info d-flex align-items-center"><i class="bi bi-info-circle-fill me-2"></i><?= safe($message) ?></div>
    <?php endif; ?>
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card stat-card" style="background:linear-gradient(135deg,#1e3c72,#2a5298);">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div><div class="stat-label">My Hotels</div><p class="stat-value"><?= count($hotels) ?></p></div>
                    <i class="bi bi-buildings-fill"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card" style="background:linear-gradient(135deg,#0891b2,#06b6d4);">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div><div class="stat-label">Total Bookings</div><p class="stat-value"><?= $total_bookings ?></p></div>
                    <i class="bi bi-calendar-check-fill"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card" style="background:linear-gradient(135deg,#15803d,#22c55e);">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div><div class="stat-label">Revenue (Confirmed)</div><p class="stat-value fs-4">TZS <?= number_format($total_revenue) ?></p></div>
                    <i class="bi bi-cash-stack"></i>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($_GET['limit'])): ?>
        <div class="alert alert-warning d-flex align-items-center"><i class="bi bi-exclamation-circle-fill me-2"></i>
            Each manager can list <strong>one hotel</strong>. Remove your current hotel first if you want to list a different one.</div>
    <?php endif; ?>
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <h3 class="section-title mb-0">My Hotel</h3>
        <?php if (empty($hotels)): ?>
            <a href="add_hotel.php" class="btn btn-success btn-sm mb-2"><i class="bi bi-building-add me-1"></i>Add Your Hotel</a>
        <?php else: ?>
            <span class="badge bg-light text-muted border mb-2"><i class="bi bi-info-circle me-1"></i>One hotel per manager</span>
        <?php endif; ?>
    </div>
    <div class="row g-3 mb-4 mt-1">
        <?php if (empty($hotels)): ?>
            <div class="col-12"><div class="empty-state"><i class="bi bi-buildings"></i>
                <p class="mt-3 mb-2">You don't manage any hotels yet.</p>
                <a href="add_hotel.php" class="btn btn-success btn-sm"><i class="bi bi-building-add me-1"></i>List your first property</a>
            </div></div>
        <?php endif; ?>
        <?php foreach ($hotels as $h): ?>
            <div class="col-sm-6 col-md-4">
                <div class="card hotel-card h-100">
                    <img src="<?= safe(hotel_img($h['image'])) ?>" class="card-img-top" alt="<?= safe($h['name']) ?>" style="height:140px;object-fit:cover;" loading="lazy">
                    <div class="card-body d-flex flex-column">
                        <h5><?= safe($h['name']) ?></h5>
                        <p class="text-muted flex-grow-1"><i class="bi bi-geo-alt-fill text-danger"></i> <?= safe($h['location']) ?></p>
                        <div class="d-grid gap-2 mt-auto">
                            <a href="manage_rooms.php?hotel_id=<?= (int)$h['id'] ?>" class="btn btn-primary btn-sm"><i class="bi bi-door-open me-1"></i>Manage Rooms &amp; Services</a>
                            <div class="d-flex gap-2">
                                <a href="add_hotel.php?id=<?= (int)$h['id'] ?>" class="btn btn-outline-secondary btn-sm flex-fill"><i class="bi bi-pencil-square me-1"></i>Edit</a>
                                <form method="POST" class="flex-fill" onsubmit="return confirm('Remove &quot;<?= safe(addslashes($h['name'])) ?>&quot;? This permanently deletes its rooms and bookings.');">
                                    <input type="hidden" name="delete_hotel" value="<?= (int)$h['id'] ?>">
                                    <button class="btn btn-outline-danger btn-sm w-100"><i class="bi bi-trash me-1"></i>Remove</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <h3 class="section-title">Recent Bookings</h3>
    <div class="table-wrap">
    <table class="table table-striped align-middle mb-0">
        <thead>
            <tr>
                <th>ID</th><th>Customer</th><th>Hotel</th><th>Room</th>
                <th>Dates</th><th>Total</th><th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($bookings)): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">No bookings yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($bookings as $b): ?>
            <tr>
                <td>#<?= (int)$b['id'] ?></td>
                <td><?= safe($b['customer_name']) ?><br><small class="text-muted"><?= safe($b['email']) ?></small></td>
                <td><?= safe($b['hotel_name']) ?></td>
                <td><?= safe($b['room_type']) ?></td>
                <td><?= safe($b['check_in']) ?> <i class="bi bi-arrow-right text-muted"></i> <?= safe($b['check_out']) ?></td>
                <td class="fw-semibold">TZS <?= number_format($b['total_price']) ?></td>
                <td><span class="badge bg-<?= $b['status']==='confirmed'?'success':($b['status']==='cancelled'?'danger':'warning') ?>"><?= safe(ucfirst($b['status'])) ?></span></td>
            </tr>
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
