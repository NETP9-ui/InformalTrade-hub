<?php
require_once "config.php";
session_start();

if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit; 
}

if (!isset($_GET['id'])) { 
    header("Location: product.php"); 
    exit; 
}

$prod_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
$stmt = $pdo->prepare("SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON p.cat_id = c.cat_id WHERE p.prod_id = ?");
$stmt->execute([$prod_id]);
$product = $stmt->fetch();
if (!$product) { echo "Product not found."; exit; }
if ($product['status'] !== 'active' || (int)$product['quantity'] <= 0) {
    echo "This product is not available for purchase.";
    exit;
}
$isClothing = isClothingCategoryName($product['category_name'] ?? '');
$sizeStock = $isClothing ? decodeClothingSizeStock($product['clothing_size_stock'] ?? '') : [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $buyer_id   = $_SESSION['user_id'];
    $card_number = $_POST['card_number']; 
    $expiry      = $_POST['expiry'];
    $cvv         = $_POST['cvv'];
    $quantity    = max(1, (int)($_POST['quantity'] ?? 1));
    $selected_size = strtolower(trim($_POST['selected_size'] ?? ''));

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON p.cat_id = c.cat_id WHERE p.prod_id = ? AND p.status = 'active' FOR UPDATE");
        $stmt->execute([$prod_id]);
        $product = $stmt->fetch();
        if (!$product || (int)$product['quantity'] < $quantity) {
            $pdo->rollBack();
            echo "Not enough stock available.";
            exit;
        }

        $isClothing = isClothingCategoryName($product['category_name'] ?? '');
        $sizeStock = $isClothing ? decodeClothingSizeStock($product['clothing_size_stock'] ?? '') : [];
        if ($isClothing) {
            if (!in_array($selected_size, clothingSizes(), true)) {
                $pdo->rollBack();
                echo "Please select a valid clothing size.";
                exit;
            }
            if (($sizeStock[$selected_size] ?? 0) < $quantity) {
                $pdo->rollBack();
                echo "Not enough stock available for size " . htmlspecialchars(strtoupper($selected_size)) . ".";
                exit;
            }
            $sizeStock[$selected_size] -= $quantity;
        } else {
            $selected_size = null;
        }

        $amount = $product['price'] * $quantity;

        $stmt = $pdo->prepare("INSERT INTO transactions 
            (prod_id, buyer_id, seller_id, amount, quantity, selected_size, status, escrow_status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, 'pending', 'pending', NOW())");
        $stmt->execute([$prod_id, $buyer_id, $product['user_id'], $amount, $quantity, $selected_size]);

        $trans_id = $pdo->lastInsertId();

        $newQuantity = (int)$product['quantity'] - $quantity;
        $newStatus = $newQuantity <= 0 ? 'sold_out' : 'active';
        if ($isClothing) {
            $stmt = $pdo->prepare("UPDATE products SET quantity = ?, clothing_size_stock = ?, status = ? WHERE prod_id = ?");
            $stmt->execute([$newQuantity, encodeClothingSizeStock($sizeStock), $newStatus, $prod_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE products SET quantity = ?, status = ? WHERE prod_id = ?");
            $stmt->execute([$newQuantity, $newStatus, $prod_id]);
        }

        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Purchase failed: " . $e->getMessage());
        echo "Purchase failed. Please try again.";
        exit;
    }

    header("Location: processing.php?trans_id=$trans_id&prod_id=$prod_id&buyer_id=$buyer_id&seller_id={$product['user_id']}&amount={$product['price']}&card=".substr($card_number,-4));
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Buy Product</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <h2>Buy: <?= htmlspecialchars($product['title']) ?></h2>
        <p><strong>Price:</strong> R<?= htmlspecialchars($product['price']) ?></p>
        <p><strong>Available:</strong> <?= (int)$product['quantity'] ?></p>
        <?php if ($isClothing): ?>
            <p><strong>Sizes:</strong>
                <?php foreach ($sizeStock as $size => $stock): ?>
                    <?php if ($stock > 0): ?>
                        <span class="badge bg-secondary text-uppercase"><?= htmlspecialchars($size) ?>: <?= (int)$stock ?></span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </p>
        <?php endif; ?>

        <form method="POST" class="mt-3">
		    <!-- Contact details -->
            <div class="mb-3">
                <label>Contact Number</label>
                <input type="text" name="contact" class="form-control" placeholder="e.g. 082 123 4567" required>
            </div>
            <div class="mb-3">
                <label>Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="you@example.com" required>
            </div>
            <div class="mb-3">
                <label>Delivery Address</label>
                <textarea name="address" class="form-control" rows="3" placeholder="Street, City, Province, Postal Code" required></textarea>
            </div>
            <!-- Payment details -->
            <div class="mb-3">
                <label>Card Number</label>
                <input type="text" name="card_number" class="form-control" placeholder="4111 1111 1111 1111" required>
            </div>
            <div class="mb-3">
                <label>Expiry Date (MM/YY)</label>
                <input type="text" name="expiry" class="form-control" placeholder="12/28" required>
            </div>
            <div class="mb-3">
                <label>CVV</label>
                <input type="text" name="cvv" class="form-control" placeholder="123" required>
            </div>

            <!-- Quantity -->
            <?php if ($isClothing): ?>
                <div class="mb-3">
                    <label>Size</label>
                    <select name="selected_size" id="selectedSize" class="form-select" required>
                        <option value="">Select size</option>
                        <?php foreach ($sizeStock as $size => $stock): ?>
                            <?php if ($stock > 0): ?>
                                <option value="<?= htmlspecialchars($size) ?>" data-stock="<?= (int)$stock ?>"><?= htmlspecialchars(strtoupper($size)) ?> (<?= (int)$stock ?> available)</option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="mb-3">
                <label>Quantity</label>
                <input type="number" name="quantity" id="quantityInput" class="form-control" min="1" max="<?= (int)$product['quantity'] ?>" value="1" required>
            </div>

            

            <!-- Buttons -->
            <button type="submit" class="btn btn-success"><i class="bi bi-credit-card me-1"></i>Card Payment</button>
            <a href="product.php" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
        </form>
    </div>
    <?php if ($isClothing): ?>
        <script>
            const selectedSize = document.getElementById('selectedSize');
            const quantityInput = document.getElementById('quantityInput');

            function syncQuantityMax() {
                const option = selectedSize.options[selectedSize.selectedIndex];
                const stock = parseInt(option?.dataset.stock || '1', 10);
                quantityInput.max = stock;
                if (parseInt(quantityInput.value || '1', 10) > stock) {
                    quantityInput.value = stock;
                }
            }

            selectedSize.addEventListener('change', syncQuantityMax);
            syncQuantityMax();
        </script>
    <?php endif; ?>
</body>
</html>
