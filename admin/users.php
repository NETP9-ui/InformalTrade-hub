<?php 
require_once '../config.php'; 
checkRole($pdo, 'admin');

// Handle actions
if ($_POST) {
    if (isset($_POST['action'])) {
        switch($_POST['action']) {
            case 'toggle_active':
                $stmt = $pdo->prepare("UPDATE users SET is_active = NOT(is_active) WHERE user_id = ? AND user_id != ?");
                $stmt->execute([$_POST['user_id'], $_SESSION['user_id']]);
                break;
                
            case 'change_role':
                $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE user_id = ? AND user_id != ?");
                $stmt->execute([$_POST['role'], $_POST['user_id'], $_SESSION['user_id']]);
                break;
        }
    }
}

// Get all users
$users = $pdo->query("
    SELECT u.*, 
           COUNT(p.prod_id) as product_count,
           COUNT(t.trans_id) as transaction_count
    FROM users u 
    LEFT JOIN products p ON u.user_id = p.user_id AND p.status != 'sold'
    LEFT JOIN transactions t ON u.user_id = t.buyer_id OR u.user_id = t.seller_id
    WHERE 1=1
    GROUP BY u.user_id
    ORDER BY u.created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <a href="index.php" class="btn btn-outline-light">
                <i class="bi bi-arrow-left me-2"></i>Dashboard
            </a>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-people me-2 text-primary"></i>User Management</h2>
            <span class="badge bg-primary fs-6"><?= count($users) ?> Total Users</span>
        </div>

        <div class="card shadow">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Avatar</th>
                            <th>User Info</th>
                            <th>Role</th>
                            <th>Stats</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($users as $user): ?>
                        <tr>
                            <td><strong>#<?= $user['user_id'] ?></strong></td>
                            <td>
                                <div class="avatar-sm">
                                    <div class="avatar-title bg-<?= $user['role']=='admin' ? 'danger' : ($user['role']=='seller' ? 'success' : 'info') ?> rounded-circle text-white fs-5">
                                        <?= strtoupper(substr($user['username'], 0, 1)) ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($user['username']) ?></strong><br>
                                <small class="text-muted"><?= htmlspecialchars($user['email']) ?></small><br>
                                <small>📍 <?= htmlspecialchars($user['location'] ?? 'Not set') ?></small>
                            </td>
                            <td>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                    <input type="hidden" name="action" value="change_role">
                                    <select name="role" class="form-select form-select-sm" 
                                            onchange="this.form.submit()" 
                                            <?= $user['user_id'] == $_SESSION['user_id'] ? 'disabled' : '' ?>>
                                        <option value="buyer" <?= $user['role']=='buyer' ? 'selected' : '' ?>>Buyer</option>
                                        <option value="seller" <?= $user['role']=='seller' ? 'selected' : '' ?>>Seller</option>
                                        <option value="moderator" <?= $
