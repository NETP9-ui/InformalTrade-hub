<?php
require_once "config.php";
session_start();

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$stmt = $pdo->prepare("SELECT role FROM users WHERE user_id=?");
$stmt->execute([$_SESSION['user_id']]);
$role = $stmt->fetchColumn();
if ($role !== 'admin') { echo "Access denied."; exit; }

releaseDueEscrow($pdo);

$success = '';

if (isset($_GET['toggle_user'])) {
    $stmt = $pdo->prepare("UPDATE users SET is_active=IF(is_active=1,0,1) WHERE user_id=?");
    $stmt->execute([$_GET['toggle_user']]);
    $success = "User status updated.";
}

if (isset($_POST['update_transaction'])) {
    $releaseAtSql = $_POST['escrow_status'] === 'released' ? ", escrow_release_at=COALESCE(escrow_release_at, NOW())" : "";
    $stmt = $pdo->prepare("UPDATE transactions SET status=?, escrow_status=? $releaseAtSql WHERE trans_id=?");
    $stmt->execute([$_POST['status'], $_POST['escrow_status'], $_POST['trans_id']]);
    $success = "Transaction updated.";
}

if (isset($_POST['approve_product'])) {
    $stmt = $pdo->prepare("UPDATE products SET status='active', rejection_reason=NULL WHERE prod_id=? AND quantity > 0");
    $stmt->execute([$_POST['prod_id']]);
    $success = "Product approved and is now visible in the marketplace.";
}

if (isset($_POST['reject_product'])) {
    $reason = trim($_POST['rejection_reason'] ?? '');
    if ($reason === '') {
        $success = "Please provide a reason before rejecting a product.";
    } else {
        $stmt = $pdo->prepare("UPDATE products SET status='rejected', rejection_reason=? WHERE prod_id=?");
        $stmt->execute([$reason, $_POST['prod_id']]);
        $success = "Product rejected.";
    }
}

if (isset($_POST['update_role'])) {
    $stmt = $pdo->prepare("UPDATE users SET role=? WHERE user_id=?");
    $stmt->execute([$_POST['role'], $_POST['user_id']]);
    $success = "User role updated.";
}

if (isset($_POST['delete_user'])) {
    $stmt = $pdo->prepare("DELETE FROM users WHERE user_id=?");
    $stmt->execute([$_POST['user_id']]);
    $success = "User deleted.";
}

if (isset($_POST['create_user'])) {
    $stmt = $pdo->prepare("INSERT INTO users (username, email, role, is_active, created_at) VALUES (?, ?, ?, 1, NOW())");
    $stmt->execute([$_POST['username'], $_POST['email'], $_POST['role']]);
    $success = "New user created.";
}

$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalProducts = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$totalTransactions = $pdo->query("SELECT COUNT(*) FROM transactions")->fetchColumn();
$pendingProductsCount = $pdo->query("SELECT COUNT(*) FROM products WHERE status='pending'")->fetchColumn();

