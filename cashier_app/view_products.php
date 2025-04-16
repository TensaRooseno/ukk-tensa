<?php
// Start session and include database connection
session_start();
require_once 'includes/db.php';

// Authentication check - Only cashier can access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    header("Location: index.php");
    exit;
}

// Fetch all products from the database for display
$stmt = $conn->query("SELECT * FROM products");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>View Products - Cashier App</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Include Bootstrap CSS and JS -->
    <link href="/ukk/cashier_app/assets/bootstrap.min.css" rel="stylesheet">
    <script src="/ukk/cashier_app/assets/bootstrap.min.js"></script>
    
    <!-- Cashier interface styling -->
    <style>
        .sidebar {
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            width: 200px;
            padding-top: 20px;
            background-color: #28a745;
        }
        .sidebar .nav-link {
            color: #fff;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background-color: #218838;
        }
        .main-content {
            margin-left: 200px;
            padding: 20px;
        }
    </style>
</head>
<body>
    <!-- Cashier Sidebar Navigation -->
    <div class="sidebar">
        <h4 class="text-white text-center">Cashier Dashboard</h4>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="cashier_dashboard.php">Transactions</a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="view_products.php">View Products</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="transaction_history.php">Transaction History</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="logout.php">Logout</a>
            </li>
        </ul>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
        <h2>Available Products</h2>
        <p>Below is the list of products available in the system:</p>

        <!-- Products Table -->
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Image</th>
                    <th>Name</th>
                    <th>Price</th>
                    <th>Stock Quantity</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                <tr>
                    <td><?php echo $product['product_id']; ?></td>
                    <td>
                        <?php if (!empty($product['image_path'])): ?>
                            <img src="<?php echo $product['image_path']; ?>" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                 class="img-thumbnail" style="max-width: 100px">
                        <?php else: ?>
                            <span class="text-muted">No image</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $product['name']; ?></td>
                    <td>$<?php echo number_format($product['price'], 2); ?></td>
                    <td><?php echo $product['stock_quantity']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>