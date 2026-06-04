<?php
require_once "config.php";
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Get transaction details from query
$trans_id   = $_GET['trans_id'] ?? null;
$card_last4 = $_GET['card'] ?? '****';

if (!$trans_id) {
    echo "No transaction ID provided.";
    exit;
}

// Cancel path
if (isset($_GET['cancel'])) {
    $stmt = $pdo->prepare("SELECT prod_id, quantity, selected_size FROM transactions WHERE trans_id=? AND status='pending'");
    $stmt->execute([$trans_id]);
    $pendingTxn = $stmt->fetch();

    if ($pendingTxn) {
        $stmt = $pdo->prepare("SELECT clothing_size_stock FROM products WHERE prod_id=?");
        $stmt->execute([$pendingTxn['prod_id']]);
        $product = $stmt->fetch();

        if (!empty($pendingTxn['selected_size']) && $product) {
            $sizeStock = decodeClothingSizeStock($product['clothing_size_stock'] ?? '');
            $selectedSize = strtolower($pendingTxn['selected_size']);
            if (array_key_exists($selectedSize, $sizeStock)) {
                $sizeStock[$selectedSize] += (int)$pendingTxn['quantity'];
                $stmt = $pdo->prepare("UPDATE products SET quantity = quantity + ?, clothing_size_stock = ?, status='active' WHERE prod_id=?");
                $stmt->execute([$pendingTxn['quantity'], encodeClothingSizeStock($sizeStock), $pendingTxn['prod_id']]);
            }
        } else {
            $stmt = $pdo->prepare("UPDATE products SET quantity = quantity + ?, status='active' WHERE prod_id=?");
            $stmt->execute([$pendingTxn['quantity'], $pendingTxn['prod_id']]);
        }
    }

    $stmt = $pdo->prepare("UPDATE transactions 
                           SET status='cancelled', escrow_status='refunded' 
                           WHERE trans_id=?");
    $stmt->execute([$trans_id]);

    echo "<!DOCTYPE html>
    <html><head>
        <title>Payment Cancelled</title>
        <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css' rel='stylesheet'>
    </head><body class='bg-light'>
        <div class='container mt-5'>
            <div class='alert alert-danger'>
                Payment cancelled. Transaction #".htmlspecialchars($trans_id)." has been marked <strong>cancelled</strong>.
            </div>
            <a href='product.php' class='btn btn-secondary'>Back to Marketplace</a>
        </div>
    </body></html>";
    exit;
} else {
    // Success path: payment completed, funds are held in escrow
    $stmt = $pdo->prepare("UPDATE transactions 
                           SET status='paid', escrow_status='held' 
                           WHERE trans_id=?");
    $stmt->execute([$trans_id]);

    // Show processing then redirect
    echo "<!DOCTYPE html>
    <html><head>
        <title>Processing Payment</title>
        <meta http-equiv='refresh' content='2;url=success.php?trans_id=$trans_id&card=$card_last4'>
        <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css' rel='stylesheet'>
    </head><body class='bg-light'>
        <div class='container mt-5'>
            <div class='alert alert-info'>
                Processing transaction #".htmlspecialchars($trans_id)." for card ending in ".htmlspecialchars($card_last4)."...
            </div>
            <p>You will be redirected to the payment result shortly.</p>
        </div>
    </body></html>";
    exit;
}
?>
