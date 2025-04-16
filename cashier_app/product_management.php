<?php
session_start();
require_once 'includes/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: index.php");
    exit;
}

// Handle Add/Edit/Delete Product
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_product'])) {
        $stmt = $conn->prepare("INSERT INTO products (name, price, stock_quantity) VALUES (:name, :price, :stock)");
        $stmt->execute([
            'name' => $_POST['name'],
            'price' => $_POST['price'],
            'stock' => $_POST['stock']
        ]);
    } elseif (isset($_POST['edit_product'])) {
        $stmt = $conn->prepare("UPDATE products SET name = :name, price = :price, = stock_quantity = :stock WHERE product_id = :id");
        $stmt->execute([
            'id' => $_POST['product_id'],
            'name' => $_POST['name'],
            'price' => $_POST['price'],
            'stock' => $_POST['stock']
        ]);
    } elseif (isset($_POST['delete_product'])) {
        $stmt = $conn->prepare("DELETE FROM products WHERE product_id = :id");
        $stmt->execute(['id' => $_POST['product_id']]);
    }
}

// Fetch Products with Pagination
$limit = 5; // Products per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$totalStmt = $conn->query("SELECT COUNT(*) as total FROM products");
$total = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($total / $limit);

$products = $conn->query("SELECT * FROM products ORDER BY product_id LIMIT $limit OFFSET $offset")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Product Management - Cashier App</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="/ukk/cashier_app/assets/bootstrap.min.css" rel="stylesheet">
    <script src="/ukk/cashier_app/assets/bootstrap.min.js"></script>
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
        .sidebar .nav-link:hover {
            background-color: #495057;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <h4 class="text-white text-center">Admin Dashboard</h4>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="admin_dashboard.php">Overview</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="user_management.php">User Management</a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="product_management.php">Product Management</a>
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

    <!-- Main Content -->
    <div class="main-content">
        <h2>Product Management</h2>
        <p>Manage products available for sale.</p>

        <!-- Add Product Button -->
        <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addProductModal">
            Add New Product
        </button>

        <!-- Product List -->
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                <tr>
                    <td><?php echo $product['product_id']; ?></td>
                    <td><?php echo $product['name']; ?></td>
                    <td>$<?php echo number_format($product['price'], 2); ?></td>
                    <td><?php echo $product['stock_quantity']; ?></td>
                    <td>
                        <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editProductModal<?php echo $product['product_id']; ?>">Edit</button>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                            <button type="submit" name="delete_product" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this product?');">Delete</button>
                        </form>
                    </td>
                </tr>

                <!-- Edit Product Modal -->
                <div class="modal fade" id="editProductModal<?php echo $product['product_id']; ?>" tabindex="-1" aria-labelledby="editProductModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="editProductModalLabel">Edit Product</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="POST">
                                <div class="modal-body">
                                    <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                    <div class="mb-3">
                                        <label>Name</label>
                                        <input type="text" name="name" class="form-control" value="<?php echo $product['name']; ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label>Price</label>
                                        <input type="number" name="price" class="form-control" value="<?php echo $product['price']; ?>" step="0.01" required>
                                    </div>
                                    <div class="mb-3">
                                        <label>Stock Quantity</label>
                                        <input type="number" name="stock" class="form-control" value="<?php echo $product['stock_quantity']; ?>" required>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    <button type="submit" name="edit_product" class="btn btn-primary">Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination -->
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

    <!-- Add Product Modal -->
    <div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addProductModalLabel">Add New Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Price</label>
                            <input type="number" name="price" class="form-control" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label>Stock Quantity</label>
                            <input type="number" name="stock" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="add_product" class="btn btn-primary">Add Product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>