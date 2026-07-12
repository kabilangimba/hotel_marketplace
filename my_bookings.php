<?php
// =====================================================
// MY BOOKINGS (my_bookings.php)
// Customer sees their own booking history
// =====================================================
require_once 'config.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

// Cancel a booking if requested
if (isset($_POST['cancel_id'])) {
    $stmt = $pdo->prepare("
        UPDATE bookings SET status='cancelled'
        WHERE id = ? AND user_id = ? AND status IN ('pending','confirmed')
    ");
    $stmt->execute([(int)$_POST['cancel_id'], $_SESSION['user_id']]);
}

// Get all bookings for the logged-in user
$stmt = $pdo->prepare("
    SELECT b.*, r.room_type, r.price_per_night, h.id AS hotel_id, h.name AS hotel_name, h.location
    FROM bookings b
    JOIN rooms r ON b.room_id = r.id
    JOIN hotels h ON r.hotel_id = h.id
    WHERE b.user_id = ?
    ORDER BY b.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$bookings = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Bookings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="index.php"><i class="bi bi-building-fill-check me-1"></i> Hotel Marketplace</a>
        <div class="d-flex gap-2">
            <a href="index.php" class="btn btn-outline-light btn-sm"><i class="bi bi-house me-1"></i>Home</a>
            <a href="wishlist.php" class="btn btn-outline-light btn-sm"><i class="bi bi-heart me-1"></i>Saved</a>
            <a href="logout.php" class="btn btn-outline-warning btn-sm"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
        </div>
    </div>
</nav>

<main class="container my-5">
    <h2 class="section-title">My Bookings</h2>
    <?php if (empty($bookings)): ?>
        <div class="empty-state">
            <i class="bi bi-journal-x"></i>
            <p class="mt-3 mb-3">You have no bookings yet.</p>
            <a href="index.php" class="btn btn-primary"><i class="bi bi-search me-1"></i>Find a hotel</a>
        </div>
    <?php else: ?>
        <div class="table-wrap">
        <table class="table table-striped align-middle mb-0">
            <thead>
                <tr>
                    <th>ID</th><th>Hotel</th><th>Room</th>
                    <th>Check-in</th><th>Check-out</th><th>Total</th>
                    <th>Status</th><th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($bookings as $b): ?>
                <tr>
                    <td>#<?= (int)$b['id'] ?></td>
                    <td><?= safe($b['hotel_name']) ?><br><small class="text-muted"><i class="bi bi-geo-alt"></i> <?= safe($b['location']) ?></small></td>
                    <td><?= safe($b['room_type']) ?>
                        <?php $addon_keys = array_filter(explode(',', $b['addons'] ?? '')); ?>
                        <?php if ($addon_keys): $cat = trip_addons(); ?>
                            <div class="small text-muted mt-1">
                                <?php foreach ($addon_keys as $k): if (isset($cat[$k])): ?>
                                    <div><i class="bi <?= $cat[$k][2] ?> me-1 text-primary"></i><?= safe($cat[$k][0]) ?></div>
                                <?php endif; endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td><?= safe($b['check_in']) ?></td>
                    <td><?= safe($b['check_out']) ?></td>
                    <td class="fw-semibold">TZS <?= number_format($b['total_price']) ?>
                        <?php if ($b['addons_total'] > 0): ?><br><small class="text-muted">incl. TZS <?= number_format($b['addons_total']) ?> add-ons</small><?php endif; ?>
                    </td>
                    <td>
                        <span class="badge bg-<?=
                            $b['status']==='confirmed' ? 'success' :
                            ($b['status']==='cancelled' ? 'danger' : 'warning') ?>">
                            <?= safe(ucfirst($b['status'])) ?>
                        </span>
                    </td>
                    <td>
                        <div class="d-flex flex-column gap-1">
                            <?php if (in_array($b['status'], ['pending','confirmed'])): ?>
                                <form method="POST" onsubmit="return confirm('Cancel this booking?');">
                                    <input type="hidden" name="cancel_id" value="<?= (int)$b['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger w-100"><i class="bi bi-x-circle me-1"></i>Cancel</button>
                                </form>
                            <?php endif; ?>
                            <?php if (in_array($b['status'], ['confirmed','completed'])): ?>
                                <a href="hotel.php?id=<?= (int)$b['hotel_id'] ?>#reviews" class="btn btn-sm btn-outline-primary"><i class="bi bi-star me-1"></i>Review</a>
                            <?php endif; ?>
                            <?php if ($b['status'] === 'cancelled'): ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</main>

<footer class="site-footer">
    Hotel Marketplace System &copy; <?= date('Y') ?> — Judith Antoni Obedi
</footer>
</body>
</html>