$transactions = $pdo->query("SELECT t.*, p.title, u.username AS buyer, s.username AS seller
    FROM transactions t
    JOIN products p ON t.prod_id=p.prod_id
    JOIN users u ON t.buyer_id=u.user_id
    JOIN users s ON t.seller_id=s.user_id
    ORDER BY t.created_at DESC LIMIT 20")->fetchAll();

$users = $pdo->query("SELECT user_id, username, email, role, is_active, created_at
    FROM users ORDER BY created_at DESC")->fetchAll();

$allProducts = $pdo->query("SELECT p.*, u.username, c.name AS category_name
    FROM products p
    JOIN users u ON p.user_id=u.user_id
    LEFT JOIN categories c ON p.cat_id=c.cat_id
    WHERE p.status <> 'removed'
    ORDER BY FIELD(p.status, 'pending', 'active', 'sold_out', 'rejected'), p.created_at DESC")->fetchAll();

$previewProduct = null;
if (isset($_GET['view_product'])) {
    $stmt = $pdo->prepare("SELECT p.*, u.username, c.name AS category_name
        FROM products p
        JOIN users u ON p.user_id=u.user_id
        LEFT JOIN categories c ON p.cat_id=c.cat_id
        WHERE p.prod_id=?");
    $stmt->execute([$_GET['view_product']]);
    $previewProduct = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard - InformalTrade Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar bg-danger">
        <div class="container">
            <a class="navbar-brand text-white" href="index.php"><i class="bi bi-shop me-1"></i>InformalTrade Hub</a>
            <a href="logout.php" class="btn btn-outline-light ms-auto">Logout</a>
        </div>
    </nav>
    <div class="container mt-4">
        <h2><i class="bi bi-shield-lock me-2"></i>Admin Dashboard</h2>
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

        <div class="row mt-4 g-3">
            <div class="col-md-3"><div class="card bg-primary text-white text-center"><div class="card-body"><h3><?= $totalUsers ?></h3><p>Total Users</p></div></div></div>
            <div class="col-md-3"><div class="card bg-success text-white text-center"><div class="card-body"><h3><?= $totalProducts ?></h3><p>Total Products</p></div></div></div>
            <div class="col-md-3"><div class="card bg-warning text-dark text-center"><div class="card-body"><h3><?= $totalTransactions ?></h3><p>Total Transactions</p></div></div></div>
            <div class="col-md-3"><div class="card bg-secondary text-white text-center"><div class="card-body"><h3><?= $pendingProductsCount ?></h3><p>Pending Approval</p></div></div></div>
        </div>

        <h4 class="mt-4"><i class="bi bi-people me-2"></i>Manage Users & Roles</h4>
        <form method="POST" class="row g-2 mb-3">
            <input type="hidden" name="create_user" value="1">
            <div class="col-md-3"><input type="text" name="username" class="form-control" placeholder="Username" required></div>
            <div class="col-md-3"><input type="email" name="email" class="form-control" placeholder="Email" required></div>
            <div class="col-md-3">
                <select name="role" class="form-select" required>
                    <option value="buyer">Buyer</option>
                    <option value="seller">Seller</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div class="col-md-3"><button type="submit" class="btn btn-success"><i class="bi bi-person-plus me-1"></i>Create User</button></div>
        </form>

        <table class="table table-bordered">
            <thead><tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= $u['user_id'] ?></td>
                        <td><?= htmlspecialchars($u['username']) ?></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td>
                            <form method="POST" class="d-flex">
                                <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                <select name="role" class="form-select form-select-sm me-2">
                                    <option value="buyer" <?= $u['role']=='buyer'?'selected':'' ?>>Buyer</option>
                                    <option value="seller" <?= $u['role']=='seller'?'selected':'' ?>>Seller</option>
                                    <option value="admin" <?= $u['role']=='admin'?'selected':'' ?>>Admin</option>
                                </select>
                                <button type="submit" name="update_role" class="btn btn-primary btn-sm">Update</button>
                            </form>
                        </td>
                        <td><?= $u['is_active'] ? 'Active' : 'Inactive' ?></td>
                        <td>
                            <a href="admin-dashboard.php?toggle_user=<?= $u['user_id'] ?>" class="btn btn-warning btn-sm">Toggle</a>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                <button type="submit" name="delete_user" class="btn btn-danger btn-sm">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h4 id="all-products" class="mt-5"><i class="bi bi-box-seam me-2"></i>Product Approval</h4>
        <?php if ($previewProduct): ?>
            <div id="product-preview" class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Product Preview</strong>
                    <a href="admin-dashboard.php#all-products" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left me-1"></i>Back to approval page
                    </a>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <?php if (!empty($previewProduct['image_url'])): ?>
                                <img src="uploads/<?= htmlspecialchars($previewProduct['image_url']) ?>" alt="<?= htmlspecialchars($previewProduct['title']) ?>" class="img-fluid rounded border">
                            <?php else: ?>
                                <div class="border rounded p-5 text-center text-muted">No image uploaded</div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-8">
                            <h5><?= htmlspecialchars($previewProduct['title']) ?></h5>
                            <p><?= nl2br(htmlspecialchars($previewProduct['description'])) ?></p>
                            <p><strong>Price:</strong> R<?= htmlspecialchars($previewProduct['price']) ?></p>
                            <p><strong>Quantity:</strong> <?= (int)$previewProduct['quantity'] ?></p>
                            <p><strong>Category:</strong> <?= htmlspecialchars($previewProduct['category_name'] ?? 'Uncategorized') ?></p>
                            <p><strong>Seller:</strong> <?= htmlspecialchars($previewProduct['username']) ?></p>
                            <p><strong>Location:</strong> <?= htmlspecialchars($previewProduct['location']) ?></p>
                            <p><strong>Status:</strong> <?= ucfirst(str_replace('_', ' ', $previewProduct['status'])) ?></p>
                            <?php if (($previewProduct['status'] ?? '') === 'rejected' && !empty($previewProduct['rejection_reason'])): ?>
                                <p class="text-danger"><strong>Reason:</strong> <?= htmlspecialchars($previewProduct['rejection_reason']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <table class="table table-bordered">
            <thead><tr><th>ID</th><th>Image</th><th>Title</th><th>Price</th><th>Qty</th><th>Status</th><th>Seller</th><th>Date</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($allProducts as $p): ?>
                    <tr>
                        <td><?= $p['prod_id'] ?></td>
                        <td>
                            <?php if (!empty($p['image_url'])): ?>
                                <a href="admin-dashboard.php?view_product=<?= $p['prod_id'] ?>#product-preview">
                                    <img src="uploads/<?= htmlspecialchars($p['image_url']) ?>" alt="<?= htmlspecialchars($p['title']) ?>" style="width:70px;height:70px;object-fit:cover;" class="rounded border">
                                </a>
                                <div><a href="admin-dashboard.php?view_product=<?= $p['prod_id'] ?>#product-preview" class="small">View full</a></div>
                            <?php else: ?>
                                <span class="text-muted small">No image</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($p['title']) ?></td>
                        <td>R<?= $p['price'] ?></td>
                        <td><?= (int)$p['quantity'] ?></td>
                        <td>
                            <?= ucfirst(str_replace('_', ' ', $p['status'])) ?>
                            <?php if (($p['status'] ?? '') === 'rejected' && !empty($p['rejection_reason'])): ?>
                                <div class="small text-danger mt-1"><strong>Reason:</strong> <?= htmlspecialchars($p['rejection_reason']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($p['username']) ?></td>
                        <td><?= $p['created_at'] ?></td>
                        <td>
                            <?php if ($p['status'] === 'pending'): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="prod_id" value="<?= $p['prod_id'] ?>">
                                    <button type="submit" name="approve_product" class="btn btn-success btn-sm">Approve</button>
                                </form>
                                <form method="POST" class="mt-2">
                                    <input type="hidden" name="prod_id" value="<?= $p['prod_id'] ?>">
                                    <textarea name="rejection_reason" class="form-control form-control-sm mb-1" rows="2" placeholder="Reason for rejection" required></textarea>
                                    <button type="submit" name="reject_product" class="btn btn-danger btn-sm">Reject</button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted">No action</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h4 class="mt-5"><i class="bi bi-credit-card me-2"></i>Recent Transactions</h4>
        <table class="table table-bordered">
            <thead><tr><th>ID</th><th>Product</th><th>Buyer</th><th>Seller</th><th>Amount</th><th>Status</th><th>Escrow</th><th>Update</th></tr></thead>
            <tbody>
                <?php foreach ($transactions as $t): ?>
                    <tr>
                        <td><?= $t['trans_id'] ?></td>
                        <td><?= htmlspecialchars($t['title']) ?></td>
                        <td><?= htmlspecialchars($t['buyer']) ?></td>
                        <td><?= htmlspecialchars($t['seller']) ?></td>
                        <td>R<?= htmlspecialchars($t['amount']) ?></td>
                        <td><?= htmlspecialchars($t['status']) ?></td>
                        <td><?= htmlspecialchars($t['escrow_status'] ?? 'pending') ?></td>
                        <td>
                            <form method="POST" class="d-flex gap-1">
                                <input type="hidden" name="trans_id" value="<?= $t['trans_id'] ?>">
                                <select name="status" class="form-select form-select-sm">
                                    <?php foreach (['pending','paid','shipped','completed','cancelled'] as $status): ?>
                                        <option value="<?= $status ?>" <?= $t['status']===$status?'selected':'' ?>><?= ucfirst($status) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <select name="escrow_status" class="form-select form-select-sm">
                                    <?php foreach (['pending','held','released','refunded'] as $escrow): ?>
                                        <option value="<?= $escrow ?>" <?= ($t['escrow_status'] ?? 'pending')===$escrow?'selected':'' ?>><?= ucfirst($escrow) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" name="update_transaction" class="btn btn-primary btn-sm">Save</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
