<?php
require_once "config.php";
session_start();

if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit; 
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $sender_id   = $_SESSION['user_id'];
    $receiver_id = filter_var($_POST['receiver_id'] ?? '', FILTER_VALIDATE_INT);
    $prod_id     = filter_var($_POST['prod_id'] ?? null, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
    $message     = trim($_POST['message'] ?? '');
    $csrf_token  = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf_token)) {
        header("Location: messages.php?receiver_id=" . urlencode((string) $receiver_id) . "&error=invalid_request");
        exit;
    }

    if ($receiver_id && $receiver_id !== $sender_id && $message !== "") {
        try {
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE user_id = ?");
            $stmt->execute([$receiver_id]);
            if (!$stmt->fetch()) {
                header("Location: product.php");
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, prod_id, message, created_at) 
                                   VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$sender_id, $receiver_id, $prod_id, $message]);
        } catch (PDOException $e) {
            error_log("Message send failed: " . $e->getMessage()); // Log for prod
            // For dev: echo $e->getMessage(); exit;
        }
    }
}

// Preserve prod_id in redirect
$redirect_url = "messages.php?receiver_id=" . urlencode($receiver_id);
if ($prod_id) $redirect_url .= "&prod_id=" . urlencode($prod_id);
header("Location: $redirect_url");
exit;
?>
