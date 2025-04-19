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

// Create upload directory if it doesn't exist
$upload_dir = '../../assets/product_images/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Add new product
    if (isset($_POST['add_product'])) {
        $name = mysqli_real_escape_string($conn, $_POST['name']);
        $price = floatval($_POST['price']);
        $stock = intval($_POST['stock']);
        
        // Handle image upload
        $image_path = null;
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
            $file_name = uniqid() . '.' . pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
            $destination = $upload_dir . $file_name;
            
            // Move the uploaded file to the destination
            if (move_uploaded_file($_FILES['product_image']['tmp_name'], $destination)) {
                $image_path = 'assets/product_images/' . $file_name;
            }
        }
        
        $query = "INSERT INTO products (name, price, stock_quantity, image_path) 
                  VALUES ('$name', '$price', '$stock', " . ($image_path ? "'$image_path'" : "NULL") . ")";
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
        
        // Check if there's a new image uploaded
        $image_update = "";
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
            $file_name = uniqid() . '.' . pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
            $destination = $upload_dir . $file_name;
            
            // Move the uploaded file to the destination
            if (move_uploaded_file($_FILES['product_image']['tmp_name'], $destination)) {
                $image_path = 'assets/product_images/' . $file_name;
                $image_update = ", image_path = '$image_path'";
                
                // Delete old image if exists
                if (isset($_POST['current_image']) && $_POST['current_image']) {
                    $old_image = $_POST['current_image'];
                    if (file_exists('../../' . $old_image)) {
                        unlink('../../' . $old_image);
                    }
                }
            }
        }
        
        $query = "UPDATE products SET name = '$name', price = '$price', stock_quantity = '$stock'$image_update 
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
            // Delete the product image if it exists
            $query = "SELECT image_path FROM products WHERE product_id = $product_id";
            $result = mysqli_query($conn, $query);
            if ($result && mysqli_num_rows($result) > 0) {
                $product = mysqli_fetch_assoc($result);
                if ($product['image_path'] && file_exists('../../' . $product['image_path'])) {
                    unlink('../../' . $product['image_path']);
                }
            }
            
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
        <h1 class="page-title"><i class="fas fa-box-open me-2"></i>Product Management</h1>
    </div>
</div>

<div class="row">
    <!-- Add/Edit Product Form -->
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header">
                <h3 class="mb-0"><?php echo $edit_mode ? 'Edit Product' : 'Add New Product'; ?></h3>
            </div>
            <div class="card-body">
                <form method="POST" action="" enctype="multipart/form-data">
                    <?php if ($edit_mode): ?>
                        <input type="hidden" name="product_id" value="<?php echo $edit_product['product_id']; ?>">
                        <?php if ($edit_product['image_path']): ?>
                            <input type="hidden" name="current_image" value="<?php echo $edit_product['image_path']; ?>">
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="product-name" class="form-label">Product Name:</label>
                        <input type="text" id="product-name" name="name" required class="form-control"
                               value="<?php echo $edit_mode ? $edit_product['name'] : ''; ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="product-price" class="form-label">Price:</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" id="product-price" name="price" step="0.01" required min="0" class="form-control"
                                   value="<?php echo $edit_mode ? $edit_product['price'] : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="product-stock" class="form-label">Stock:</label>
                        <input type="number" id="product-stock" name="stock" class="form-control"
                               value="<?php echo $edit_mode ? $edit_product['stock_quantity'] : '0'; ?>" min="0">
                    </div>
                    
                    <div class="mb-3">
                        <label for="product-image" class="form-label">Product Image:</label>
                        <input type="file" id="product-image" name="product_image" accept="image/*" class="form-control">
                        <div class="form-text text-muted">
                            Supported formats: JPG, JPEG, PNG, GIF (Max size: 2MB)
                        </div>
                    </div>
                    
                    <?php if ($edit_mode && $edit_product['image_path']): ?>
                        <div class="mb-3">
                            <label class="form-label">Current Image:</label>
                            <div class="product-img-container">
                                <img src="../../<?php echo $edit_product['image_path']; ?>" alt="Product Image" class="product-img" style="max-width: 150px; max-height: 150px;">
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="d-grid gap-2 d-md-flex mt-4">
                        <?php if ($edit_mode): ?>
                            <button type="submit" name="update_product" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Update Product
                            </button>
                            <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-secondary">Cancel</a>
                        <?php else: ?>
                            <button type="submit" name="add_product" class="btn btn-success">
                                <i class="fas fa-plus-circle me-2"></i>Add Product
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Product List -->
    <div class="col-md-8">
        <div class="card h-100">
            <div class="card-header">
                <h3 class="mb-0">Products</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Image</th>
                                <th>Name</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($products) > 0): ?>
                                <?php while ($product = mysqli_fetch_assoc($products)): ?>
                                    <tr>
                                        <td><?php echo $product['product_id']; ?></td>
                                        <td style="width: 80px; text-align: center;">
                                            <?php if ($product['image_path']): ?>
                                                <img src="../../<?php echo $product['image_path']; ?>" alt="<?php echo $product['name']; ?>" class="img-thumbnail" style="max-width: 60px; max-height: 60px;">
                                            <?php else: ?>
                                                <span class="badge bg-secondary"><i class="fas fa-image me-1"></i>No image</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $product['name']; ?></td>
                                        <td>$<?php echo number_format($product['price'], 2); ?></td>
                                        <td>
                                            <?php if ($product['stock_quantity'] > 10): ?>
                                                <span class="badge bg-success"><?php echo $product['stock_quantity']; ?></span>
                                            <?php elseif ($product['stock_quantity'] > 0): ?>
                                                <span class="badge bg-warning"><?php echo $product['stock_quantity']; ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">0</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="?edit=<?php echo $product['product_id']; ?>" class="btn btn-primary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                
                                                <form method="POST" action="" style="display: inline;">
                                                    <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                                    <button type="submit" name="delete_product" class="btn btn-danger" 
                                                            onclick="return confirm('Are you sure you want to delete this product?');">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center">No products found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../include/footer.php'; ?>