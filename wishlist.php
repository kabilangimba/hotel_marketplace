<?php
// =====================================================
// WISHLIST / SAVED HOTELS (wishlist.php)
// Toggles a favorite (POST) and lists the user's saved hotels.
// =====================================================
require_once 'config.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

// Toggle a favorite, then return to wherever the user was.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle'])) {
    $hid = (int)$_POST['toggle'];
    $exists = $pdo->prepare("SELECT 1 FROM favorites WHERE user_id = ? AND hotel_id = ?");
    $exists->execute([$_SESSION['user_id'], $hid]);
    if ($exists->fetch()) {
        $pdo->prepare("DELETE FROM favorites WHERE user_id = ? AND hotel_id = ?")
            ->execute([$_SESSION['user_id'], $hid]);
    } else {
        $pdo->prepare("INSERT IGNORE INTO favorites (user_id, hotel_id) VALUES (?, ?)")
            ->execute([$_SESSION['user_id'], $hid]);
    }
    // Safe local redirect back
    $back = $_POST['return'] ?? 'wishlist.php';
    if (!preg_match('#^[a-zA-Z0-9_]+\.php#', $back)) $back = 'wishlist.php';
    header('Location: ' . $back);
    exit;
}

// Saved hotels with their starting price + real review average
$stmt = $pdo->prepare("
    SELECT h.*, MIN(r.price_per_night) AS starting_price,
           AVG(rv.rating) AS avg_rating, COUNT(DISTINCT rv.id) AS review_count
    FROM favorites f
    JOIN hotels h ON f.hotel_id = h.id
    LEFT JOIN rooms r ON h.id = r.hotel_id AND r.is_active = 1
    LEFT JOIN reviews rv ON rv.hotel_id = h.id
    WHERE f.user_id = ?
    GROUP BY h.id
    ORDER BY f.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$hotels = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Saved Hotels</title>
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
            <a href="my_bookings.php" class="btn btn-outline-light btn-sm"><i class="bi bi-journal-text me-1"></i>My Bookings</a>
            <a href="logout.php" class="btn btn-outline-warning btn-sm"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
        </div>
    </div>
</nav>

<main class="container my-5">
    <h2 class="section-title"><i class="bi bi-heart-fill text-danger me-1"></i>My Saved Hotels <span class="text-muted fs-5">(<?= count($hotels) ?>)</span></h2>
    <?php if (empty($hotels)): ?>
        <div class="empty-state">
            <i class="bi bi-heart"></i>
            <p class="mt-3 mb-3">You haven't saved any hotels yet. Tap the heart on any hotel to save it here.</p>
            <a href="index.php" class="btn btn-primary"><i class="bi bi-search me-1"></i>Browse hotels</a>
        </div>
    <?php else: ?>
    <div class="row g-4">
        <?php foreach ($hotels as $h): ?>
            <div class="col-sm-6 col-lg-4">
                <div class="card hotel-card h-100">
                    <div class="position-relative">
                        <img src="<?= safe(hotel_img($h['image'])) ?>" class="card-img-top" alt="<?= safe($h['name']) ?>" loading="lazy">
                        <form method="POST" class="position-absolute top-0 end-0 m-2">
                            <input type="hidden" name="toggle" value="<?= (int)$h['id'] ?>">
                            <input type="hidden" name="return" value="wishlist.php">
                            <button class="btn btn-light btn-sm rounded-circle shadow-sm" title="Remove from saved"><i class="bi bi-heart-fill text-danger"></i></button>
                        </form>
                    </div>
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start">
                            <h5 class="card-title mb-1"><?= safe($h['name']) ?></h5>
                            <?php if ($h['review_count'] > 0): ?>
                                <span class="ms-2"><?= stars($h['avg_rating']) ?></span>
                            <?php elseif (!empty($h['rating'])): ?>
                                <span class="ms-2"><?= stars($h['rating']) ?></span>
                            <?php endif; ?>
                        </div>
                        <p class="text-muted mb-2"><i class="bi bi-geo-alt-fill text-danger"></i> <?= safe($h['location']) ?></p>
                        <?php if ($h['review_count'] > 0): ?>
                            <p class="small text-muted mb-2"><?= (int)$h['review_count'] ?> review(s)</p>
                        <?php endif; ?>
                        <?php if ($h['starting_price']): ?>
                            <p class="price-tag mb-3 mt-auto">From TZS <?= number_format($h['starting_price']) ?> <span class="text-muted fw-normal small">/ night</span></p>
                        <?php endif; ?>
                        <a href="hotel.php?id=<?= (int)$h['id'] ?>" class="btn btn-primary w-100">View Rooms</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</main>

<footer class="site-footer">
    Hotel Marketplace System &copy; <?= date('Y') ?> — Judith Antoni Obedi
</footer>
</body>
</html>
