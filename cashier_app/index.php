<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: " . ($_SESSION['role_id'] == 1 ? "admin_dashboard.php" : "cashier_dashboard.php"));
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <link href="/ukk/cashier_app/assets/bootstrap.min.css" rel="stylesheet">
    <script src="/ukk/cashier_app/assets/bootstrap.min.js"></script>
</head>
<body>
    <div class="container mt-5">
        <h2>Login</h2>
        <form method="POST" action="authenticate.php">
            <div class="mb-3">
                <label>Username</label>
                <input type="text" name="username" class="form-control" required>
            </div>
            <div class="mb-3">
                <label>Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary">Login</button>
        </form>
    </div>
</body>
</html>