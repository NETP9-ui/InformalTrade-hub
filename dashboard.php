<?php 
require_once 'config.php'; 
session_start();

if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit; 
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id=?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$role = $user['role'];

$stmtMessages = $pdo->prepare("SELECT COUNT(*) AS total_messages FROM messages WHERE receiver_id = ? AND is_read = 0");
$stmtMessages->execute([$_SESSION['user_id']]);
$messageCount = (int) $stmtMessages->fetchColumn();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard - InformalTrade Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar bg-success">
        <a class="navbar-brand text-white" href="index.php"><i class="bi bi-house me-1"></i>Home</a>
        <a href="logout.php" class="btn btn-outline-light ms-auto"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
    </nav>
    <div class="container mt-4">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3">
                <div class="list-group">
                    <a href="dashboard.php" class="list-group-item list-group-item-action active"><i class="bi bi-speedometer2 me-2"></i>Overview</a>
                    <?php if ($role === 'seller'): ?>
                        <a href="list-product.php" class="list-group-item"><i class="bi bi-box-seam me-2"></i>My Listings</a>
                        <a href="my-sales.php" class="list-group-item"><i class="bi bi-cash-coin me-2"></i>My Sales</a>
                    <?php endif; ?>
                    <?php if ($role === 'buyer'): ?>
                        <a href="my-orders.php" class="list-group-item"><i class="bi bi-bag me-2"></i>My Orders</a>
                    <?php endif; ?>
                    <a href="messages.php" class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-chat-dots me-2"></i>Messages</span>
                        <?php if ($messageCount > 0): ?>
                            <span class="badge bg-primary rounded-pill"><?= $messageCount ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="profile.php" class="list-group-item"><i class="bi bi-gear me-2"></i>Profile</a>
                    <?php if ($role === 'admin'): ?>
                        <a href="admin-dashboard.php" class="list-group-item bg-danger text-white"><i class="bi bi-shield-lock me-2"></i>Admin Panel</a>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Main Content -->
            <div class="col-md-9">
                <div class="card">
                    <div class="card-header">
                        <h4>Welcome, <?= htmlspecialchars($user['username']) ?> (<?= ucfirst($role) ?>)</h4>
                    </div>
                    <div class="card-body">
                        <h5>Quick Actions</h5>
                        <?php if ($role === 'seller'): ?>
                            <a href="list-product.php" class="btn btn-success me-2 mb-2"><i class="bi bi-plus-circle me-1"></i>List New Item</a>
                        <?php endif; ?>
                        <a href="product.php" class="btn btn-outline-success mb-2"><i class="bi bi-grid me-1"></i>Browse Marketplace</a>
                        <a href="messages.php" class="btn btn-outline-primary mb-2">
                            <i class="bi bi-chat-dots me-1"></i>Messages
                            <?php if ($messageCount > 0): ?>
                                <span class="badge bg-primary ms-1"><?= $messageCount ?></span>
                            <?php endif; ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
	
	 <!-- Footer -->
<footer class="bg-light text-center text-muted py-3 border-top fixed-bottom">
    Need help? 
    <a href="mailto:admin@hub.com" class="text-decoration-none">admin@hub.com</a>
</footer>
              
</body>
</html>
