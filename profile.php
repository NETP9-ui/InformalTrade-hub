<?php
require_once "config.php";
session_start();

if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit; 
}

$user_id = $_SESSION['user_id'];

// Fetch current user details including role
$stmt = $pdo->prepare("SELECT username, email, phone, address, password, role FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$error = $success = '';

// Update profile
if (isset($_POST['update_profile'])) {
    try {
        $stmt = $pdo->prepare("UPDATE users SET username=?, email=?, phone=?, address=? WHERE user_id=?");
        $stmt->execute([$_POST['username'], $_POST['email'], $_POST['phone'], $_POST['address'], $user_id]);
        $success = "Profile updated successfully!";
    } catch (PDOException $e) {
        $error = "Failed to update profile: " . $e->getMessage();
    }
}

// Change password
if (isset($_POST['change_password'])) {
    if (!password_verify($_POST['current_password'], $user['password'])) {
        $error = "Current password incorrect.";
    } elseif ($_POST['new_password'] !== $_POST['confirm_password']) {
        $error = "New passwords do not match.";
    } else {
        $newHash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password=? WHERE user_id=?");
        $stmt->execute([$newHash, $user_id]);
        $success = "Password updated successfully!";
    }
}

// Save bank details (seller only)
if (isset($_POST['save_bank']) && $user['role'] === 'seller') {
    try {
        $stmt = $pdo->prepare("REPLACE INTO seller_bank (user_id, bank_name, account_holder, account_number) VALUES (?,?,?,?)");
        $stmt->execute([$user_id, $_POST['bank_name'], $_POST['account_holder'], $_POST['account_number']]);
        $success = "Bank details saved successfully!";
    } catch (PDOException $e) {
        $error = "Failed to save bank details: " . $e->getMessage();
    }
}

// Fetch existing bank details if seller
$bank = null;
if ($user['role'] === 'seller') {
    $stmt = $pdo->prepare("SELECT bank_name, account_holder, account_number FROM seller_bank WHERE user_id=?");
    $stmt->execute([$user_id]);
    $bank = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Profile</title>
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
        <h2><i class="bi bi-gear me-2"></i>My Profile</h2>
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <!-- Profile Update -->
        <form method="POST" class="mb-4">
            <input type="hidden" name="update_profile" value="1">
            <div class="mb-3"><label>Username</label><input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user['username']) ?>"></div>
            <div class="mb-3"><label>Email</label><input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>"></div>
            <div class="mb-3"><label>Phone</label><input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone']) ?>"></div>
            <div class="mb-3"><label>Address</label><input type="text" name="address" class="form-control" value="<?= htmlspecialchars($user['address']) ?>"></div>
            <button type="submit" class="btn btn-success"><i class="bi bi-check-circle me-1"></i>Save Changes</button>
        </form>

        <!-- Password Change -->
        <h4><i class="bi bi-lock me-2"></i>Change Password</h4>
        <form method="POST" class="mb-4">
            <input type="hidden" name="change_password" value="1">
            <div class="mb-3"><label>Current Password</label><input type="password" name="current_password" class="form-control" required></div>
            <div class="mb-3"><label>New Password</label><input type="password" name="new_password" class="form-control" required></div>
            <div class="mb-3"><label>Confirm New Password</label><input type="password" name="confirm_password" class="form-control" required></div>
            <button type="submit" class="btn btn-warning"><i class="bi bi-key me-1"></i>Update Password</button>
        </form>

        <!-- Seller Bank Details -->
        <?php if ($user['role'] === 'seller'): ?>
            <h4><i class="bi bi-bank me-2"></i>Bank Account Details</h4>
            <form method="POST" class="mb-4">
                <input type="hidden" name="save_bank" value="1">
                <div class="mb-3"><label>Bank Name</label><input type="text" name="bank_name" class="form-control" value="<?= htmlspecialchars($bank['bank_name'] ?? '') ?>" required></div>
                <div class="mb-3"><label>Account Holder</label><input type="text" name="account_holder" class="form-control" value="<?= htmlspecialchars($bank['account_holder'] ?? '') ?>" required></div>
                <div class="mb-3"><label>Account Number</label><input type="text" name="account_number" class="form-control" value="<?= htmlspecialchars($bank['account_number'] ?? '') ?>" required></div>
                <button type="submit" class="btn btn-info"><i class="bi bi-save me-1"></i>Save Bank Details</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
