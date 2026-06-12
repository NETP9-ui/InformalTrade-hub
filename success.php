<?php
require_once "config.php";
session_start();

if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit; 
}

$trans_id   = $_GET['trans_id'] ?? null;
$card_last4 = $_GET['card'] ?? '****';

if (!$trans_id) {
    echo "No transaction ID provided.";
    exit;
}

// Fetch transaction details with product, buyer, seller
$stmt = $pdo->prepare("SELECT t.*, p.title, p.price, u.username AS buyer, s.username AS seller
    FROM transactions t
    JOIN products p ON t.prod_id = p.prod_id
    JOIN users u ON t.buyer_id = u.user_id
    JOIN users s ON t.seller_id = s.user_id
    WHERE t.trans_id=?");
$stmt->execute([$trans_id]);
$txn = $stmt->fetch();

if (!$txn) {
    echo "Transaction not found.";
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Payment Result</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container page-shell">
        <?php if (in_array($txn['status'], ['paid', 'completed'])): ?>
            <div class="alert alert-success">
                Card payment successful with card ending in <?= htmlspecialchars($card_last4) ?>!<br>
                Transaction #<?= htmlspecialchars($trans_id) ?> has been marked <strong><?= htmlspecialchars($txn['status']) ?></strong>.
                <?php if (($txn['escrow_status'] ?? '') === 'held'): ?>
                    Funds are held in escrow until delivery is fulfilled or 5 days after shipment.
                <?php endif; ?>
            </div>
        <?php elseif ($txn['status'] === 'cancelled'): ?>
            <div class="alert alert-danger">
                Payment cancelled. Transaction #<?= htmlspecialchars($trans_id) ?> has been marked <strong>cancelled</strong>.
            </div>
        <?php else: ?>
            <div class="alert alert-warning">
                Transaction #<?= htmlspecialchars($trans_id) ?> is still <strong><?= htmlspecialchars($txn['status']) ?></strong>.
            </div>
        <?php endif; ?>

        <!-- Receipt details -->
        <div class="card mt-4">
            <div class="card-body">
                <h5 class="card-title"><?= htmlspecialchars($txn['title']) ?></h5>
                <?php if (!empty($txn['selected_size'])): ?>
                    <p><strong>Size:</strong> <?= htmlspecialchars(strtoupper($txn['selected_size'])) ?></p>
                <?php endif; ?>
                <p><strong>Amount:</strong> R<?= htmlspecialchars($txn['amount']) ?></p>
                <p><strong>Buyer:</strong> <?= htmlspecialchars($txn['buyer']) ?></p>
                <p><strong>Seller:</strong> <?= htmlspecialchars($txn['seller']) ?></p>
                <p><strong>Card:</strong> **** **** **** <?= htmlspecialchars($card_last4) ?></p>
                <p><strong>Date:</strong> <?= htmlspecialchars($txn['created_at']) ?></p>
            </div>
        </div>

        <a href="product.php" class="btn btn-primary mt-3"><i class="bi bi-arrow-left me-1"></i>Back to Marketplace</a>
    </div>
</body>
</html>
