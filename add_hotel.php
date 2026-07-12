<?php
// =====================================================
// ADD / EDIT A HOTEL (add_hotel.php)
// A logged-in host creates a new property listing, or edits
// an existing one (add_hotel.php?id=N).
// Access: a manager may only add/edit their OWN hotel; an
// admin may edit any hotel. Customers who list a property
// are upgraded to 'manager'.
// =====================================================
require_once 'config.php';

if (!is_logged_in()) {
    header('Location: list_property.php');
    exit;
}

// Upgrade a customer to a host the moment they list a property
if ($_SESSION['role'] === 'customer') {
    $pdo->prepare("UPDATE users SET role = 'manager' WHERE id = ?")->execute([$_SESSION['user_id']]);
    $_SESSION['role'] = 'manager';
}

$error   = '';
$edit_id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$hotel   = null;

// ONE HOTEL PER MANAGER (hard limit).
// When adding a new hotel (not editing), a manager who already owns one
// is sent back to their dashboard. Admins are exempt.
if (!$edit_id && !is_admin()) {
    $owned = $pdo->prepare("SELECT COUNT(*) FROM hotels WHERE manager_id = ?");
    $owned->execute([$_SESSION['user_id']]);
    if ($owned->fetchColumn() > 0) {
        header('Location: manager_dashboard.php?limit=1');
        exit;
    }
}

// In edit mode, load the hotel and enforce ownership (admins bypass the owner check)
if ($edit_id) {
    if (is_admin()) {
        $stmt = $pdo->prepare("SELECT * FROM hotels WHERE id = ?");
        $stmt->execute([$edit_id]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM hotels WHERE id = ? AND manager_id = ?");
        $stmt->execute([$edit_id, $_SESSION['user_id']]);
    }
    $hotel = $stmt->fetch();
    if (!$hotel) die("Unauthorized: This hotel doesn't belong to you.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name'] ?? '');
    $location    = trim($_POST['location'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $image       = trim($_POST['image'] ?? '');
    $website     = trim($_POST['website'] ?? '');
    $phone       = trim($_POST['phone'] ?? '');

    if ($name === '' || $location === '') {
        $error = 'Property name and location are required.';
    } elseif ($website !== '' && !filter_var($website, FILTER_VALIDATE_URL)) {
        $error = 'Please enter a valid website URL (including https://) or leave it blank.';
    } elseif ($image !== '' && !filter_var($image, FILTER_VALIDATE_URL)) {
        $error = 'The photo must be a valid image URL (including https://) or left blank.';
    } elseif ($edit_id) {
        // ---- UPDATE existing hotel (ownership already verified above) ----
        $stmt = $pdo->prepare("
            UPDATE hotels SET name = ?, location = ?, description = ?, image = ?, website = ?, phone = ?
            WHERE id = ?
        ");
        $stmt->execute([$name, $location, $description, $image ?: null, $website ?: null, $phone ?: null, $edit_id]);
        $back = is_admin() ? 'admin_dashboard.php' : 'manager_dashboard.php';
        header('Location: ' . $back);
        exit;
    } else {
        // ---- INSERT new hotel ----
        $stmt = $pdo->prepare("
            INSERT INTO hotels (manager_id, name, location, description, image, website, phone)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'], $name, $location, $description,
            $image ?: null, $website ?: null, $phone ?: null
        ]);
        $new_id = (int)$pdo->lastInsertId();
        // Straight on to adding rooms for the new property
        header('Location: manage_rooms.php?hotel_id=' . $new_id . '&new=1');
        exit;
    }
}

// Pre-fill values: a failed POST keeps what the user typed; edit mode shows the saved hotel.
$val = function ($field) use ($hotel) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') return $_POST[$field] ?? '';
    return $hotel[$field] ?? '';
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $edit_id ? 'Edit' : 'Add' ?> Your Property - Hotel Marketplace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
</head>
<body>
<?php $dashboard = is_admin() ? 'admin_dashboard.php' : 'manager_dashboard.php'; ?>
<nav class="navbar navbar-dark <?= is_admin() ? 'bg-danger' : 'bg-success' ?>">
    <div class="container">
        <a class="navbar-brand" href="<?= $dashboard ?>"><i class="bi bi-building-fill-check me-1"></i> <?= is_admin() ? 'Admin Dashboard' : 'Manager Dashboard' ?></a>
        <a href="<?= $dashboard ?>" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
</nav>

<main class="container my-4" style="max-width: 760px;">
    <?php if ($edit_id): ?>
        <h2 class="section-title"><i class="bi bi-pencil-square text-success me-1"></i>Edit your property</h2>
        <p class="text-muted">Update your hotel's details below. Use “Manage Rooms &amp; Services” to change rooms and prices.</p>
    <?php else: ?>
        <h2 class="section-title"><i class="bi bi-building-add text-success me-1"></i>Add your property</h2>
        <p class="text-muted">Tell travellers about your hotel. You'll add rooms and prices in the next step.</p>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger d-flex align-items-center"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= safe($error) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body p-4 p-md-5">
            <form method="POST">
                <?php if ($edit_id): ?><input type="hidden" name="id" value="<?= (int)$edit_id ?>"><?php endif; ?>
                <div class="mb-3">
                    <label class="form-label">Property name *</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Kilimanjaro View Lodge" value="<?= safe($val('name')) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Location *</label>
                    <input type="text" name="location" class="form-control" list="loc-suggestions" placeholder="e.g. Moshi, Tanzania" value="<?= safe($val('location')) ?>" required>
                    <datalist id="loc-suggestions">
                        <option value="Moshi, Tanzania">
                        <option value="Arusha, Tanzania">
                    </datalist>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="What makes your property special? Amenities, views, location..."><?= safe($val('description')) ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Cover photo URL <span class="text-muted fw-normal">(optional)</span></label>
                    <input type="url" name="image" class="form-control" placeholder="https://..." value="<?= safe($val('image')) ?>">
                    <div class="form-text">Paste a link to a photo of your property. Leave blank to use a default image.</div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Website <span class="text-muted fw-normal">(optional)</span></label>
                        <input type="url" name="website" class="form-control" placeholder="https://..." value="<?= safe($val('website')) ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Contact phone <span class="text-muted fw-normal">(optional)</span></label>
                        <input type="text" name="phone" class="form-control" placeholder="+255 ..." value="<?= safe($val('phone')) ?>">
                    </div>
                </div>
                <?php if ($edit_id): ?>
                    <button type="submit" class="btn btn-success w-100 btn-lg mt-2"><i class="bi bi-check2-circle me-1"></i>Save changes</button>
                <?php else: ?>
                    <button type="submit" class="btn btn-success w-100 btn-lg mt-2"><i class="bi bi-arrow-right-circle me-1"></i>Continue — add rooms</button>
                <?php endif; ?>
            </form>
        </div>
    </div>
</main>

<footer class="site-footer">
    Hotel Marketplace System &copy; <?= date('Y') ?> — Judith Antoni Obedi
</footer>
</body>
</html>
