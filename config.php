<?php
// =====================================================
// DATABASE CONNECTION (config.php)
// Include this at the top of every backend file
// =====================================================

// Connection settings — adjust if needed
// -----------------------------------------------------
// CURRENT (Linux / this machine):
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'hotel_marketplace';
//
// FOR XAMPP ON WINDOWS, comment out the 3 lines above for USER/PASS
// and use XAMPP's defaults instead:
//   $DB_USER = 'root';
//   $DB_PASS = '';        // XAMPP's root password is empty by default
// -----------------------------------------------------

try {
    // PDO is the modern, safer way to connect to MySQL
    // It supports prepared statements which PREVENT SQL INJECTION
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,           // throw exceptions on errors
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,      // results as associative arrays
            PDO::ATTR_EMULATE_PREPARES => false,                   // use real prepared statements
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Start the session for login tracking
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Helper function: check if a user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Helper function: is the current user an administrator?
function is_admin() {
    return is_logged_in() && ($_SESSION['role'] ?? '') === 'admin';
}

// Helper: require login as a manager OR an admin (used by management pages).
// Admins are allowed everywhere a manager is.
function require_manager_or_admin() {
    if (!is_logged_in() || !in_array($_SESSION['role'] ?? '', ['manager', 'admin'], true)) {
        header('Location: login.php');
        exit;
    }
}

// Helper function: require a specific role or redirect
function require_role($role) {
    if (!is_logged_in() || $_SESSION['role'] !== $role) {
        header('Location: login.php');
        exit;
    }
}

// Helper function: sanitize output to prevent XSS attacks
function safe($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

// Helper: resolve a hotel image. Stored value may be a full https URL
// (real hotels) or a local filename in images/ (legacy uploads).
function hotel_img($image) {
    $image = $image ?? '';
    if ($image === '') {
        return 'https://images.unsplash.com/photo-1455587734955-081b22074882?auto=format&fit=crop&w=800&q=80';
    }
    if (preg_match('#^https?://#i', $image)) {
        return $image;
    }
    return 'images/' . $image;
}

// Catalog of bookable trip add-ons (the Tanzania differentiator).
// key => [label, price in TZS, bootstrap icon]
function trip_addons() {
    return [
        'kilimanjaro_dayhike' => ['Kilimanjaro Day Hike (Marangu Gate)', 150000, 'bi-tsunami'],
        'serengeti_safari'    => ['Serengeti / Ngorongoro Day Safari',   250000, 'bi-binoculars'],
        'materuni_tour'       => ['Materuni Waterfalls & Coffee Tour',     90000, 'bi-tree'],
        'airport_transfer'    => ['Airport Transfer (Kilimanjaro Intl.)',  60000, 'bi-airplane'],
    ];
}

// Map an amenity name to a Bootstrap icon (falls back to a tick).
function amenity_icon($name) {
    $map = [
        'WiFi' => 'bi-wifi', 'Pool' => 'bi-water', 'Parking' => 'bi-p-square',
        'Restaurant' => 'bi-cup-hot', 'Bar' => 'bi-cup-straw', 'Gym' => 'bi-bicycle',
        'Spa' => 'bi-flower1', 'Airport Shuttle' => 'bi-airplane', 'Breakfast' => 'bi-egg-fried',
        'AC' => 'bi-snow', 'Room Service' => 'bi-bell', 'Garden' => 'bi-flower2',
        'Family Rooms' => 'bi-people', 'Mountain View' => 'bi-image-alt',
    ];
    return $map[trim($name)] ?? 'bi-check-circle';
}

// Split a CSV column into a clean array (amenities / gallery).
function csv_list($csv) {
    if (!$csv) return [];
    return array_values(array_filter(array_map('trim', explode(',', $csv)), fn($x) => $x !== ''));
}

// Helper: render a 0–5 star rating as Bootstrap icons
function stars($rating) {
    $rating = (float)$rating;
    if ($rating <= 0) return '';
    $full = (int)floor($rating);
    $half = ($rating - $full) >= 0.5 ? 1 : 0;
    $html = '';
    for ($i = 0; $i < $full; $i++)       $html .= '<i class="bi bi-star-fill"></i>';
    if ($half)                            $html .= '<i class="bi bi-star-half"></i>';
    for ($i = $full + $half; $i < 5; $i++) $html .= '<i class="bi bi-star"></i>';
    return '<span class="rating-stars text-warning">' . $html .
           '</span> <span class="text-muted small">' . number_format($rating, 1) . '</span>';
}
?>
