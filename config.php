<?php

define('DB_HOST', 'sql107.infinityfree.com');
define('DB_USER', 'if0_41830908');
define('DB_PASS', 'JePggXzKF2IP');
define('DB_NAME', 'if0_41830908_informaltradehub');

// Secure Session Settings (BEFORE session start)
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');

session_start();

// Database Connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    error_log("DB Connection Failed: " . $e->getMessage());
    die("Database unavailable.");
}

function clothingSizes() {
    return ['xxs', 'xs', 's', 'm', 'l', 'xl', 'xxl'];
}

function isClothingCategoryName($name) {
    $normalized = strtolower(trim((string)$name));
    return $normalized === 'clothing' || strpos($normalized, 'clothing') !== false || strpos($normalized, 'clothes') !== false;
}

function decodeClothingSizeStock($value) {
    $decoded = json_decode((string)$value, true);
    $stock = [];
    foreach (clothingSizes() as $size) {
        $stock[$size] = max(0, (int)($decoded[$size] ?? 0));
    }
    return $stock;
}

function encodeClothingSizeStock($stock) {
    $clean = [];
    foreach (clothingSizes() as $size) {
        $clean[$size] = max(0, (int)($stock[$size] ?? 0));
    }
    return json_encode($clean);
}

function totalClothingStock($stock) {
    return array_sum(array_map('intval', $stock));
}

$admins = [
    ['admin', 'admin@hub.com', 'Admin@123'],      
    ['admin1', 'superadmin@hub.com', 'Admin@2026']  
];

foreach ($admins as $admin) {
    $username = $admin[0];
    $email = $admin[1];
    $plain_pass = $admin[2];
    
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username=?");
    $stmt->execute([$username]);
    
    if (!$stmt->fetch()) {
        $hashed = password_hash($plain_pass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, is_active, created_at)
                               VALUES (?, ?, ?, 'admin', 1, NOW())");
        $stmt->execute([$username, $email, $hashed]);
    }
}


function getCurrentUser($pdo) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function checkRole($pdo) {
    $user = getCurrentUser($pdo);
    return $user['role'] ?? 'guest';
}

function generateCSRF() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function releaseDueEscrow($pdo) {
    try {
        $stmt = $pdo->prepare("UPDATE transactions t
            JOIN shipments s ON s.transaction_id = t.trans_id
            SET t.escrow_status = 'released',
                t.escrow_release_at = NOW(),
                t.status = 'completed'
            WHERE t.escrow_status = 'held'
              AND s.shipped_at IS NOT NULL
              AND s.shipped_at <= DATE_SUB(NOW(), INTERVAL 5 DAY)");
        $stmt->execute();
    } catch (PDOException $e) {
        error_log("Escrow auto-release failed: " . $e->getMessage());
    }
}
?>
