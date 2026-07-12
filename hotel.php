<?php
// =====================================================
// HOTEL DETAIL PAGE (hotel.php)
// Rooms + photo gallery, amenities, map, and guest reviews.
// =====================================================
require_once 'config.php';

$hotel_id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM hotels WHERE id = ?");
$stmt->execute([$hotel_id]);
$hotel = $stmt->fetch();
if (!$hotel) die("Hotel not found.");

// Optional date context carried over from search (for booking links + availability)
$check_in  = $_GET['check_in']  ?? '';
$check_out = $_GET['check_out'] ?? '';
$has_dates = ($check_in !== '' && $check_out !== '' && $check_out > $check_in);
$date_qs   = $has_dates ? '&check_in='.urlencode($check_in).'&check_out='.urlencode($check_out) : '';

$review_msg = '';
// Can this user review? Only after a confirmed/completed stay at this hotel.
$can_review = false;
$my_review  = null;
if (is_logged_in() && ($_SESSION['role'] ?? '') === 'customer') {
    $chk = $pdo->prepare("
        SELECT COUNT(*) FROM bookings b JOIN rooms r ON b.room_id = r.id
        WHERE r.hotel_id = ? AND b.user_id = ? AND b.status IN ('confirmed','completed')
    ");
    $chk->execute([$hotel_id, $_SESSION['user_id']]);
    $can_review = $chk->fetchColumn() > 0;

    $mr = $pdo->prepare("SELECT * FROM reviews WHERE hotel_id = ? AND user_id = ?");
    $mr->execute([$hotel_id, $_SESSION['user_id']]);
    $my_review = $mr->fetch();
}

// Submit / update a review
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (!$can_review) {
        $review_msg = 'Only guests with a booking at this hotel can leave a review.';
    } else {
        $rating  = max(1, min(5, (int)$_POST['rating']));
        $comment = trim($_POST['comment'] ?? '');
        $stmt = $pdo->prepare("
            INSERT INTO reviews (hotel_id, user_id, rating, comment) VALUES (?,?,?,?)
            ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment), created_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$hotel_id, $_SESSION['user_id'], $rating, $comment]);
        header("Location: hotel.php?id=$hotel_id$date_qs#reviews");
        exit;
    }
}

