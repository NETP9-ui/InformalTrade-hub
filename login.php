<?php
require_once 'config.php';
session_start();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        // Store session data
        $_SESSION['user_id']   = $user['user_id'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['role']      = $user['role'];

        // Redirect based on role
        if ($user['role'] === 'admin') {
            header('Location: admin-dashboard.php');
        } else {
            header('Location: dashboard.php');
        }
        exit;
    } else {
        $error = 'Invalid username or password!';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - InformalTrade Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="responsive.css"> <!-- add your responsive stylesheet -->
</head>
<body class="bg-light">
<div class="container auth-container"> <!-- keep Bootstrap + add custom -->
    <div class="row justify-content-center mt-5">
        <div class="col-md-6 col-lg-4 auth-card"> <!-- keep Bootstrap + add custom -->
            <div class="card shadow">
                <div class="card-body p-5">
                    <h3 class="text-center mb-4"><i class="bi bi-box-arrow-in-right me-2"></i>Login</h3>
                    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-success w-100"><i class="bi bi-box-arrow-in-right me-1"></i>Login</button>
                    </form>
                    <div class="text-center mt-3">
                        <a href="register.php">Need an account? Register</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
