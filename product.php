<?php 
require_once 'config.php'; 
checkRole($pdo);

$current_user_id = $_SESSION['user_id'];

// Search functionality
$search = $_GET['search'] ?? '';
$sql = "SELECT p.*, u.username, c.name as category,
               COALESCE(AVG(r.score),0) AS avg_rating,
               COUNT(r.score) AS total_reviews
        FROM products p 
        JOIN users u ON p.user_id = u.user_id 
        JOIN categories c ON p.cat_id = c.cat_id 
        LEFT JOIN ratings r ON p.user_id = r.seller_id
        WHERE p.status = 'active' AND p.quantity > 0";
$params = [];
if ($search) {
    $sql .= " AND (p.title LIKE ? OR p.location LIKE ?)";
    $params = ["%$search%", "%$search%"];
}
$sql .= " GROUP BY p.prod_id ORDER BY p.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>All Products - InformalTrade Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar bg-success">
        <div class="container">
            <a class="navbar-brand text-white" href="index.php"><i class="bi bi-house me-1"></i>Home</a>
            <a href="dashboard.php" class="btn btn-outline-light ms-auto"><i class="bi bi-arrow-left me-1"></i>Back to Dashboard</a>
        </div>
    </nav>
    <div class="container mt-4">
        <h2><i class="bi bi-grid me-2"></i>All Active Listings</h2>

        <!-- Search bar -->
        <form method="GET" class="mb-4">
            <div class="input-group">
                <input type="text" name="search" class="form-control" placeholder="Search by title or location" value="<?= htmlspecialchars($search) ?>">
                <button class="btn btn-success" type="submit"><i class="bi bi-search me-1"></i>Search</button>
            </div>
        </form>

        <div class="row">
            <?php if (count($products) > 0): ?>
                <?php foreach($products as $p): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            <?php if (!empty($p['image_url'])): ?>
                                <img src="uploads/<?= htmlspecialchars($p['image_url']) ?>" class="card-img-top" alt="<?= htmlspecialchars($p['title']) ?>">
                            <?php endif; ?>
                            <div class="card-body">
                                <h5 class="card-title"><?= htmlspecialchars($p['title']) ?></h5>
                                <p class="card-text"><?= htmlspecialchars($p['description']) ?></p>
                                <p><strong>Price:</strong> R<?= htmlspecialchars($p['price']) ?></p>
                                <p><strong>Available:</strong> <?= (int)$p['quantity'] ?></p>
                                <?php if (isClothingCategoryName($p['category'] ?? '')): ?>
                                    <p><strong>Sizes:</strong>
                                        <?php foreach (decodeClothingSizeStock($p['clothing_size_stock'] ?? '') as $size => $stock): ?>
                                            <?php if ($stock > 0): ?>
                                                <span class="badge bg-secondary text-uppercase"><?= htmlspecialchars($size) ?>: <?= (int)$stock ?></span>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </p>
                                <?php endif; ?>
                                <p><strong>Seller:</strong> <?= htmlspecialchars($p['username']) ?></p>
                                <p><strong>Category:</strong> <?= htmlspecialchars($p['category']) ?></p>
                                <p><strong>Location:</strong> <?= htmlspecialchars($p['location']) ?></p>
                                <p><strong>Rating:</strong> 
                                    <?= $p['avg_rating'] > 0 
                                        ? number_format($p['avg_rating'],1) . " (" . $p['total_reviews'] . " reviews)" 
                                        : "No reviews yet" ?>
                                </p>
                            </div>
                            <div class="card-footer text-center">
                                <!-- Only show Buy and Message buttons if user is not the seller -->
                                <?php if ($p['user_id'] != $current_user_id): ?>
                                    <a href="buy-product.php?id=<?= $p['prod_id'] ?>" class="btn btn-success me-2">
                                        <i class="bi bi-bag-check me-1"></i>Buy Now
                                    </a>
                                    <a href="messages.php?receiver_id=<?= $p['user_id'] ?>&prod_id=<?= $p['prod_id'] ?>" class="btn btn-primary me-2">
                                        <i class="bi bi-chat-dots me-1"></i>Message Seller
                                    </a>
                                <?php endif; ?>
                                <a href="seller-profile.php?id=<?= $p['user_id'] ?>" class="btn btn-info">
                                    <i class="bi bi-person me-1"></i>View Profile
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No active listings found.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
