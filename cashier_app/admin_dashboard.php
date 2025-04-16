<?php
// Start session and include database connection
session_start();
require_once 'includes/db.php';

// Authentication check - Only admin can access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: index.php");
    exit;
}

// Fetch key statistics for dashboard overview
// Calculate total sales across all transactions
$totalSalesStmt = $conn->query("SELECT SUM(total_amount) as total_sales FROM transactions");
$totalSales = $totalSalesStmt->fetch(PDO::FETCH_ASSOC)['total_sales'] ?? 0;

// Count transactions made today
$todayTransactionsStmt = $conn->query("SELECT COUNT(*) as today_count FROM transactions WHERE DATE(date_time) = CURDATE()");
$todayTransactions = $todayTransactionsStmt->fetch(PDO::FETCH_ASSOC)['today_count'] ?? 0;

// Count total active users in the system
$activeUsersStmt = $conn->query("SELECT COUNT(*) as user_count FROM users");
$activeUsers = $activeUsersStmt->fetch(PDO::FETCH_ASSOC)['user_count'] ?? 0;

// Fetch 5 most recent transactions with cashier details
$recentTransactionsStmt = $conn->query("SELECT t.transaction_id, t.date_time, t.total_amount, u.username 
    FROM transactions t JOIN users u ON t.cashier_id = u.user_id 
    ORDER BY t.date_time DESC LIMIT 5");
$recentTransactions = $recentTransactionsStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin Dashboard - Cashier App</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Include Bootstrap CSS and JS -->
    <link href="/ukk/cashier_app/assets/bootstrap.min.css" rel="stylesheet">
    <script src="/ukk/cashier_app/assets/bootstrap.min.js"></script>
    <!-- Admin dashboard styling -->
    <style>
        /* Admin sidebar styles */
        .sidebar {
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            padding-top: 20px;
            background-color: #343a40;
        }
        .sidebar .nav-link {
            color: #fff;
        }
        .sidebar .nav-link:hover {
            background-color: #495057;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        /* Statistics card styles */
        .card-stat {
            min-height: 120px;
        }
    </style>
</head>
<body>
    <!-- Admin Sidebar Navigation -->
    <div class="sidebar">
        <h4 class="text-white text-center">Admin Dashboard</h4>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link active" href="#overview">Overview</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="user_management.php">User Management</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="product_management.php">Product Management</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="transaction_history.php">Transaction History</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="reports.php">Reports</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="logout.php">Logout</a>
            </li>
        </ul>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
        <h2>Welcome, Admin</h2>
        <p>Manage your cashier application efficiently.</p>

        <!-- Overview Statistics Section -->
        <div id="overview" class="mb-4">
            <h3>Overview</h3>
            <div class="row">
                <!-- Total Sales Card -->
                <div class="col-md-4">
                    <div class="card card-stat text-white bg-primary">
                        <div class="card-body">
                            <h5 class="card-title">Total Sales</h5>
                            <p class="card-text">$<?php echo number_format($totalSales, 2); ?></p>
                        </div>
                    </div>
                </div>
                <!-- Today's Transactions Card -->
                <div class="col-md-4">
                    <div class="card card-stat text-white bg-success">
                        <div class="card-body">
                            <h5 class="card-title">Transactions Today</h5>
                            <p class="card-text"><?php echo $todayTransactions; ?></p>
                        </div>
                    </div>
                </div>
                <!-- Active Users Card -->
                <div class="col-md-4">
                    <div class="card card-stat text-white bg-info">
                        <div class="card-body">
                            <h5 class="card-title">Active Users</h5>
                            <p class="card-text"><?php echo $activeUsers; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Transactions Table -->
        <div class="mb-4">
            <h3>Recent Transactions</h3>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date</th>
                        <th>Total Amount</th>
                        <th>Cashier</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentTransactions as $trans): ?>
                    <tr>
                        <td><?php echo $trans['transaction_id']; ?></td>
                        <td><?php echo $trans['date_time']; ?></td>
                        <td>$<?php echo number_format($trans['total_amount'], 2); ?></td>
                        <td><?php echo $trans['username']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>