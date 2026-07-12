<?php
// =====================================================
// HOME PAGE (index.php)
// Customers search & browse hotels with smart filters,
// real availability, review ratings and wishlist hearts.
// =====================================================
require_once 'config.php';

$location  = trim($_GET['location'] ?? '');
$check_in  = $_GET['check_in']  ?? '';
$check_out = $_GET['check_out'] ?? '';
$min_price = $_GET['min_price'] ?? '';
$max_price = $_GET['max_price'] ?? '';
$guests    = (int)($_GET['guests'] ?? 0);
$min_rating= (float)($_GET['min_rating'] ?? 0);
$sort      = $_GET['sort'] ?? '';

// Valid date range? (used for availability filtering)
$has_dates = ($check_in !== '' && $check_out !== '' && $check_out > $check_in);

// A room counts as "available" if no confirmed/pending booking overlaps the dates.
// We fold per-room filters (price, capacity, availability) into the JOIN so that
// starting_price reflects only rooms the guest can actually book.
$room_cond = "r.is_active = 1";
$room_params = [];
if ($guests > 0)            { $room_cond .= " AND r.capacity >= ?";        $room_params[] = $guests; }
if ($min_price !== '')      { $room_cond .= " AND r.price_per_night >= ?";  $room_params[] = (float)$min_price; }
if ($max_price !== '')      { $room_cond .= " AND r.price_per_night <= ?";  $room_params[] = (float)$max_price; }
if ($has_dates) {
    $room_cond .= " AND NOT EXISTS (
        SELECT 1 FROM bookings b
        WHERE b.room_id = r.id AND b.status IN ('pending','confirmed')
          AND b.check_in < ? AND b.check_out > ?)";
    $room_params[] = $check_out;
    $room_params[] = $check_in;
}

// $room_cond is interpolated twice below (MIN + COUNT), so its params bind twice.
$params = array_merge($room_params, $room_params);

$sql = "SELECT h.*,
               MIN(CASE WHEN $room_cond THEN r.price_per_night END) AS starting_price,
               COUNT(DISTINCT CASE WHEN $room_cond THEN r.id END)   AS available_rooms,
               AVG(rv.rating) AS avg_rating,
               COUNT(DISTINCT rv.id) AS review_count
        FROM hotels h
        LEFT JOIN rooms r   ON h.id = r.hotel_id
        LEFT JOIN reviews rv ON rv.hotel_id = h.id
        WHERE 1=1";

if ($location !== '') { $sql .= " AND h.location LIKE ?"; $params[] = "%$location%"; }

$sql .= " GROUP BY h.id";

// Only show hotels that actually have at least one bookable room matching the filters
$having = ["available_rooms > 0"];
if ($min_rating > 0) { $having[] = "COALESCE(AVG(rv.rating), h.rating) >= " . (float)$min_rating; }
$sql .= " HAVING " . implode(' AND ', $having);

