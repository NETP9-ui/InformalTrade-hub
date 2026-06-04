<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>InformalTrade Hub - South Africa C2C Marketplace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-success sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold fs-3" href="index.php">
                <i class="bi bi-shop me-2"></i>InformalTrade Hub
            </a>
            <div class="navbar-nav ms-auto">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a class="nav-link" href="dashboard.php"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
                    <a class="nav-link" href="product.php"><i class="bi bi-grid me-1"></i>Browse</a>
                    <?php if ($_SESSION['role'] == 'seller'): ?>
                        <a class="nav-link" href="list-product.php">
                            <i class="bi bi-plus-circle"></i> Sell Item
                        </a>
                    <?php endif; ?>
                    <span class="nav-link disabled"><?= htmlspecialchars($_SESSION['username']) ?></span>
                    <a class="nav-link" href="logout.php">Logout</a>
                <?php else: ?>
                    <a class="nav-link" href="login.php"><i class="bi bi-box-arrow-in-right me-1"></i>Login</a>
                    <a class="nav-link" href="register.php"><i class="bi bi-person-plus me-1"></i>Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4 bg-light min-vh-100">
        <div class="row">
            <!-- Hero Section -->
            <div class="col-12 text-center mb-5">
                <h1 class="display-4 fw-bold text-success mb-3">
                    Your Local C2C Marketplace
                </h1>
                <p class="lead">Buy & Sell safely in South Africa </p>
                <form method="GET" class="d-flex justify-content-center mb-4" style="max-width: 500px; margin: 0 auto;">
                    <input type="text" name="q" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
                           class="form-control form-control-lg me-2" placeholder="Search airfryers in Soweto...">
                    <button class="btn btn-success btn-lg px-4"><i class="bi bi-search me-1"></i>Search</button>
                </form>
            </div>
        </div>

        <!-- Featured Products Grid -->
        <div class="container">
            <h2 class="mb-4"><i class="bi bi-stars me-2"></i>Latest Local Listings</h2>
            <div class="row g-4" id="product-grid">
                <?php
                $search = $_GET['q'] ?? '';
                $sql = "SELECT p.*, u.username, c.name AS category_name
                        FROM products p
                        JOIN users u ON p.user_id = u.user_id
                        JOIN categories c ON p.cat_id = c.cat_id
                        WHERE p.status = 'active' AND p.quantity > 0";
                $params = [];
                if ($search) {
                    $sql .= " AND (title LIKE ? OR description LIKE ? OR location LIKE ?)";
                    $params = ["%$search%", "%$search%", "%$search%"];
                }
                $sql .= " ORDER BY created_at DESC LIMIT 12";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $products = $stmt->fetchAll();

                if (empty($products)): ?>
                    <div class="col-12">
                        <div class="text-center py-5">
                            <i class="bi bi-shop display-1 text-muted mb-3"></i>
                            <h4>No listings yet</h4>
                            <p>Be the first! <a href="register.php" class="btn btn-success">Start Selling</a></p>
                        </div>
                    </div>
                <?php else:
                    foreach ($products as $p): ?>
                    <div class="col-lg-3 col-md-6">
                        <div class="card h-100 shadow-sm hover-shadow">
                            <?php if ($p['image_url']): ?>
                                <img src="uploads/<?= htmlspecialchars($p['image_url']) ?>"
                                     class="card-img-top" alt="<?= htmlspecialchars($p['title']) ?>"
                                     style="height: 200px; object-fit: cover;">
                            <?php else: ?>
                                <div class="bg-secondary d-flex align-items-center justify-content-center"
                                     style="height: 200px; color: white;">
                                    <i class="bi bi-image me-2"></i>No Image
                                </div>
                            <?php endif; ?>
                            <div class="card-body d-flex flex-column">
                                <h6 class="card-title fw-bold"><?= htmlspecialchars($p['title']) ?></h6>
                                <p class="flex-grow-1 text-muted small"><?= substr($p['description'], 0, 80) ?>...</p>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="fs-4 fw-bold text-success">R<?= number_format($p['price'], 2) ?></span>
                                    <small class="badge bg-info"><?= htmlspecialchars($p['location'] ?? 'Gauteng') ?></small>
                                </div>
                                <small class="text-muted mb-2">Available: <?= (int)$p['quantity'] ?></small>
                                <?php if (isClothingCategoryName($p['category_name'] ?? '')): ?>
                                    <div class="mb-2">
                                        <?php foreach (decodeClothingSizeStock($p['clothing_size_stock'] ?? '') as $size => $stock): ?>
                                            <?php if ($stock > 0): ?>
                                                <span class="badge bg-secondary text-uppercase"><?= htmlspecialchars($size) ?>: <?= (int)$stock ?></span>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <small class="text-muted mb-2"><i class="bi bi-person me-1"></i>Seller: <?= htmlspecialchars($p['username']) ?></small>
                                <div class="d-grid gap-2 mt-auto">
                                    <a href="buy-product.php?id=<?= $p['prod_id'] ?>" class="btn btn-success btn-sm">
                                        <i class="bi bi-bag-check me-1"></i>Buy Securely
                                    </a>
                                    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $p['user_id']): ?>
                                        <a href="messages.php?receiver_id=<?= $p['user_id'] ?>&prod_id=<?= $p['prod_id'] ?>" class="btn btn-outline-primary btn-sm">
                                            <i class="bi bi-chat-dots me-1"></i>Message Seller
                                        </a>
                                    <?php elseif (!isset($_SESSION['user_id'])): ?>
                                        <a href="login.php" class="btn btn-outline-primary btn-sm">
                                            <i class="bi bi-box-arrow-in-right me-1"></i>Login to Message Seller
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach;
                endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mobile search enhancement
        document.querySelector('input[name="q"]').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') this.form.submit();
        });
    </script>
</body>
</html>
