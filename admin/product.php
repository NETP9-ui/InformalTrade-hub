<?php 
require_once '../config.php'; 
checkRole($pdo, 'admin');

// Handle product actions
$message = '';
if ($_POST) {
    if (isset($_POST['action'])) {
        switch($_POST['action']) {
            case 'approve':
                $stmt = $pdo->prepare("UPDATE products SET status = 'active', rejection_reason = NULL WHERE prod_id = ?");
                if ($stmt->execute([$_POST['prod_id']])) {
                    $message = '<div class="alert alert-success">✅ Product approved!</div>';
                }
                break;
                
            case 'reject':
                $reason = trim($_POST['rejection_reason'] ?? '');
                if ($reason === '') {
                    $message = '<div class="alert alert-danger">Please provide a reason before rejecting a product.</div>';
                } else {
                    $stmt = $pdo->prepare("UPDATE products SET status = 'rejected', rejection_reason = ? WHERE prod_id = ?");
                    if ($stmt->execute([$reason, $_POST['prod_id']])) {
                        $message = '<div class="alert alert-danger">❌ Product rejected!</div>';
                    }
                }
                break;
                
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM products WHERE prod_id = ?");
                if ($stmt->execute([$_POST['prod_id']])) {
                    $message = '<div class="alert alert-warning">🗑️ Product deleted!</div>';
                }
                break;
        }
    }
}

// Get all products with user and category info
$products = $pdo->query("
    SELECT p.*, 
           u.username, 
           u.location as user_location,
           c.name as category_name
    FROM products p 
    LEFT JOIN users u ON p.user_id = u.user_id
    LEFT JOIN categories c ON p.cat_id = c.cat_id
    WHERE p.status <> 'removed'
    ORDER BY p.created_at DESC
")->fetchAll();

// Filter counts
$total = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$pending = $pdo->query("SELECT COUNT(*) FROM products WHERE status='pending'")->fetchColumn();
$active = $pdo->query("SELECT COUNT(*) FROM products WHERE status='active'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <!-- Top Navigation -->
    <nav class="navbar navbar-dark bg-dark shadow-sm">
        <div class="container-fluid">
            <a href="index.php" class="btn btn-outline-light">
                <i class="bi bi-arrow-left me-2"></i>Dashboard
            </a>
            <span class="navbar-text text-white-50">
                📦 <?= $total ?> Total Products
            </span>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <!-- Stats Header -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-light">
                    <div class="card-body text-center">
                        <h3><?= $total ?></h3>
                        <p class="mb-0">Total Listings</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-dark">
                    <div class="card-body text-center">
                        <h3><?= $pending ?></h3>
                        <p class="mb-0">Pending Review</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h3><?= $active ?></h3>
                        <p class="mb-0">Active Products</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-secondary text-white">
                    <div class="card-body text-center">
                        <h3><?= $total - $pending - $active ?></h3>
                        <p class="mb-0">Sold/Rejected</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Message -->
        <?= $message ?? '' ?>

        <!-- Products Table -->
        <div class="card shadow">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-box-seam me-2"></i>Product Management</h5>
                <div class="input-group" style="width: 300px;">
                    <input type="text" id="searchInput" class="form-control" placeholder="🔍 Search products...">
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="productsTable">
                    <thead class="table-dark">
                        <tr>
                            <th>Image</th>
                            <th>Product Details</th>
                            <th>Seller</th>
                            <th>Price</th>
                            <th>Category</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($products as $product): ?>
                        <tr data-product="<?= htmlspecialchars($product['title']) ?>">
                            <td>
                                <?php if($product['image_url']): ?>
                                    <img src="../uploads/<?= htmlspecialchars($product['image_url']) ?>" 
                                         class="rounded" style="width:60px;height:60px;object-fit:cover;"
                                         onerror="this.src='../uploads/no-image.png'">
                                <?php else: ?>
                                    <div class="bg-light rounded d-flex align-items-center justify-content-center" 
                                         style="width:60px;height:60px;">
                                        <i class="bi bi-image text-muted"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($product['title']) ?></strong><br>
                                <small class="text-muted"><?= substr($product['description'], 0, 50) ?>...</small>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($product['username'] ?? 'Unknown') ?></strong>
                            </td>
                            <td>
                                <span class="fw-bold text-success">R <?= number_format($product['price'], 2) ?></span>
                            </td>
                            <td>
                                <span class="badge bg-info"><?= htmlspecialchars($product['category_name'] ?? 'Uncategorized') ?></span>
                            </td>
                            <td>
                                <small class="text-muted"><?= htmlspecialchars($product['location'] ?? 'N/A') ?></small>
                            </td>
                            <td>
                                <?php 
                                $statusClass = match($product['status']) {
                                    'pending' => 'bg-warning',
                                    'active' => 'bg-success',
                                    'sold' => 'bg-secondary',
                                    'rejected' => 'bg-danger',
                                    default => 'bg-light'
                                };
                                ?>
                                <span class="badge <?= $statusClass ?>"><?= ucfirst($product['status']) ?></span>
                                <?php if (($product['status'] ?? '') === 'rejected' && !empty($product['rejection_reason'])): ?>
                                    <div class="small text-danger mt-1"><strong>Reason:</strong> <?= htmlspecialchars($product['rejection_reason']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= date('M j', strtotime($product['created_at'])) ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <?php if($product['status'] == 'pending'): ?>
                                        <form method="POST" class="d-inline me-1">
                                            <input type="hidden" name="prod_id" value="<?= $product['prod_id'] ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="btn btn-success" title="Approve">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                        </form>
                                        <form method="POST" class="d-inline me-1">
                                            <input type="hidden" name="prod_id" value="<?= $product['prod_id'] ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <input type="text" name="rejection_reason" class="form-control form-control-sm mb-1" placeholder="Reason" required>
                                            <button type="submit" class="btn btn-danger" title="Reject" 
                                                    onclick="return confirm('Reject this product?')">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <?php if($product['status'] != 'pending'): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="prod_id" value="<?= $product['prod_id'] ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <button type="submit" class="btn btn-outline-danger" title="Delete"
                                                    onclick="return confirm('Delete permanently?')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Live search
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#productsTable tbody tr');
            
            rows.forEach(row => {
                const productName = row.getAttribute('data-product').toLowerCase();
                row.style.display = productName.includes(searchTerm) ? '' : 'none';
            });
        });
    </script>
</body>
</html>