// Sorting
switch ($sort) {
    case 'price_asc':   $sql .= " ORDER BY starting_price ASC"; break;
    case 'price_desc':  $sql .= " ORDER BY starting_price DESC"; break;
    case 'rating_desc': $sql .= " ORDER BY COALESCE(AVG(rv.rating), h.rating) DESC"; break;
    default:            $sql .= " ORDER BY h.name";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$hotels = $stmt->fetchAll();

// Which hotels has this user saved? (to show filled vs empty hearts)
$saved = [];
if (is_logged_in()) {
    $f = $pdo->prepare("SELECT hotel_id FROM favorites WHERE user_id = ?");
    $f->execute([$_SESSION['user_id']]);
    $saved = array_column($f->fetchAll(), 'hotel_id');
}

// Preserve the current query string so the wishlist toggle returns here
$return_to = 'index.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hotel Marketplace - Find & Book Hotels</title>
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
            <a href="list_property.php" class="btn btn-outline-light btn-sm"><i class="bi bi-building-add me-1"></i><span class="d-none d-sm-inline">List your property</span><span class="d-sm-none">List</span></a>
            <?php if (is_logged_in()): ?>
                <a href="wishlist.php" class="btn btn-outline-light btn-sm"><i class="bi bi-heart me-1"></i><span class="d-none d-sm-inline">Saved</span></a>
                <span class="text-light d-none d-sm-inline">Hi, <?= safe($_SESSION['name']) ?></span>
                <?php if ($_SESSION['role'] === 'manager'): ?>
                    <a href="manager_dashboard.php" class="btn btn-outline-light btn-sm"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
                <?php elseif ($_SESSION['role'] === 'admin'): ?>
                    <a href="admin_dashboard.php" class="btn btn-outline-light btn-sm"><i class="bi bi-gear me-1"></i>Admin</a>
                <?php else: ?>
                    <a href="my_bookings.php" class="btn btn-outline-light btn-sm"><i class="bi bi-journal-text me-1"></i>My Bookings</a>
                <?php endif; ?>
                <a href="logout.php" class="btn btn-outline-warning btn-sm"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
            <?php else: ?>
                <a href="login.php" class="btn btn-outline-light btn-sm">Login</a>
                <a href="register.php" class="btn btn-warning btn-sm">Register</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<section class="hero text-center">
    <div class="container">
        <h1 class="display-5">Find Your Perfect Stay</h1>
        <p class="lead">Compare and book hotels across Tanzania</p>
        <form method="GET" class="search-card text-start mx-auto" style="max-width: 980px;">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small text-muted mb-1">Destination</label>
                    <input type="text" name="location" class="form-control" placeholder="Where to? (e.g. Arusha)" value="<?= safe($location) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Check-in</label>
                    <input type="date" name="check_in" class="form-control" value="<?= safe($check_in) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Check-out</label>
                    <input type="date" name="check_out" class="form-control" value="<?= safe($check_out) ?>">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-warning w-100"><i class="bi bi-search me-1"></i>Search</button>
                </div>
            </div>
            <!-- Smart filters -->
            <div class="row g-2 align-items-end mt-1">
                <div class="col-6 col-md-2">
                    <label class="form-label small text-muted mb-1">Guests</label>
                    <input type="number" name="guests" min="1" class="form-control" placeholder="Any" value="<?= $guests ?: '' ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small text-muted mb-1">Min price</label>
                    <input type="number" name="min_price" min="0" step="1000" class="form-control" placeholder="TZS" value="<?= safe($min_price) ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small text-muted mb-1">Max price</label>
                    <input type="number" name="max_price" min="0" step="1000" class="form-control" placeholder="TZS" value="<?= safe($max_price) ?>">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small text-muted mb-1">Min rating</label>
                    <select name="min_rating" class="form-select">
                        <option value="">Any rating</option>
                        <?php foreach ([4.5,4,3.5,3] as $r): ?>
                            <option value="<?= $r ?>" <?= ($min_rating==$r?'selected':'') ?>><?= $r ?>+ stars</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label small text-muted mb-1">Sort by</label>
                    <select name="sort" class="form-select">
                        <option value="">Recommended</option>
                        <option value="price_asc"   <?= $sort==='price_asc'?'selected':'' ?>>Price: low to high</option>
                        <option value="price_desc"  <?= $sort==='price_desc'?'selected':'' ?>>Price: high to low</option>
                        <option value="rating_desc" <?= $sort==='rating_desc'?'selected':'' ?>>Top rated</option>
                    </select>
                </div>
            </div>
        </form>
    </div>
</section>

<main class="container my-5">
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <h2 class="section-title mb-0">Available Hotels <span class="text-muted fs-5">(<?= count($hotels) ?>)</span></h2>
        <?php if ($has_dates): ?>
            <span class="badge bg-success-subtle text-success border mb-2"><i class="bi bi-calendar-check me-1"></i>Showing rooms free <?= safe($check_in) ?> → <?= safe($check_out) ?></span>
        <?php endif; ?>
    </div>
    <?php if (empty($hotels)): ?>
        <div class="empty-state">
            <i class="bi bi-search"></i>
            <p class="mt-3 mb-0">No hotels match your search. Try widening your filters or different dates.</p>
        </div>
    <?php else: ?>
    <div class="row g-4 mt-1">
        <?php foreach ($hotels as $h): ?>
            <?php $is_saved = in_array($h['id'], $saved); ?>
            <div class="col-sm-6 col-lg-4">
                <div class="card hotel-card h-100">
                    <div class="position-relative">
                        <img src="<?= safe(hotel_img($h['image'])) ?>" class="card-img-top" alt="<?= safe($h['name']) ?>" loading="lazy">
                        <?php if (is_logged_in()): ?>
                            <form method="POST" action="wishlist.php" class="position-absolute top-0 end-0 m-2">
                                <input type="hidden" name="toggle" value="<?= (int)$h['id'] ?>">
                                <input type="hidden" name="return" value="<?= safe($return_to) ?>">
                                <button class="btn btn-light btn-sm rounded-circle shadow-sm" title="<?= $is_saved?'Remove from saved':'Save to wishlist' ?>">
                                    <i class="bi <?= $is_saved?'bi-heart-fill text-danger':'bi-heart' ?>"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                        <?php if ($has_dates && $h['available_rooms'] <= 2): ?>
                            <span class="badge bg-danger position-absolute bottom-0 start-0 m-2"><i class="bi bi-fire me-1"></i>Only <?= (int)$h['available_rooms'] ?> room(s) left!</span>
                        <?php endif; ?>
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
                        <p class="text-muted mb-1"><i class="bi bi-geo-alt-fill text-danger"></i> <?= safe($h['location']) ?></p>
                        <?php if ($h['review_count'] > 0): ?>
                            <p class="small text-muted mb-2"><i class="bi bi-chat-left-text me-1"></i><?= (int)$h['review_count'] ?> guest review(s)</p>
                        <?php else: ?>
                            <p class="small text-muted mb-2">No reviews yet</p>
                        <?php endif; ?>
                        <p class="small text-secondary flex-grow-1"><?= safe($h['description']) ?></p>
                        <?php if (!empty($h['website'])): ?>
                            <p class="small mb-2"><i class="bi bi-globe me-1 text-primary"></i><a href="<?= safe($h['website']) ?>" target="_blank" rel="noopener">Official website</a></p>
                        <?php endif; ?>
                        <?php if ($h['starting_price']): ?>
                            <p class="price-tag mb-3">From TZS <?= number_format($h['starting_price']) ?> <span class="text-muted fw-normal small">/ night</span></p>
                        <?php endif; ?>
                        <a href="hotel.php?id=<?= (int)$h['id'] ?><?= $has_dates ? '&check_in='.urlencode($check_in).'&check_out='.urlencode($check_out) : '' ?>" class="btn btn-primary w-100 mt-auto">View Rooms</a>
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
