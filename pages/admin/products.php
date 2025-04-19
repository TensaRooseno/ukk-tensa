<?php
require_once '../../include/config.php';

// Check if user is logged in and has admin role
if (!isLoggedIn() || !hasRole('admin')) {
    header('Location: ../auth/login.php');
    exit;
}

// Product editing mode
$edit_mode = false;
$edit_product = null;

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Add new product
    if (isset($_POST['add_product'])) {
        $name = mysqli_real_escape_string($conn, $_POST['name']);
        $price = floatval($_POST['price']);
        $stock = intval($_POST['stock']);
        
        $query = "INSERT INTO products (name, price, stock_quantity) VALUES ('$name', '$price', '$stock')";
        if (mysqli_query($conn, $query)) {
            redirectWithMessage($_SERVER['PHP_SELF'], "Product '$name' added successfully", 'success');
        } else {
            redirectWithMessage($_SERVER['PHP_SELF'], "Error adding product: " . mysqli_error($conn), 'error');
        }
    }
    
    // Update product
    if (isset($_POST['update_product'])) {
        $product_id = intval($_POST['product_id']);
        $name = mysqli_real_escape_string($conn, $_POST['name']);
        $price = floatval($_POST['price']);
        $stock = intval($_POST['stock']);
        
        $query = "UPDATE products SET name = '$name', price = '$price', stock_quantity = '$stock' 
                  WHERE product_id = $product_id";
        if (mysqli_query($conn, $query)) {
            redirectWithMessage($_SERVER['PHP_SELF'], "Product updated successfully", 'success');
        } else {
            redirectWithMessage($_SERVER['PHP_SELF'], "Error updating product: " . mysqli_error($conn), 'error');
        }
    }
    
    // Delete product
    if (isset($_POST['delete_product'])) {
        $product_id = intval($_POST['product_id']);
        
        // First check if product is used in any transaction
        $query = "SELECT COUNT(*) as count FROM transaction_details WHERE product_id = $product_id";
        $result = mysqli_query($conn, $query);
        $row = mysqli_fetch_assoc($result);
        
        if ($row['count'] > 0) {
            // Product is used in transactions, cannot delete
            redirectWithMessage($_SERVER['PHP_SELF'], "Cannot delete product because it is used in transactions", 'error');
        } else {
            // Safe to delete
            $query = "DELETE FROM products WHERE product_id = $product_id";
            if (mysqli_query($conn, $query)) {
                redirectWithMessage($_SERVER['PHP_SELF'], "Product deleted successfully", 'success');
            } else {
                redirectWithMessage($_SERVER['PHP_SELF'], "Error deleting product: " . mysqli_error($conn), 'error');
            }
        }
    }
}

// Handle edit request
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $product_id = intval($_GET['edit']);
    $query = "SELECT * FROM products WHERE product_id = $product_id";
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $edit_mode = true;
        $edit_product = mysqli_fetch_assoc($result);
    }
}

// Get all products
$query = "SELECT * FROM products ORDER BY name ASC";
$products = mysqli_query($conn, $query);

require_once '../../include/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <h1>Product Management</h1>
    </div>
</div>

<div class="row">
    <!-- Add/Edit Product Form -->
    <div class="col-md-4">
        <h2><?php echo $edit_mode ? 'Edit Product' : 'Add New Product'; ?></h2>
        
        <form method="POST" action="">
            <?php if ($edit_mode): ?>
                <input type="hidden" name="product_id" value="<?php echo $edit_product['product_id']; ?>">
            <?php endif; ?>
            
            <div>
                <label>Product Name:</label>
                <input type="text" name="name" required 
                       value="<?php echo $edit_mode ? $edit_product['name'] : ''; ?>">
            </div>
            
            <div>
                <label>Price:</label>
                <input type="number" name="price" step="0.01" required min="0"
                       value="<?php echo $edit_mode ? $edit_product['price'] : ''; ?>">
            </div>
            
            <div>
                <label>Stock:</label>
                <input type="number" name="stock" value="<?php echo $edit_mode ? $edit_product['stock_quantity'] : '0'; ?>" min="0">
            </div>
            
            <?php if ($edit_mode): ?>
                <button type="submit" name="update_product">Update Product</button>
                <a href="<?php echo $_SERVER['PHP_SELF']; ?>" style="margin-left: 10px;">Cancel</a>
            <?php else: ?>
                <button type="submit" name="add_product">Add Product</button>
            <?php endif; ?>
        </form>
    </div>
    
    <!-- Product List -->
    <div class="col-md-8">
        <h2>Products</h2>
        
        <table border="1" width="100%">
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Price</th>
                <th>Stock</th>
                <th>Actions</th>
            </tr>
            <?php if (mysqli_num_rows($products) > 0): ?>
                <?php while ($product = mysqli_fetch_assoc($products)): ?>
                    <tr>
                        <td><?php echo $product['product_id']; ?></td>
                        <td><?php echo $product['name']; ?></td>
                        <td>$<?php echo number_format($product['price'], 2); ?></td>
                        <td><?php echo $product['stock_quantity']; ?></td>
                        <td>
                            <a href="?edit=<?php echo $product['product_id']; ?>">Edit</a>
                            
                            <form method="POST" action="" style="display: inline; margin-left: 10px;">
                                <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                <button type="submit" name="delete_product" 
                                        onclick="return confirm('Are you sure you want to delete this product?');">
                                    Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" align="center">No products found</td>
                </tr>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php require_once '../../include/footer.php'; ?>