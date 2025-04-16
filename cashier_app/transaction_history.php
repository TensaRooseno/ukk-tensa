<?php
// Start session and include database connection
session_start();
require_once 'includes/db.php';

// Authentication check - Both admin and cashier can access this page
if (!isset($_SESSION['user_id']) || ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 2)) {
    header("Location: index.php");
    exit;
}

// Define sidebar menu items based on user role
$role_id = $_SESSION['role_id'];
if ($role_id == 2) {
    // Cashier menu items
    $menu_items = [
        ['link' => 'cashier_dashboard.php', 'label' => 'Transactions'],
        ['link' => 'view_products.php', 'label' => 'View Products'],
        ['link' => 'transaction_history.php', 'label' => 'Transaction History'],
        ['link' => 'logout.php', 'label' => 'Logout']
    ];
} else {
    // Admin menu items
    $menu_items = [
        ['link' => 'admin_dashboard.php', 'label' => 'Overview'],
        ['link' => 'user_management.php', 'label' => 'User Management'],
        ['link' => 'product_management.php', 'label' => 'Product Management'],
        ['link' => 'transaction_history.php', 'label' => 'Transaction History'],
        ['link' => 'reports.php', 'label' => 'Reports'],
        ['link' => 'logout.php', 'label' => 'Logout']
    ];
}

// Set interface properties based on user role
$sidebar_title = $role_id == 1 ? 'Admin Dashboard' : 'Cashier Dashboard';
$sidebar_bg_color = $role_id == 1 ? '#343a40' : '#28a745';
$sidebar_hover_color = $role_id == 1 ? '#495057' : '#218838';
$sidebar_width = $role_id == 1 ? '250px' : '200px';

// Pagination setup
$limit = 10; // Transactions per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Get total number of transactions for pagination
$totalStmt = $conn->query("SELECT COUNT(*) as total FROM transactions");
$total = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($total / $limit);

// Fetch transactions with cashier details for current page
$stmt = $conn->prepare("SELECT t.*, u.username 
                       FROM transactions t 
                       JOIN users u ON t.cashier_id = u.user_id 
                       ORDER BY t.date_time DESC 
                       LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Transaction History - Cashier App</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Include Bootstrap CSS and JS -->
    <link href="/ukk/cashier_app/assets/bootstrap.min.css" rel="stylesheet">
    <script src="/ukk/cashier_app/assets/bootstrap.min.js"></script>
    <!-- Sidebar styling based on user role -->
    <style>
        .sidebar {
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            width: <?php echo $sidebar_width; ?>;
            padding-top: 20px;
            background-color: <?php echo $sidebar_bg_color; ?>;
        }
        .sidebar .nav-link {
            color: #fff;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background-color: <?php echo $sidebar_hover_color; ?>;
        }
        .main-content {
            margin-left: <?php echo $sidebar_width; ?>;
            padding: 20px;
        }
    </style>
</head>
<body>
    <!-- Role-based Sidebar Navigation -->
    <div class="sidebar">
        <h4 class="text-white text-center"><?php echo $sidebar_title; ?></h4>
        <ul class="nav flex-column">
            <?php foreach ($menu_items as $item): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == $item['link'] ? 'active' : ''; ?>" 
                   href="<?php echo $item['link']; ?>"><?php echo $item['label']; ?></a>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
        <h2>Transaction History</h2>
        <p>View all transactions processed by cashiers.</p>

        <!-- Transactions Table -->
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Date</th>
                    <th>Total Amount</th>
                    <th>Discount</th>
                    <th>Cashier</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $trans): ?>
                <tr>
                    <td><?php echo $trans['transaction_id']; ?></td>
                    <td><?php echo $trans['date_time']; ?></td>
                    <td>$<?php echo number_format($trans['total_amount'], 2); ?></td>
                    <td>$<?php echo number_format($trans['discount_amount'], 2); ?></td>
                    <td><?php echo $trans['username']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination Navigation -->
        <nav>
            <ul class="pagination">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>
</body>
</html>