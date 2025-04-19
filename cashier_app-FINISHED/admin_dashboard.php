<?php
session_start();
require_once 'includes/db.php';

// Authentication check - Only admin can access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: index.php");
    exit;
}

// Fetch overall statistics
$statsQuery = $conn->prepare("
    SELECT 
        COUNT(DISTINCT t.transaction_id) as total_transactions,
        COUNT(DISTINCT t.member_id) as member_transactions,
        SUM(t.total_amount) as total_revenue
    FROM transactions t
");
$statsQuery->execute();
$stats = $statsQuery->fetch(PDO::FETCH_ASSOC);

// Get today's transaction count instead of sales amount
$todayTransactionsQuery = $conn->prepare("
    SELECT COUNT(DISTINCT transaction_id) as today_transactions
    FROM transactions
    WHERE DATE(date_time) = CURRENT_DATE()
");
$todayTransactionsQuery->execute();
$todayTransactions = $todayTransactionsQuery->fetch(PDO::FETCH_ASSOC);

// Get member statistics
$memberStatsQuery = $conn->query("
    SELECT 
        COUNT(*) as total_members,
        COUNT(CASE WHEN DATE(first_purchase_date) = CURRENT_DATE() THEN 1 END) as new_members
    FROM members
");
$memberStats = $memberStatsQuery->fetch(PDO::FETCH_ASSOC);

// Get daily sales data for the last 30 days
$dailySalesQuery = $conn->query("
    SELECT 
        DATE(date_time) as day,
        SUM(total_amount) as total_sales
    FROM transactions
    WHERE date_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(date_time)
    ORDER BY day ASC
");
$dailySales = $dailySalesQuery->fetchAll(PDO::FETCH_ASSOC);

// Get monthly sales data for the last 12 months
$monthlySalesQuery = $conn->query("
    SELECT 
        DATE_FORMAT(date_time, '%Y-%m') as month,
        SUM(total_amount) as total_sales
    FROM transactions
    WHERE date_time >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(date_time, '%Y-%m')
    ORDER BY month ASC
");
$monthlySales = $monthlySalesQuery->fetchAll(PDO::FETCH_ASSOC);

// Get product sales percentages
$productSalesQuery = $conn->query("
    SELECT 
        p.name as product_name,
        SUM(td.quantity) as total_quantity,
        SUM(td.quantity * td.price_per_unit) as total_sales
    FROM transaction_details td
    JOIN products p ON td.product_id = p.product_id
    JOIN transactions t ON td.transaction_id = t.transaction_id
    WHERE t.date_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY p.product_id, p.name
    ORDER BY total_sales DESC
");
$productSales = $productSalesQuery->fetchAll(PDO::FETCH_ASSOC);

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
    <!-- Include Bootstrap CSS and JS with Popper -->
    <link href="/ukk/cashier_app/assets/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="/ukk/cashier_app/assets/bootstrap.min.js"></script>
    <!-- Include Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Admin dashboard styling -->
    <style>
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
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background-color: #495057;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .stats-card {
            transition: all 0.3s ease;
        }
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
            margin-bottom: 1rem;
        }
        .profile-dropdown {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 1030;
        }
        .profile-button {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #343a40;
            border: none;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.2s;
        }
        .profile-button:hover,
        .profile-button:focus {
            background-color: #495057;
        }
        .profile-icon {
            font-size: 1.2rem;
        }
        .profile-dropdown .dropdown-menu {
            right: 0;
            left: auto;
            margin-top: 0.5rem;
        }
        .user-info {
            padding: 0.5rem 1rem;
            border-bottom: 1px solid #dee2e6;
        }
    </style>
</head>
<body>
    <!-- Profile Dropdown -->
    <div class="profile-dropdown dropdown">
        <button class="profile-button" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fas fa-user profile-icon"></i>
        </button>
        <ul class="dropdown-menu">
            <li class="user-info">
                <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
                <div class="small text-muted">Administrator</div>
            </li>
            <li><a class="dropdown-item text-danger" href="logout.php">Logout</a></li>
        </ul>
    </div>

    <!-- Admin Sidebar Navigation -->
    <div class="sidebar">
        <h4 class="text-white text-center">Admin Dashboard</h4>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link active" href="admin_dashboard.php">Overview</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="user_management.php">User Management</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="product_management.php">Product Management</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="transactions.php">Transactions</a>
            </li>
        </ul>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
        <h2>Dashboard Overview</h2>

        <!-- Overall Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stats-card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Revenue</h5>
                        <p class="card-text display-6">$<?php echo number_format($stats['total_revenue'], 2); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Transactions</h5>
                        <p class="card-text display-6"><?php echo $stats['total_transactions']; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title">Member Transactions</h5>
                        <p class="card-text display-6"><?php echo $stats['member_transactions']; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card bg-warning text-dark">
                    <div class="card-body">
                        <h5 class="card-title">Today's Transactions</h5>
                        <p class="card-text display-6"><?php echo $todayTransactions['today_transactions'] ?? 0; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sales Charts -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Sales Overview</h5>
                        <div class="btn-group" role="group">
                            <input type="radio" class="btn-check" name="timeScale" id="monthlyView" checked>
                            <label class="btn btn-outline-primary btn-sm" for="monthlyView">Monthly</label>
                            <input type="radio" class="btn-check" name="timeScale" id="dailyView">
                            <label class="btn btn-outline-primary btn-sm" for="dailyView">Daily</label>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="salesChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Product Sales Distribution</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="productSalesChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Transactions Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Recent Transactions</h5>
            </div>
            <div class="card-body">
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
    </div>

    <script>
        // Initialize Chart.js Sales Chart
        const salesChartCtx = document.getElementById('salesChart').getContext('2d');
        const monthlyData = {
            labels: <?php echo json_encode(array_column($monthlySales, 'month')); ?>,
            datasets: [{
                label: 'Monthly Sales',
                data: <?php echo json_encode(array_column($monthlySales, 'total_sales')); ?>,
                borderColor: 'rgb(75, 192, 192)',
                tension: 0.1
            }]
        };

        const dailyData = {
            labels: <?php echo json_encode(array_column($dailySales, 'day')); ?>,
            datasets: [{
                label: 'Daily Sales',
                data: <?php echo json_encode(array_column($dailySales, 'total_sales')); ?>,
                borderColor: 'rgb(75, 192, 192)',
                tension: 0.1
            }]
        };

        const salesChart = new Chart(salesChartCtx, {
            type: 'line',
            data: monthlyData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Handle toggle between monthly and daily views
        document.getElementById('monthlyView').addEventListener('change', function() {
            salesChart.data = monthlyData;
            salesChart.update();
        });

        document.getElementById('dailyView').addEventListener('change', function() {
            salesChart.data = dailyData;
            salesChart.update();
        });

        // Product Sales Chart
        const productSalesCtx = document.getElementById('productSalesChart').getContext('2d');
        new Chart(productSalesCtx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode(array_column($productSales, 'product_name')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($productSales, 'total_sales')); ?>,
                    backgroundColor: [
                        'rgb(255, 99, 132)',
                        'rgb(54, 162, 235)',
                        'rgb(255, 205, 86)',
                        'rgb(75, 192, 192)',
                        'rgb(153, 102, 255)',
                        'rgb(255, 159, 64)'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.raw / total) * 100).toFixed(1);
                                return `${context.label}: $${context.raw.toLocaleString()} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>