// Rooms — when dates are given, show only rooms free for that range
if ($has_dates) {
    $stmt = $pdo->prepare("
        SELECT * FROM rooms r
        WHERE r.hotel_id = ? AND r.is_active = 1
          AND NOT EXISTS (SELECT 1 FROM bookings b WHERE b.room_id = r.id
                          AND b.status IN ('pending','confirmed')
                          AND b.check_in < ? AND b.check_out > ?)
    ");
    $stmt->execute([$hotel_id, $check_out, $check_in]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM rooms WHERE hotel_id = ? AND is_active = 1");
    $stmt->execute([$hotel_id]);
}
$rooms = $stmt->fetchAll();

// Reviews + average
$rv = $pdo->prepare("
    SELECT rv.*, u.name AS reviewer
    FROM reviews rv JOIN users u ON rv.user_id = u.id
    WHERE rv.hotel_id = ? ORDER BY rv.created_at DESC
");
$rv->execute([$hotel_id]);
$reviews = $rv->fetchAll();
$avg = $reviews ? array_sum(array_column($reviews, 'rating')) / count($reviews) : null;

$amenities = csv_list($hotel['amenities'] ?? '');
$gallery   = csv_list($hotel['gallery'] ?? '');
array_unshift($gallery, hotel_img($hotel['image'])); // main image first
$map_url = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($hotel['name'] . ' ' . $hotel['location']);

// Is this hotel saved by the user?
$is_saved = false;
if (is_logged_in()) {
    $f = $pdo->prepare("SELECT 1 FROM favorites WHERE user_id = ? AND hotel_id = ?");
    $f->execute([$_SESSION['user_id'], $hotel_id]);
    $is_saved = (bool)$f->fetch();
}
$return_to = 'hotel.php?id=' . $hotel_id . $date_qs;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= safe($hotel['name']) ?> - Hotel Marketplace</title>
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
            <?php if (is_logged_in()): ?>
                <a href="wishlist.php" class="btn btn-outline-light btn-sm"><i class="bi bi-heart me-1"></i>Saved</a>
            <?php endif; ?>
            <a href="index.php" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Search</a>
        </div>
    </div>
</nav>

<main class="container my-5">
    <!-- Photo gallery -->
    <div id="galleryCarousel" class="carousel slide mb-4 rounded overflow-hidden shadow-sm" data-bs-ride="false">
        <div class="carousel-inner">
            <?php foreach ($gallery as $i => $src): ?>
                <div class="carousel-item <?= $i===0?'active':'' ?>">
                    <img src="<?= safe($src) ?>" class="d-block w-100" style="height:380px;object-fit:cover;" alt="<?= safe($hotel['name']) ?> photo <?= $i+1 ?>">
                </div>
            <?php endforeach; ?>
        </div>
        <?php if (count($gallery) > 1): ?>
            <button class="carousel-control-prev" type="button" data-bs-target="#galleryCarousel" data-bs-slide="prev"><span class="carousel-control-prev-icon"></span></button>
            <button class="carousel-control-next" type="button" data-bs-target="#galleryCarousel" data-bs-slide="next"><span class="carousel-control-next-icon"></span></button>
        <?php endif; ?>
    </div>

    <div class="card mb-4">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div>
                    <h2 class="mb-1"><?= safe($hotel['name']) ?></h2>
                    <p class="text-muted mb-2"><i class="bi bi-geo-alt-fill text-danger"></i> <?= safe($hotel['location']) ?>
                        &middot; <a href="<?= safe($map_url) ?>" target="_blank" rel="noopener"><i class="bi bi-map me-1"></i>View on map</a></p>
                </div>
                <div class="text-end">
                    <?php if ($avg !== null): ?>
                        <div class="fs-5"><?= stars($avg) ?></div>
                        <small class="text-muted"><?= count($reviews) ?> guest review(s)</small>
                    <?php elseif (!empty($hotel['rating'])): ?>
                        <div class="fs-5"><?= stars($hotel['rating']) ?></div>
                    <?php endif; ?>
                    <?php if (is_logged_in()): ?>
                        <form method="POST" action="wishlist.php" class="mt-2">
                            <input type="hidden" name="toggle" value="<?= (int)$hotel_id ?>">
                            <input type="hidden" name="return" value="<?= safe($return_to) ?>">
                            <button class="btn btn-sm <?= $is_saved?'btn-danger':'btn-outline-danger' ?>">
                                <i class="bi <?= $is_saved?'bi-heart-fill':'bi-heart' ?> me-1"></i><?= $is_saved?'Saved':'Save' ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            <p class="mb-3 text-secondary"><?= safe($hotel['description']) ?></p>
            <div class="d-flex flex-wrap gap-3 small mb-3">
                <?php if (!empty($hotel['website'])): ?>
                    <span><i class="bi bi-globe me-1 text-primary"></i><a href="<?= safe($hotel['website']) ?>" target="_blank" rel="noopener">Official website</a></span>
                <?php endif; ?>
                <?php if (!empty($hotel['phone'])): ?>
                    <span><i class="bi bi-telephone me-1 text-success"></i><?= safe($hotel['phone']) ?></span>
                <?php endif; ?>
            </div>

            <?php if ($amenities): ?>
                <h6 class="text-uppercase text-muted small mb-2">Amenities</h6>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($amenities as $a): ?>
                        <span class="badge bg-light text-dark border fw-normal py-2 px-3"><i class="bi <?= amenity_icon($a) ?> me-1 text-primary"></i><?= safe($a) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <h3 class="section-title">Available Rooms</h3>
    <?php if ($has_dates): ?>
        <p class="text-muted"><i class="bi bi-calendar-check me-1 text-success"></i>Showing rooms free for <strong><?= safe($check_in) ?></strong> → <strong><?= safe($check_out) ?></strong></p>
    <?php endif; ?>
    <?php if (empty($rooms)): ?>
        <div class="empty-state">
            <i class="bi bi-door-closed"></i>
            <p class="mt-3 mb-0"><?= $has_dates ? 'No rooms are free for those dates. Try different dates.' : 'No rooms are currently available for this hotel.' ?></p>
        </div>
    <?php else: ?>
    <div class="row g-4">
        <?php foreach ($rooms as $r): ?>
            <div class="col-md-6">
                <div class="card hotel-card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start">
                            <h5 class="mb-1"><?= safe($r['room_type']) ?></h5>
                            <span class="badge bg-light text-dark border"><i class="bi bi-people-fill me-1"></i><?= (int)$r['capacity'] ?> guest(s)</span>
                        </div>
                        <p class="text-secondary small flex-grow-1"><?= safe($r['description']) ?></p>
                        <p class="price-tag fs-5 mb-3">TZS <?= number_format($r['price_per_night']) ?> <span class="text-muted fw-normal small">/ night</span></p>
                        <a href="book.php?room_id=<?= (int)$r['id'] ?><?= $date_qs ?>" class="btn btn-success mt-auto"><i class="bi bi-calendar-check me-1"></i>Book Now</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Reviews -->
    <h3 class="section-title mt-5" id="reviews">Guest Reviews
        <?php if ($avg !== null): ?><span class="text-muted fs-5">— <?= number_format($avg,1) ?>/5 from <?= count($reviews) ?></span><?php endif; ?>
    </h3>

    <?php if ($review_msg): ?>
        <div class="alert alert-warning"><i class="bi bi-info-circle me-1"></i><?= safe($review_msg) ?></div>
    <?php endif; ?>

    <?php if ($can_review): ?>
        <div class="card mb-4">
            <div class="card-body p-4">
                <h5 class="mb-3"><?= $my_review ? 'Update your review' : 'Write a review' ?></h5>
                <form method="POST">
                    <input type="hidden" name="submit_review" value="1">
                    <div class="mb-3">
                        <label class="form-label">Your rating</label>
                        <select name="rating" class="form-select" style="max-width:200px;">
                            <?php for ($s=5;$s>=1;$s--): ?>
                                <option value="<?= $s ?>" <?= ($my_review && (int)$my_review['rating']===$s)?'selected':'' ?>><?= str_repeat('★',$s).str_repeat('☆',5-$s) ?> (<?= $s ?>)</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Your comment</label>
                        <textarea name="comment" class="form-control" rows="3" placeholder="Tell other travellers about your stay..."><?= safe($my_review['comment'] ?? '') ?></textarea>
                    </div>
                    <button class="btn btn-primary"><i class="bi bi-send me-1"></i><?= $my_review ? 'Update review' : 'Post review' ?></button>
                </form>
            </div>
        </div>
    <?php elseif (is_logged_in() && ($_SESSION['role'] ?? '')==='customer'): ?>
        <p class="text-muted"><i class="bi bi-lock me-1"></i>You can review this hotel after a stay here.</p>
    <?php endif; ?>

    <?php if (empty($reviews)): ?>
        <p class="text-muted">No reviews yet — be the first to share your experience.</p>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($reviews as $rev): ?>
                <div class="col-md-6">
                    <div class="card h-100"><div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <strong><?= safe($rev['reviewer']) ?></strong>
                            <span class="text-warning"><?= str_repeat('★',(int)$rev['rating']) ?><span class="text-muted"><?= str_repeat('☆',5-(int)$rev['rating']) ?></span></span>
                        </div>
                        <p class="mb-1 small text-secondary"><?= safe($rev['comment']) ?: '<em>No comment</em>' ?></p>
                        <small class="text-muted"><?= safe(date('d M Y', strtotime($rev['created_at']))) ?></small>
                    </div></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<footer class="site-footer">
    Hotel Marketplace System &copy; <?= date('Y') ?> — Judith Antoni Obedi
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
