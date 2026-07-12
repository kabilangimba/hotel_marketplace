<?php
// =====================================================
// MANAGE ROOMS (manage_rooms.php)
// Hotel managers add/edit/delete rooms for their hotel
// =====================================================
require_once 'config.php';
require_manager_or_admin();

$hotel_id = (int)($_GET['hotel_id'] ?? 0);

// Access rule:
//   - Admin can manage ANY hotel.
//   - A manager can manage ONLY a hotel they own.
if (is_admin()) {
    $stmt = $pdo->prepare("
        SELECT h.*, u.name AS owner_name, u.email AS owner_email
        FROM hotels h JOIN users u ON h.manager_id = u.id
        WHERE h.id = ?
    ");
    $stmt->execute([$hotel_id]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM hotels WHERE id = ? AND manager_id = ?");
    $stmt->execute([$hotel_id, $_SESSION['user_id']]);
}
$hotel = $stmt->fetch();
if (!$hotel) die("Unauthorized: This hotel doesn't belong to you.");

// Where the "Back" button should point for this user
$dashboard = is_admin() ? 'admin_dashboard.php' : 'manager_dashboard.php';

$message = '';

// Handle add room
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_room'])) {
    $stmt = $pdo->prepare("
        INSERT INTO rooms (hotel_id, room_type, price_per_night, capacity, description)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $hotel_id,
        trim($_POST['room_type']),
        (float)$_POST['price'],
        (int)$_POST['capacity'],
        trim($_POST['description'])
    ]);
    $message = 'Room added successfully!';
}

// Handle update room (price + details). The hotel_id guard keeps this
// scoped to the hotel we already verified the user may manage.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_room'])) {
    $stmt = $pdo->prepare("
        UPDATE rooms
        SET room_type = ?, price_per_night = ?, capacity = ?, description = ?
        WHERE id = ? AND hotel_id = ?
    ");
    $stmt->execute([
        trim($_POST['room_type']),
        (float)$_POST['price'],
        (int)$_POST['capacity'],
        trim($_POST['description']),
        (int)$_POST['update_room'],
        $hotel_id
    ]);
    $message = 'Room updated.';
}

// Handle delete room (soft delete: set is_active=0)
if (isset($_POST['delete_id'])) {
    $stmt = $pdo->prepare("UPDATE rooms SET is_active=0 WHERE id=? AND hotel_id=?");
    $stmt->execute([(int)$_POST['delete_id'], $hotel_id]);
    $message = 'Room removed.';
}

// Which room (if any) is being edited inline
$edit_room = (int)($_GET['edit_room'] ?? 0);

// Get rooms
$stmt = $pdo->prepare("SELECT * FROM rooms WHERE hotel_id = ? AND is_active = 1");
$stmt->execute([$hotel_id]);
$rooms = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Rooms - <?= safe($hotel['name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-dark <?= is_admin() ? 'bg-danger' : 'bg-success' ?>">
    <div class="container">
        <a class="navbar-brand" href="<?= $dashboard ?>"><i class="bi bi-building-fill-check me-1"></i> <?= is_admin() ? 'Admin Dashboard' : 'Manager Dashboard' ?></a>
        <a href="<?= $dashboard ?>" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
</nav>

<main class="container my-4">
    <h2 class="section-title">Manage Rooms — <?= safe($hotel['name']) ?></h2>
    <?php if (is_admin() && !empty($hotel['owner_name'])): ?>
        <p class="text-muted mt-n2 mb-3"><i class="bi bi-shield-lock-fill text-danger me-1"></i>Managing as admin — owner:
            <strong><?= safe($hotel['owner_name']) ?></strong> (<?= safe($hotel['owner_email']) ?>)</p>
    <?php endif; ?>

    <?php if (isset($_GET['new'])): ?>
        <div class="alert alert-primary d-flex align-items-center"><i class="bi bi-stars me-2"></i>
            <span>Your property <strong><?= safe($hotel['name']) ?></strong> is live! Add at least one room below so travellers can book it.</span></div>
    <?php endif; ?>
    <?php if ($message): ?>
        <div class="alert alert-success d-flex align-items-center"><i class="bi bi-check-circle-fill me-2"></i><?= safe($message) ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-body p-4">
            <h5 class="mb-3"><i class="bi bi-plus-circle me-1 text-success"></i>Add New Room</h5>
            <form method="POST" class="row g-2 align-items-end">
                <div class="col-md-3"><label class="form-label small">Room type</label><input class="form-control" name="room_type" placeholder="e.g. Deluxe" required></div>
                <div class="col-md-2"><label class="form-label small">Price/night</label><input class="form-control" name="price" type="number" step="0.01" placeholder="0.00" required></div>
                <div class="col-md-2"><label class="form-label small">Capacity</label><input class="form-control" name="capacity" type="number" placeholder="Guests" required></div>
                <div class="col-md-3"><label class="form-label small">Description</label><input class="form-control" name="description" placeholder="Optional"></div>
                <div class="col-md-2"><button name="add_room" class="btn btn-primary w-100"><i class="bi bi-plus-lg me-1"></i>Add</button></div>
            </form>
        </div>
    </div>

    <div class="table-wrap">
    <table class="table align-middle mb-0">
        <thead><tr>
            <th>ID</th><th>Type</th><th>Price/Night</th><th>Capacity</th><th>Description</th><th>Action</th>
        </tr></thead>
        <tbody>
        <?php if (empty($rooms)): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">No active rooms. Add one above.</td></tr>
        <?php endif; ?>
        <?php foreach ($rooms as $r): ?>
            <?php if ($edit_room === (int)$r['id']): ?>
                <!-- Inline edit row: update price & details -->
                <tr class="table-warning">
                    <form method="POST">
                        <input type="hidden" name="update_room" value="<?= (int)$r['id'] ?>">
                        <td>#<?= (int)$r['id'] ?></td>
                        <td><input class="form-control form-control-sm" name="room_type" value="<?= safe($r['room_type']) ?>" required></td>
                        <td><input class="form-control form-control-sm" name="price" type="number" step="0.01" value="<?= safe($r['price_per_night']) ?>" required></td>
                        <td><input class="form-control form-control-sm" name="capacity" type="number" min="1" value="<?= (int)$r['capacity'] ?>" required style="max-width:90px;"></td>
                        <td><input class="form-control form-control-sm" name="description" value="<?= safe($r['description']) ?>"></td>
                        <td class="d-flex gap-1">
                            <button class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i> Save</button>
                            <a href="manage_rooms.php?hotel_id=<?= (int)$hotel_id ?>" class="btn btn-sm btn-outline-secondary">Cancel</a>
                        </td>
                    </form>
                </tr>
            <?php else: ?>
                <tr>
                    <td>#<?= (int)$r['id'] ?></td>
                    <td><?= safe($r['room_type']) ?></td>
                    <td class="fw-semibold">TZS <?= number_format($r['price_per_night']) ?></td>
                    <td><i class="bi bi-people-fill text-muted me-1"></i><?= (int)$r['capacity'] ?></td>
                    <td><?= safe($r['description']) ?></td>
                    <td class="d-flex gap-2">
                        <a href="manage_rooms.php?hotel_id=<?= (int)$hotel_id ?>&edit_room=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil me-1"></i>Edit</a>
                        <form method="POST" onsubmit="return confirm('Delete this room?');">
                            <input type="hidden" name="delete_id" value="<?= (int)$r['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i>Delete</button>
                        </form>
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
