<?php
require_once "config.php";
session_start();

if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit; 
}

$seller_id = $_GET['id'] ?? null;
if (!$seller_id) { echo "No seller selected."; exit; }
$seller_id = filter_var($seller_id, FILTER_VALIDATE_INT);
if (!$seller_id) { echo "Invalid seller selected."; exit; }

// Fetch seller info
$stmt = $pdo->prepare("SELECT username FROM users WHERE user_id=?");
$stmt->execute([$seller_id]);
$seller = $stmt->fetch();
if (!$seller) { echo "Seller not found."; exit; }

// Fetch average rating + reviews
$stmtRating = $pdo->prepare("SELECT AVG(score) AS avg_rating, COUNT(score) AS total_reviews 
                             FROM ratings WHERE seller_id=?");
$stmtRating->execute([$seller_id]);
$ratingStats = $stmtRating->fetch();

$stmtReviews = $pdo->prepare("SELECT r.*, u.username AS buyer_name 
                              FROM ratings r 
                              JOIN users u ON r.buyer_id = u.user_id 
                              WHERE r.seller_id=? 
                              ORDER BY r.created_at DESC");
$stmtReviews->execute([$seller_id]);
$reviews = $stmtReviews->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Seller Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container page-shell">
    <h2><i class="bi bi-person me-2"></i>Seller: <?= htmlspecialchars($seller['username']) ?></h2>
    <p><strong>Average Rating:</strong> 
       <?= $ratingStats['avg_rating'] ? number_format($ratingStats['avg_rating'], 1) . " (" . $ratingStats['total_reviews'] . " reviews)" : "No reviews yet" ?>
    </p>

    <?php if ($seller_id != $_SESSION['user_id']): ?>
        <a href="messages.php?receiver_id=<?= $seller_id ?>" class="btn btn-primary mb-3"><i class="bi bi-chat-dots me-1"></i>Message Seller</a>
    <?php endif; ?>

    <h4>Reviews</h4>
    <?php if ($reviews): ?>
        <ul class="list-group">
            <?php foreach ($reviews as $rev): ?>
                <li class="list-group-item">
                    <strong><?= htmlspecialchars($rev['buyer_name']) ?>:</strong> 
                    <?= htmlspecialchars($rev['comment']) ?> 
                    <span class="text-warning"> (<?= $rev['score'] ?>/5)</span>
                    <small class="text-muted"> - <?= $rev['created_at'] ?></small>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="text-muted">No reviews yet.</p>
    <?php endif; ?>
</div>
</body>
</html>
