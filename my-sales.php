<?php
require_once "config.php";
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
releaseDueEscrow($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['transaction_id'])) {
    $transaction_id = filter_var($_POST['transaction_id'], FILTER_VALIDATE_INT);

    if (isset($_POST['mark_fulfilled'])) {
        $stmt = $pdo->prepare("UPDATE shipments s
            JOIN transactions t ON t.trans_id = s.transaction_id
            SET s.status = 'fulfilled',
                s.fulfilled_at = NOW(),
                t.delivery_fulfilled = 1,
                t.delivery_fulfilled_at = NOW(),
                t.escrow_status = 'released',
                t.escrow_release_at = NOW(),
                t.status = 'completed'
            WHERE s.transaction_id = ? AND t.seller_id = ?");
        $stmt->execute([$transaction_id, $user_id]);
        $success = "Delivery fulfilled. Escrow has been released.";
    } else {
        $courier = $_POST['courier'];
        $tracking_number = $_POST['tracking_number'];
        $eta = $_POST['eta'];

        $stmt = $pdo->prepare("INSERT INTO shipments
            (transaction_id, courier, tracking_number, status, estimated_delivery, shipped_at)
            VALUES (?, ?, ?, 'shipped', ?, NOW())
            ON DUPLICATE KEY UPDATE courier=VALUES(courier), tracking_number=VALUES(tracking_number), status='shipped', estimated_delivery=VALUES(estimated_delivery), shipped_at=NOW()");
        $stmt->execute([$transaction_id, $courier, $tracking_number, $eta]);

        $stmt = $pdo->prepare("UPDATE transactions SET status='shipped' WHERE trans_id=? AND seller_id=?");
        $stmt->execute([$transaction_id, $user_id]);

        $success = "Shipment details added for transaction #$transaction_id";
    }
}

$stmt = $pdo->prepare("SELECT t.*, p.title, u.username AS buyer_name,
        s.courier, s.tracking_number, s.status AS ship_status, s.estimated_delivery, s.shipped_at, s.fulfilled_at
    FROM transactions t
    JOIN products p ON t.prod_id = p.prod_id
    JOIN users u ON t.buyer_id = u.user_id
    LEFT JOIN shipments s ON s.transaction_id = t.trans_id
    WHERE t.seller_id = ?
    ORDER BY t.created_at DESC");
$stmt->execute([$user_id]);
$sales = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Sales - InformalTrade Hub</title>
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
        <h2><i class="bi bi-cash-coin me-2"></i>My Sales</h2>
        <?php if (isset($success)): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($sales): ?>
            <table class="table table-bordered">
                <thead><tr><th>ID</th><th>Product</th><th>Buyer</th><th>Amount</th><th>Status</th><th>Escrow</th><th>Date</th><th>Shipment</th><th>Fulfillment</th></tr></thead>
                <tbody>
                    <?php foreach ($sales as $s): ?>
                        <tr>
                            <td><?= $s['trans_id'] ?></td>
                            <td>
                                <?= htmlspecialchars($s['title']) ?>
                                <?php if (!empty($s['selected_size'])): ?>
                                    <div class="small text-muted">Size: <?= htmlspecialchars(strtoupper($s['selected_size'])) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($s['buyer_name']) ?></td>
                            <td>R<?= $s['amount'] ?></td>
                            <td><?= ucfirst($s['status']) ?></td>
                            <td><?= ucfirst($s['escrow_status'] ?? 'pending') ?></td>
                            <td><?= $s['created_at'] ?></td>
                            <td>
                                <?php if ($s['tracking_number']): ?>
                                    Courier: <?= htmlspecialchars($s['courier']) ?><br>
                                    Tracking #: <?= htmlspecialchars($s['tracking_number']) ?><br>
                                    Status: <?= htmlspecialchars($s['ship_status']) ?><br>
                                    Shipped: <?= htmlspecialchars($s['shipped_at']) ?><br>
                                    ETA: <?= htmlspecialchars($s['estimated_delivery']) ?>
                                <?php else: ?>
                                    <form method="POST" class="row g-2">
                                        <input type="hidden" name="transaction_id" value="<?= $s['trans_id'] ?>">
                                        <div class="col-md-3"><input type="text" name="courier" class="form-control" placeholder="Courier" required></div>
                                        <div class="col-md-3"><input type="text" name="tracking_number" class="form-control" placeholder="Tracking #" required></div>
                                        <div class="col-md-3"><input type="date" name="eta" class="form-control" required></div>
                                        <div class="col-md-3"><button type="submit" class="btn btn-sm btn-success"><i class="bi bi-plus-circle me-1"></i>Add</button></div>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($s['tracking_number'] && empty($s['delivery_fulfilled'])): ?>
                                    <form method="POST">
                                        <input type="hidden" name="transaction_id" value="<?= $s['trans_id'] ?>">
                                        <button type="submit" name="mark_fulfilled" value="1" class="btn btn-sm btn-primary">Tick Fulfilled & Release Escrow</button>
                                    </form>
                                    <small class="text-muted">Auto-release: 5 days after shipped date.</small>
                                <?php elseif (!empty($s['delivery_fulfilled'])): ?>
                                    <span class="badge bg-success">Fulfilled</span><br>
                                    <small><?= htmlspecialchars($s['delivery_fulfilled_at']) ?></small>
                                <?php else: ?>
                                    <span class="text-muted">Add shipment first</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?><div class="alert alert-info">No sales yet.</div><?php endif; ?>
    </div>
</body>
</html>
