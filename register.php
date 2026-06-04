<?php 
require_once 'config.php'; 
session_start();
$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email    = trim($_POST['email']);
    $password = $_POST['password']; // get password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $role     = $_POST['role']; // buyer or seller

    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, created_at, is_active) 
                               VALUES (?, ?, ?, ?, NOW(), 1)");
        $stmt->execute([$username, $email, $hashedPassword, $role]);
        $success = "Account created! Please login.";
    } catch (PDOException $e) {
        // Show actual error for debugging (optional)
        // $error = $e->getMessage();
        $error = "Username or Email already exists!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register - InformalTrade Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="responsive.css"> 
</head>
<body class="bg-light">
<div class="container auth-container"> 
    <div class="row justify-content-center mt-5">
        <div class="col-md-8 col-lg-6 auth-card"> 
                <div class="card-body p-5">
                    <h3 class="text-center mb-4"><i class="bi bi-person-plus me-2"></i>Create Account</h3>
                    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" name="username" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select" required>
                                <option value="buyer">Buyer</option>
                                <option value="seller">Seller</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-success w-100"><i class="bi bi-person-plus me-1"></i>Register</button>
                    </form>
                    <div class="text-center mt-3">
                        <a href="login.php">Already have an account? Login</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
