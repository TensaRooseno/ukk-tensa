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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
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
            background-color: #28a745;
            border: none;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.2s;
        }
        .profile-button:hover,
        .profile-button:focus {
            background-color: #218838;
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
                <div class="small text-muted">Cashier</div>
            </li>
            <li><a class="dropdown-item text-danger" href="logout.php">Logout</a></li>
        </ul>
    </div>

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
                <a class="nav-link" href="transactions.php">Transaction History</a>
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