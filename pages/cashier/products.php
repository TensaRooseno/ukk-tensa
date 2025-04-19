<?php
require_once '../../include/config.php';

// Check if user is logged in and has cashier role
if (!isLoggedIn() || hasRole('admin')) {
    header('Location: ../auth/login.php');
    exit;
}

// Get all products
$query = "SELECT * FROM products ORDER BY name ASC";
$products = mysqli_query($conn, $query);

require_once '../../include/header.php';
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h1 class="page-title"><i class="fas fa-box-open me-2"></i>Product Inventory</h1>
    </div>
</div>

<div class="row">
    <!-- Product List -->
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0">Available Products</h3>
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
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center">No products found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add search functionality -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add a search field to the table
    const cardHeader = document.querySelector('.card-header');
    const searchDiv = document.createElement('div');
    searchDiv.className = 'mt-2';
    searchDiv.innerHTML = `
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-search"></i></span>
            <input type="text" id="productSearch" class="form-control" placeholder="Search products...">
        </div>
    `;
    cardHeader.appendChild(searchDiv);
    
    // Add search functionality
    const searchInput = document.getElementById('productSearch');
    searchInput.addEventListener('keyup', function() {
        const searchText = this.value.toLowerCase();
        const rows = document.querySelectorAll('tbody tr');
        
        rows.forEach(function(row) {
            const name = row.cells[2].textContent.toLowerCase();
            const id = row.cells[0].textContent.toLowerCase();
            
            if (name.includes(searchText) || id.includes(searchText)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
});
</script>

<?php require_once '../../include/footer.php'; ?>