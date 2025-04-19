<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Cashier App</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        label { display: block; margin: 10px 0 5px; }
        input, select { margin-bottom: 10px; padding: 5px; }
        table { border-collapse: collapse; margin: 20px 0; }
        table, th, td { border: 1px solid black; padding: 5px; }
        .nav { 
            background: #eee; 
            padding: 10px; 
            margin-bottom: 20px; 
            display: flex; 
            justify-content: space-between;
        }
        .nav-menu { display: flex; }
        .nav-menu a { 
            margin-right: 15px; 
            text-decoration: none;
            color: #333;
            padding: 5px 10px;
        }
        .nav-menu a:hover {
            background-color: #ddd;
        }
        .user-info {
            display: flex;
            align-items: center;
        }
        .user-info span {
            margin-right: 15px;
        }
        .row {
            width: 100%;
            display: block;
            clear: both;
        }
        .col-md-12 {
            width: 100%;
        }
        .col-md-8 {
            width: 66.66%;
            float: left;
        }
        .col-md-4 {
            width: 33.33%;
            float: left;
        }
    </style>
</head>
<body>
    <div class="nav">
        <div class="nav-menu">
            <?php if (isset($_SESSION['role']) && hasRole('admin')): ?>
                <!-- Admin Navigation -->
                <a href="/ukk/pages/admin/products.php">Products</a>
                <a href="/ukk/pages/admin/transactions.php">Transactions</a>
            <?php else: ?>
                <!-- Cashier Navigation -->
                <a href="/ukk/pages/cashier/pos.php">Point of Sale</a>
                <a href="/ukk/pages/cashier/transactions.php">My Transactions</a>
            <?php endif; ?>
        </div>
        
        <div class="user-info">
            <?php if (isset($_SESSION['user_id'])): ?>
                <span>
                    <strong><?php echo ucfirst($_SESSION['role']); ?>:</strong> 
                    <?php echo $_SESSION['username']; ?>
                </span>
                <a href="/ukk/pages/auth/logout.php">Logout</a>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (isset($_SESSION['message'])): ?>
        <div style="background: <?php echo ($_SESSION['message_type'] == 'success') ? '#dff0d8' : '#f2dede'; ?>; 
                    padding: 10px; margin-bottom: 10px; border-radius: 3px;">
            <?php 
            echo $_SESSION['message']; 
            unset($_SESSION['message']);
            unset($_SESSION['message_type']);
            ?>
        </div>
    <?php endif; ?>