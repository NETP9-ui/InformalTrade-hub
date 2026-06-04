<?php 
require_once '../config.php'; 
$role = checkRole($pdo, 'admin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - InformalTrade Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-dark bg-dark shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand fs-3 fw-bold" href="index.php">
                <i class="bi bi-shield-lock text-success me-2"></i>Admin Panel
            </a>
            <div>
                <span class="navbar-text me-3 text-white-50">
                    👤 <?= htmlspecialchars($_SESSION['username']) ?>
                </span>
                <a href="../logout.php" class="btn btn-outline-light btn-sm">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 bg-light border-end vh-100 p-3">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">
                            <i class="bi bi-house-door me-2"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">
                            <i class="bi bi-people me-2"></i>Users (<?= $pdo->query("SELECT COUNT(*) FROM users WHERE is_active=1")->fetchColumn() ?>)
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="products.php">
                            <i class="bi bi-box-seam me-2"></i>Products
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="analytics.php">
                            <i class="bi bi-graph-up me-2"></i>Analytics
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 p-4">
                <h1 class="mb-4">📊 Admin Dashboard</h1>
                
                <!-- Stats Cards -->
                <div class="row g-4 mb-5">
                    <div class="col-md-3">
                        <div class="card shadow h-100 text-white bg-success">
                            <div class="card-body">
                                <h5><i class="bi bi-people"></i> Total Users</h5>
                                <h2 id="totalUsers">
                                    <?php echo $pdo->query("SELECT COUNT(*) FROM users WHERE is_active=1")->fetchColumn(); ?>
                                </h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card shadow h-100 text-white bg-primary">
                            <div class="card-body">
                                <h5><i class="bi bi-box-seam"></i> Active Products</h5>
                                <h2 id="activeProducts">
                                    <?php echo $pdo->query("SELECT COUNT(*) FROM products WHERE status='active'")->fetchColumn(); ?>
                                </h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card shadow h-100 text-white bg-warning">
                            <div class="card-body">
                                <h5><i class="bi bi-cart"></i> Escrow Transactions</h5>
                                <h2 id="escrowCount">
                                    <?php echo $pdo->query("SELECT COUNT(*) FROM transactions WHERE status='escrow'")->fetchColumn(); ?>
                                </h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card shadow h-100 text-white bg-info">
                            <div class="card-body">
                                <h5><i class="bi bi-clock-history"></i> Revenue Today</h5>
                                <h2>R <?php 
                                    $today = $pdo->query("SELECT SUM(amount) FROM transactions WHERE status='completed' AND DATE(created_at)=CURDATE()")->fetchColumn() ?: 0;
                                    echo number_format($today, 2);
                                ?></h2>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="row">
                    <div class="col-12">
                        <div class="card shadow">
                            <div class="card-header">
                                <h5>📋 Recent Activity</h5>
                            </div>
                            <div class="card-body">
                                <?php
                                $recent = $pdo->query("
                                    SELECT 'product' as type, title, created_at, user_id, status 
                                    FROM products 
                                    WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
                                    UNION ALL
                                    SELECT 'transaction' as type, CONCAT('R', amount) as title, created_at, buyer_id as user_id, status
                                    FROM transactions 
                                    WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
                                    ORDER BY created_at DESC LIMIT 10
                                ")->fetchAll();
                                ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Activity</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($recent as $item): ?>
                                            <tr>
                                                <td><?= date('M j, H:i', strtotime($item['created_at'])) ?></td>
                                                <td>
                                                    <strong><?= htmlspecialchars($item['title']) ?></strong>
                                                    <?php if($item['type'] == 'product'): ?>
                                                        <br><small class="text-muted">New listing</small>
                                                    <?php else: ?>
                                                        <br><small class="text-muted">New transaction</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge <?= $item['status']=='active' || $item['status']=='escrow' ? 'bg-success' : 'bg-secondary' ?>">
                                                        <?= ucfirst($item['status']) ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
