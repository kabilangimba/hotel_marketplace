<?php
// =====================================================
// BOOK A ROOM (book.php)
// THIS IS THE MOST IMPORTANT FILE IN THE PROJECT.
// Prevents double-booking using a database transaction.
// =====================================================
require_once 'config.php';

// Only logged-in customers can book
if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';
$room_id = (int)($_GET['room_id'] ?? $_POST['room_id'] ?? 0);

// Pre-fill dates carried over from the search/hotel page
$pre_check_in  = $_GET['check_in']  ?? '';
$pre_check_out = $_GET['check_out'] ?? '';

// Fetch room details to show on the form
$stmt = $pdo->prepare("
    SELECT r.*, h.name AS hotel_name, h.location
    FROM rooms r JOIN hotels h ON r.hotel_id = h.id
    WHERE r.id = ? AND r.is_active = 1
");
$stmt->execute([$room_id]);
$room = $stmt->fetch();

if (!$room) {
    die("Room not found.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $check_in  = $_POST['check_in']  ?? '';
    $check_out = $_POST['check_out'] ?? '';

    // === VALIDATION ===
    if (!$check_in || !$check_out) {
        $error = 'Please choose both check-in and check-out dates.';
    } elseif ($check_in >= $check_out) {
        $error = 'Check-out must be after check-in.';
    } elseif ($check_in < date('Y-m-d')) {
        $error = 'Check-in cannot be in the past.';
    } else {
        // === CALCULATE TOTAL PRICE (room + selected trip add-ons) ===
        $nights = (strtotime($check_out) - strtotime($check_in)) / 86400;
        $room_total = $nights * $room['price_per_night'];

        // Validate selected add-ons against the catalog
        $catalog = trip_addons();
        $chosen  = (array)($_POST['addons'] ?? []);
        $chosen  = array_values(array_intersect($chosen, array_keys($catalog)));
        $addons_total = 0;
        foreach ($chosen as $key) { $addons_total += $catalog[$key][1]; }

        $total = $room_total + $addons_total;

        // ========================================================
        // CONCURRENCY-SAFE BOOKING USING A TRANSACTION
        // ========================================================
        // Problem: Two users could click "Book" at the same instant
        // for the same room and same dates. Without protection both
        // would succeed and create a DOUBLE BOOKING.
        //
        // Solution: Wrap the check + insert inside a TRANSACTION
        // and LOCK the rows we read with "FOR UPDATE". This forces
        // the second request to wait until the first one finishes.
        // ========================================================
        try {
            $pdo->beginTransaction();

            // Step 1: Check for ANY overlapping confirmed booking
            // Two date ranges OVERLAP if: existing.check_in < new.check_out
            //                       AND  existing.check_out > new.check_in
            $check = $pdo->prepare("
                SELECT id FROM bookings
                WHERE room_id = ?
                  AND status IN ('pending', 'confirmed')
                  AND check_in  < ?
                  AND check_out > ?
                FOR UPDATE
            ");
            $check->execute([$room_id, $check_out, $check_in]);

            if ($check->fetch()) {
                // Conflict! Another booking already covers these dates.
                $pdo->rollBack();
                $error = 'Sorry, this room is already booked for those dates. Please choose different dates.';
            } else {
                // Step 2: Safe to insert the new booking
                $insert = $pdo->prepare("
                    INSERT INTO bookings (user_id, room_id, check_in, check_out, total_price, addons, addons_total, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'confirmed')
                ");
                $insert->execute([
                    $_SESSION['user_id'],
                    $room_id,
                    $check_in,
                    $check_out,
                    $total,
                    implode(',', $chosen),
                    $addons_total
                ]);

                $booking_id = $pdo->lastInsertId();
                $pdo->commit();
                $success = "Booking confirmed! Total: TZS " . number_format($total) .
                           " for $nights night(s)" .
                           ($addons_total > 0 ? " (incl. TZS " . number_format($addons_total) . " add-ons)" : "") .
                           ". Booking ID: " . $booking_id;
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Booking failed: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Book Room - Hotel Marketplace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="index.php"><i class="bi bi-building-fill-check me-1"></i> Hotel Marketplace</a>
        <a href="hotel.php?id=<?= (int)$room['hotel_id'] ?>" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
</nav>

<main class="container my-5" style="max-width: 640px;">
    <div class="card">
        <div class="card-body p-4 p-md-5">
            <h3 class="mb-1"><i class="bi bi-calendar-check text-success me-1"></i>Book a Room</h3>
            <p class="text-muted mb-4">Review the details and choose your dates</p>

            <ul class="list-group list-group-flush mb-4">
                <li class="list-group-item d-flex justify-content-between px-0"><span class="text-muted">Hotel</span><strong><?= safe($room['hotel_name']) ?></strong></li>
                <li class="list-group-item d-flex justify-content-between px-0"><span class="text-muted">Location</span><span><?= safe($room['location']) ?></span></li>
                <li class="list-group-item d-flex justify-content-between px-0"><span class="text-muted">Room Type</span><span><?= safe($room['room_type']) ?></span></li>
                <li class="list-group-item d-flex justify-content-between px-0"><span class="text-muted">Capacity</span><span><?= (int)$room['capacity'] ?> guest(s)</span></li>
                <li class="list-group-item d-flex justify-content-between px-0"><span class="text-muted">Price</span><span class="price-tag">TZS <?= number_format($room['price_per_night']) ?> / night</span></li>
            </ul>

            <?php if ($error): ?>
                <div class="alert alert-danger d-flex align-items-center"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= safe($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success d-flex align-items-center"><i class="bi bi-check-circle-fill me-2"></i><?= safe($success) ?></div>
                <a href="my_bookings.php" class="btn btn-primary w-100"><i class="bi bi-journal-text me-1"></i>View My Bookings</a>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="room_id" value="<?= (int)$room_id ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Check-in Date</label>
                            <input type="date" name="check_in" class="form-control"
                                   min="<?= date('Y-m-d') ?>" value="<?= safe($pre_check_in) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Check-out Date</label>
                            <input type="date" name="check_out" class="form-control"
                                   min="<?= date('Y-m-d', strtotime('+1 day')) ?>" value="<?= safe($pre_check_out) ?>" required>
                        </div>
                    </div>

                    <!-- Tanzania trip add-ons — our signature differentiator -->
                    <div class="mt-4">
                        <label class="form-label d-flex align-items-center"><i class="bi bi-stars text-warning me-2"></i>Enhance your trip <span class="text-muted small ms-2">(optional)</span></label>
                        <div class="list-group">
                            <?php foreach (trip_addons() as $key => $a): ?>
                                <label class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><input class="form-check-input me-2" type="checkbox" name="addons[]" value="<?= $key ?>"
                                          <?= in_array($key, (array)($_POST['addons'] ?? []), true) ? 'checked' : '' ?>>
                                          <i class="bi <?= $a[2] ?> me-1 text-primary"></i><?= safe($a[0]) ?></span>
                                    <span class="price-tag small">+ TZS <?= number_format($a[1]) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-success w-100 mt-4"><i class="bi bi-check2-circle me-1"></i>Confirm Booking</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</main>

<footer class="site-footer">
    Hotel Marketplace System &copy; <?= date('Y') ?> — Judith Antoni Obedi
</footer>
</body>
</html>
