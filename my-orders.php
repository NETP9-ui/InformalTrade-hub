<?php
require_once "config.php";
session_start();

if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit; 
}

$user_id = $_SESSION['user_id'];
releaseDueEscrow($pdo);

// Handle rating submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rating'])) {
    $transaction_id = $_POST['transaction_id'];
    $seller_id = $_POST['seller_id'];
    $score = $_POST['score'];
    $comment = $_POST['comment'];

    $stmt = $pdo->prepare("INSERT INTO ratings 
        (transaction_id, buyer_id, seller_id, score, comment, created_at) 
        VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$transaction_id, $user_id, $seller_id, $score, $comment]);

    $success = "Rating and comment submitted for transaction #$transaction_id";
}

// Fetch buyer’s orders with shipment info
$stmt = $pdo->prepare("SELECT t.*, p.title, u.username AS seller_name, 
                              s.courier, s.tracking_number, s.status AS ship_status, s.estimated_delivery, s.shipped_at, s.fulfilled_at
    FROM transactions t
    JOIN products p ON t.prod_id = p.prod_id
    JOIN users u ON t.seller_id = u.user_id
    LEFT JOIN shipments s ON t.trans_id = s.transaction_id
    WHERE t.buyer_id = ?
    ORDER BY t.created_at DESC");
$stmt->execute([$user_id]);
$orders = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Orders - InformalTrade Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar bg-success">
        <div class="container">
            <a class="navbar-brand text-white" href="index.php"><i class="bi bi-house me-1"></i>Home</a>
            <a href="dashboard.php" class="btn btn-outline-light ms-auto"><i class="bi bi-arrow-left me-1"></i>Back</a>
        </div>
    </nav>
    <div class="container page-shell">
        <h2><i class="bi bi-bag me-2"></i>My Orders</h2>
        <?php if (isset($success)): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
        <?php if ($orders): ?>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>ID</th><th>Product</th><th>Seller</th><th>Amount</th>
                        <th>Status</th><th>Escrow</th><th>Date</th><th>Shipment</th><th>Contact</th><th>Rate Seller</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $o): ?>
                        <tr>
                            <td><?= $o['trans_id'] ?></td>
                            <td>
                                <?= htmlspecialchars($o['title']) ?>
                                <?php if (!empty($o['selected_size'])): ?>
                                    <div class="small text-muted">Size: <?= htmlspecialchars(strtoupper($o['selected_size'])) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($o['seller_name']) ?></td>
                            <td>R<?= $o['amount'] ?></td>
                            <td><?= ucfirst($o['status']) ?></td>
                            <td><?= ucfirst($o['escrow_status'] ?? 'pending') ?></td>
                            <td><?= $o['created_at'] ?></td>
                            <td>
                                <?php if ($o['courier']): ?>
                                    Courier: <?= htmlspecialchars($o['courier']) ?><br>
                                    Tracking #: <?= htmlspecialchars($o['tracking_number']) ?><br>
                                    Status: <?= htmlspecialchars($o['ship_status']) ?><br>
                                    Shipped: <?= htmlspecialchars($o['shipped_at']) ?><br>
                                    ETA: <?= htmlspecialchars($o['estimated_delivery']) ?>
                                    <?php if ($o['fulfilled_at']): ?><br>Fulfilled: <?= htmlspecialchars($o['fulfilled_at']) ?><?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">No shipment yet</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="messages.php?receiver_id=<?= $o['seller_id'] ?>&prod_id=<?= $o['prod_id'] ?>" class="btn btn-sm btn-primary"><i class="bi bi-chat-dots me-1"></i>Message Seller</a>
                            </td>
                            <td>
                                <!-- Rating Form -->
                                <form method="POST" class="row g-2">
                                    <input type="hidden" name="transaction_id" value="<?= $o['trans_id'] ?>">
                                    <input type="hidden" name="seller_id" value="<?= $o['seller_id'] ?>">

                                    <!-- Score -->
                                    <div class="col-md-4">
                                        <select name="score" class="form-select" required>
                                            <option value="">Score</option>
                                            <option value="1">1 - Poor</option>
                                            <option value="2">2</option>
                                            <option value="3">3</option>
                                            <option value="4">4</option>
                                            <option value="5">5 - Excellent</option>
                                        </select>
                                    </div>

                                    <!-- Comment -->
                                    <div class="col-md-6">
                                        <input type="text" name="comment" class="form-control" placeholder="Write a comment..." required>
                                    </div>

                                    <!-- Submit -->
                                    <div class="col-md-2">
                                        <button type="submit" name="rating" class="btn btn-sm btn-success"><i class="bi bi-check-circle me-1"></i>Submit</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?><div class="alert alert-info">No orders yet.</div><?php endif; ?>
    </div>
</body>
</html>
