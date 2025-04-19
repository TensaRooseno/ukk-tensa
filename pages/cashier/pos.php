<?php
require_once '../../include/config.php';

// Check if user is logged in and has cashier role
if (!isLoggedIn() || hasRole('admin')) {
    header('Location: ../auth/login.php');
    exit;
}

// Get all products
$query = "SELECT * FROM products WHERE stock_quantity > 0";
$products = mysqli_query($conn, $query);

// Initialize cart if it doesn't exist
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Initialize member data in session if needed
if (!isset($_SESSION['member'])) {
    $_SESSION['member'] = null;
}

// Points calculation constants
$POINTS_RATE = 0.01; // Points earned per dollar spent
$POINTS_VALUE = 0.1; // Value of 1 point (in dollars)

// Process form actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Add product to cart
    if (isset($_POST['add_to_cart'])) {
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;
        $price = isset($_POST['price']) ? floatval($_POST['price']) : 0;
        $name = isset($_POST['product_name']) ? $_POST['product_name'] : '';
        $phone = isset($_POST['phone_number']) ? $_POST['phone_number'] : '';
        
        // Validate inputs
        if ($product_id <= 0) {
            redirectWithMessage('pos.php', 'Please select a valid product', 'error');
            exit;
        }
        
        if ($quantity <= 0) {
            redirectWithMessage('pos.php', 'Quantity must be greater than zero', 'error');
            exit;
        }
        
        if ($price <= 0) {
            redirectWithMessage('pos.php', 'Product price is invalid', 'error');
            exit;
        }

        // Check for member if phone number provided
        if (!empty($phone)) {
            $phone = mysqli_real_escape_string($conn, $phone);
            $query = "SELECT * FROM members WHERE phone_number = '$phone'";
            $result = mysqli_query($conn, $query);
            
            if (mysqli_num_rows($result) > 0) {
                // Member found, store in session
                $_SESSION['member'] = mysqli_fetch_assoc($result);
            } else {
                // No member found, create new member
                $member_name = isset($_POST['member_name']) ? mysqli_real_escape_string($conn, $_POST['member_name']) : 'Unknown';
                $query = "INSERT INTO members (phone_number, name, points) VALUES ('$phone', '$member_name', 0)";
                if (mysqli_query($conn, $query)) {
                    $member_id = mysqli_insert_id($conn);
                    $query = "SELECT * FROM members WHERE id = $member_id";
                    $result = mysqli_query($conn, $query);
                    $_SESSION['member'] = mysqli_fetch_assoc($result);
                    
                    redirectWithMessage('pos.php', "New member registered with phone number: $phone", 'success');
                    exit;
                } else {
                    redirectWithMessage('pos.php', "Error registering member: " . mysqli_error($conn), 'error');
                    exit;
                }
            }
        }
        
        // Check stock availability
        $stock_query = "SELECT stock_quantity FROM products WHERE product_id = $product_id";
        $stock_result = mysqli_query($conn, $stock_query);
        
        if ($stock_result && mysqli_num_rows($stock_result) > 0) {
            $stock_row = mysqli_fetch_assoc($stock_result);
            $available_stock = $stock_row['stock_quantity'];
            
            // Calculate total quantity in cart
            $cart_quantity = 0;
            foreach ($_SESSION['cart'] as $item) {
                if ($item['product_id'] == $product_id) {
                    $cart_quantity += $item['quantity'];
                }
            }
            
            // Check if requested quantity exceeds available stock
            if (($cart_quantity + $quantity) > $available_stock) {
                redirectWithMessage('pos.php', "Not enough stock available. Only $available_stock units left.", 'error');
                exit;
            }
        }
        
        // Check if product already in cart
        $found = false;
        foreach ($_SESSION['cart'] as $key => $item) {
            if ($item['product_id'] == $product_id) {
                $_SESSION['cart'][$key]['quantity'] += $quantity;
                $_SESSION['cart'][$key]['subtotal'] = $_SESSION['cart'][$key]['price'] * $_SESSION['cart'][$key]['quantity'];
                $found = true;
                break;
            }
        }
        
        // If not found, add to cart
        if (!$found) {
            $_SESSION['cart'][] = [
                'product_id' => $product_id,
                'name' => $name,
                'quantity' => $quantity,
                'price' => $price,
                'subtotal' => $price * $quantity
            ];
        }
        
        redirectWithMessage('pos.php', 'Product added to cart', 'success');
    }
    
    // Remove item from cart
    if (isset($_POST['remove_item'])) {
        $index = isset($_POST['item_index']) ? intval($_POST['item_index']) : -1;
        if ($index >= 0 && isset($_SESSION['cart'][$index])) {
            unset($_SESSION['cart'][$index]);
            $_SESSION['cart'] = array_values($_SESSION['cart']); // Reindex
            redirectWithMessage('pos.php', 'Item removed from cart', 'success');
        } else {
            redirectWithMessage('pos.php', 'Invalid item', 'error');
        }
    }
    
    // Clear member from session
    if (isset($_POST['clear_member'])) {
        $_SESSION['member'] = null;
        redirectWithMessage('pos.php', "Member info cleared", 'success');
    }
    
    // Process transaction
    if (isset($_POST['process_transaction'])) {
        if (empty($_SESSION['cart'])) {
            redirectWithMessage('pos.php', 'Cart is empty', 'error');
            exit;
        }
        
        // Get paid amount
        $paid_amount = isset($_POST['paid_amount']) ? floatval($_POST['paid_amount']) : 0;
        
        // Points related variables
        $use_points = isset($_POST['use_points']) && $_SESSION['member'] && $_SESSION['member']['points'] > 0;
        $member_id = isset($_SESSION['member']) ? $_SESSION['member']['id'] : null;
        
        // Start transaction to ensure database consistency
        mysqli_begin_transaction($conn);
        
        try {
            // Calculate total
            $total_amount = 0;
            foreach ($_SESSION['cart'] as $item) {
                $total_amount += $item['subtotal'];
            }
            
            // Calculate discount from points if using points
            $points_used = 0;
            $discount_amount = 0;
            
            if ($use_points && isset($_SESSION['member'])) {
                // Only allow points to be used if the member has already made their first purchase
                if ($_SESSION['member']['first_purchase_date']) {
                    // Only use points that are available (not earned in the current transaction)
                    $available_points = $_SESSION['member']['points'];
                    $points_used = $available_points;
                    $discount_amount = $points_used * $POINTS_VALUE;
                    
                    // Make sure discount doesn't exceed total
                    if ($discount_amount > $total_amount) {
                        $discount_amount = $total_amount;
                        $points_used = $total_amount / $POINTS_VALUE;
                    }
                } else {
                    // First purchase - cannot use points yet
                    $points_used = 0;
                    $discount_amount = 0;
                }
            }
            
            // Calculate final total after discount
            $final_total = $total_amount - $discount_amount;
            
            // Calculate change amount
            $change_amount = $paid_amount - $final_total;
            
            // Validate payment
            if ($paid_amount < $final_total) {
                throw new Exception("Paid amount must be at least equal to the final total amount.");
            }
            
            // Calculate points earned (only on the paid amount)
            $points_earned = $final_total * $POINTS_RATE;
            
            // Insert transaction
            $cashier_id = $_SESSION['user_id'];
            $query = "INSERT INTO transactions (
                        cashier_id, total_amount, discount_amount, 
                        member_id, points_used, points_earned,
                        paid_amount, change_amount
                      ) VALUES (
                        '$cashier_id', '$total_amount', '$discount_amount',
                        " . ($member_id ? "'$member_id'" : "NULL") . ", 
                        '$points_used', '$points_earned',
                        '$paid_amount', '$change_amount'
                      )";
            $result = mysqli_query($conn, $query);
            
            if (!$result) {
                throw new Exception("Error creating transaction: " . mysqli_error($conn));
            }
            
            $transaction_id = mysqli_insert_id($conn);
            
            // Insert transaction details and update stock
            foreach ($_SESSION['cart'] as $item) {
                $product_id = $item['product_id'];
                $quantity = $item['quantity'];
                $price = $item['price'];
                
                // Insert detail
                $query = "INSERT INTO transaction_details (transaction_id, product_id, quantity, price_per_unit) 
                          VALUES ('$transaction_id', '$product_id', '$quantity', '$price')";
                $result = mysqli_query($conn, $query);
                
                if (!$result) {
                    throw new Exception("Error creating transaction details: " . mysqli_error($conn));
                }
                
                // Update stock
                $query = "UPDATE products SET stock_quantity = stock_quantity - $quantity 
                          WHERE product_id = $product_id AND stock_quantity >= $quantity";
                $result = mysqli_query($conn, $query);
                
                if (!$result || mysqli_affected_rows($conn) == 0) {
                    throw new Exception("Error updating stock for product $product_id. Not enough stock available.");
                }
            }
            
            // If we have a member, update their points
            if ($member_id) {
                // Calculate new points balance
                $new_points = $_SESSION['member']['points'] - $points_used + $points_earned;
                
                // Update member record
                $query = "UPDATE members SET points = '$new_points'";
                
                // Set first purchase date if this is their first purchase
                if (!$_SESSION['member']['first_purchase_date']) {
                    $query .= ", first_purchase_date = NOW()";
                }
                
                $query .= " WHERE id = '$member_id'";
                $result = mysqli_query($conn, $query);
                
                if (!$result) {
                    throw new Exception("Error updating member points: " . mysqli_error($conn));
                }
                
                // Update the member in session if not clearing after transaction
                $_SESSION['member']['points'] = $new_points;
            }
            
            // Commit the transaction if everything was successful
            mysqli_commit($conn);
            
            // Clear cart and redirect
            $_SESSION['cart'] = [];
            redirectWithMessage('transactions.php', 'Transaction #' . $transaction_id . ' successfully completed', 'success');
            
        } catch (Exception $e) {
            // Roll back the transaction if any query failed
            mysqli_rollback($conn);
            redirectWithMessage('pos.php', 'Error processing transaction: ' . $e->getMessage(), 'error');
        }
    }
    
    // Clear cart
    if (isset($_POST['clear_cart'])) {
        $_SESSION['cart'] = [];
        redirectWithMessage('pos.php', 'Cart has been cleared', 'success');
    }
}

// Retrieve the latest product data for the select dropdown
$products_query = "SELECT * FROM products WHERE stock_quantity > 0";
$products = mysqli_query($conn, $products_query);

require_once '../../include/header.php';
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h1><i class="fas fa-cash-register me-2"></i>Transactions</h1>
    </div>
</div>

<div class="row">
    <!-- Add Product Form -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0"><i class="fas fa-cart-plus me-2"></i>Add Product</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="product-select" class="form-label">Select Product:</label>
                        <select id="product-select" name="product_id" required class="form-select" onchange="updateProduct()">
                            <option value="">-- Select Product --</option>
                            <?php 
                            // Reset the result pointer to the beginning
                            mysqli_data_seek($products, 0);
                            while ($row = mysqli_fetch_assoc($products)): 
                            ?>
                                <option value="<?php echo $row['product_id']; ?>" 
                                        data-price="<?php echo $row['price']; ?>"
                                        data-name="<?php echo $row['name']; ?>"
                                        data-stock="<?php echo $row['stock_quantity']; ?>"
                                        data-image="<?php echo $row['image_path']; ?>">
                                    <?php echo $row['name']; ?> ($<?php echo $row['price']; ?>) - Stock: <?php echo $row['stock_quantity']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <!-- Product Image Preview -->
                    <div id="product-image-container" class="product-img-container" style="display: none;">
                        <img id="product-image-preview" src="" alt="Product Image" class="product-img" style="max-width: 150px; max-height: 150px;">
                        <p id="no-image-text" class="text-muted" style="display: none;">
                            <i class="fas fa-image me-2"></i>No image available
                        </p>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="price" class="form-label">Price:</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="price" id="price" readonly class="form-control">
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="stock" class="form-label">Available Stock:</label>
                            <input type="text" id="stock" readonly class="form-control">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="quantity" class="form-label">Quantity:</label>
                            <input type="number" name="quantity" id="quantity" min="1" value="1" 
                                   class="form-control" onchange="validateQuantity()">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="subtotal" class="form-label">Subtotal:</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="text" id="subtotal" readonly class="form-control">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="phone-number" class="form-label">Member Phone Number:</label>
                        <div class="input-group">
                            <input type="text" id="phone-number" name="phone_number" 
                                   value="<?php echo isset($_SESSION['member']) ? $_SESSION['member']['phone_number'] : ''; ?>" 
                                   placeholder="Enter phone number" class="form-control">
                            
                            <?php if (isset($_SESSION['member']) && $_SESSION['member']): ?>
                            <button type="button" id="clear-member-btn" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i> Clear
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="mb-3" id="member-name-container" <?php echo (isset($_SESSION['member']) && $_SESSION['member']) ? 'style="display: none;"' : ''; ?>>
                        <label for="member-name" class="form-label">Member Name:</label>
                        <input type="text" id="member-name" name="member_name" class="form-control" placeholder="Enter member name">
                        <small class="form-text text-muted">Only required for new members</small>
                    </div>
                    
                    <?php if (isset($_SESSION['member']) && $_SESSION['member']): ?>
                    <div class="alert alert-info mt-2">
                        <span>
                            <i class="fas fa-star me-1"></i>
                            <strong>Member:</strong> <?php echo $_SESSION['member']['name'] ? $_SESSION['member']['name'] : 'Unknown'; ?>
                        </span>
                        <br>
                        <span>
                            <i class="fas fa-star me-1"></i>
                            <strong>Total Points:</strong> <?php echo number_format($_SESSION['member']['points'], 2); ?>
                        </span>
                        <?php if (!$_SESSION['member']['first_purchase_date']): ?>
                        <p class="text-muted mt-1"><i class="fas fa-info-circle me-1"></i> This is the member's first purchase. Points earned from this transaction can only be used in future transactions.</p>
                        <?php else: ?>
                        <p class="text-muted mt-1"><i class="fas fa-info-circle me-1"></i> Points earned from this transaction can only be used in future transactions.</p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <input type="hidden" name="product_name" id="product_name">
                    <button type="submit" name="add_to_cart" id="add_to_cart_btn" class="btn btn-primary w-100">
                        <i class="fas fa-plus-circle me-2"></i>Add to Cart
                    </button>
                </form>
                
                <!-- Hidden form for clearing member - separate from all other forms -->
                <?php if (isset($_SESSION['member']) && $_SESSION['member']): ?>
                <form id="clear-member-form" method="POST" action="" style="display: none;">
                    <input type="hidden" name="clear_member" value="1">
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Cart -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                <h3 class="mb-0"><i class="fas fa-shopping-cart me-2"></i>Cart</h3>
                <?php if (!empty($_SESSION['cart'])): ?>
                    <span class="badge bg-light text-dark"><?php echo count($_SESSION['cart']); ?> item(s)</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (empty($_SESSION['cart'])): ?>
                    <div class="text-center p-4 text-muted">
                        <i class="fas fa-shopping-cart fa-3x mb-3"></i>
                        <p>Cart is empty</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Product</th>
                                    <th>Price</th>
                                    <th>Quantity</th>
                                    <th>Subtotal</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total = 0;
                                foreach ($_SESSION['cart'] as $index => $item): 
                                    $total += $item['subtotal'];
                                ?>
                                    <tr>
                                        <td><?php echo $item['name']; ?></td>
                                        <td>$<?php echo number_format($item['price'], 2); ?></td>
                                        <td><?php echo $item['quantity']; ?></td>
                                        <td>$<?php echo number_format($item['subtotal'], 2); ?></td>
                                        <td>
                                            <form method="POST" action="">
                                                <input type="hidden" name="item_index" value="<?php echo $index; ?>">
                                                <button type="submit" name="remove_item" class="btn btn-sm btn-danger">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-primary">
                                    <td colspan="3" class="text-end"><strong>Total:</strong></td>
                                    <td colspan="2"><strong>$<?php echo number_format($total, 2); ?></strong></td>
                                </tr>
                                
                                <?php if (isset($_SESSION['member']) && $_SESSION['member'] && $_SESSION['member']['points'] > 0): ?>
                                    <?php 
                                    $potential_discount = $_SESSION['member']['points'] * $POINTS_VALUE;
                                    $discount_amount = ($potential_discount > $total) ? $total : $potential_discount;
                                    $final_total = $total - $discount_amount;
                                    ?>
                                    <tr class="table-info">
                                        <td colspan="3" class="text-end"><strong>Discount (if points used):</strong></td>
                                        <td colspan="2"><strong>$<?php echo number_format($discount_amount, 2); ?></strong></td>
                                    </tr>
                                    <tr class="table-success">
                                        <td colspan="3" class="text-end"><strong>Final Total:</strong></td>
                                        <td colspan="2"><strong id="final-total-value">$<?php echo number_format($final_total, 2); ?></strong></td>
                                    </tr>
                                <?php else: ?>
                                    <tr class="table-success">
                                        <td colspan="3" class="text-end"><strong>Final Total:</strong></td>
                                        <td colspan="2"><strong id="final-total-value">$<?php echo number_format($total, 2); ?></strong></td>
                                    </tr>
                                <?php endif; ?>
                                
                                <!-- Payment section -->
                                <tr class="table-warning">
                                    <td colspan="3" class="text-end"><strong>Paid Amount:</strong></td>
                                    <td colspan="2">
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="number" step="0.01" min="0" class="form-control" id="paid-amount" name="paid_amount" placeholder="Enter paid amount" required>
                                        </div>
                                    </td>
                                </tr>
                                <tr class="table-danger">
                                    <td colspan="3" class="text-end"><strong>Change:</strong></td>
                                    <td colspan="2">
                                        <strong id="change-amount">$0.00</strong>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    
                    <div class="mt-4">
                        <div class="d-flex justify-content-between">
                            <form method="POST" action="" id="transactionForm" onsubmit="return validatePayment()">
                                <?php if (isset($_SESSION['member']) && $_SESSION['member'] && $_SESSION['member']['points'] > 0 && $_SESSION['member']['first_purchase_date']): ?>
                                <div class="alert alert-success mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="use_points" id="use_points" checked onchange="updateTotals()">
                                        <label class="form-check-label" for="use_points">
                                            Use points
                                            (<?php echo number_format($_SESSION['member']['points'], 2); ?> points = 
                                            $<?php echo number_format($_SESSION['member']['points'] * $POINTS_VALUE, 2); ?>)
                                        </label>
                                    </div>
                                </div>
                                <?php elseif (isset($_SESSION['member']) && $_SESSION['member'] && !$_SESSION['member']['first_purchase_date']): ?>
                                <div class="alert alert-info mb-3">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <span>Points will be earned after this first purchase and can be used in future transactions.</span>
                                </div>
                                <?php elseif (isset($_SESSION['member']) && $_SESSION['member'] && $_SESSION['member']['points'] == 0): ?>
                                <div class="alert alert-info mb-3">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <span>No points available for use. Points earned from this purchase can be used in future transactions.</span>
                                </div>
                                <?php endif; ?>
                                
                                <input type="hidden" name="paid_amount" id="hidden-paid-amount">
                                <input type="hidden" name="change_amount" id="hidden-change-amount">
                                
                                <button type="submit" name="process_transaction" id="process-transaction-btn" class="btn btn-success">
                                    <i class="fas fa-check-circle me-2"></i>Process Transaction
                                </button>
                            </form>
                            
                            <!-- Separate form for clearing the cart -->
                            <form method="POST" action="">
                                <button type="submit" name="clear_cart" class="btn btn-outline-secondary">
                                    <i class="fas fa-times-circle me-2"></i>Clear Cart
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function updateProduct() {
    const select = document.querySelector('select[name="product_id"]');
    if (select.selectedIndex === 0) {
        document.getElementById('price').value = '';
        document.getElementById('stock').value = '';
        document.getElementById('product_name').value = '';
        document.getElementById('subtotal').value = '';
        document.getElementById('add_to_cart_btn').disabled = true;
        document.getElementById('product-image-container').style.display = 'none';
        return;
    }
    
    const price = select.options[select.selectedIndex].getAttribute('data-price');
    const name = select.options[select.selectedIndex].getAttribute('data-name');
    const stock = select.options[select.selectedIndex].getAttribute('data-stock');
    const imagePath = select.options[select.selectedIndex].getAttribute('data-image');
    
    document.getElementById('price').value = price;
    document.getElementById('stock').value = stock;
    document.getElementById('product_name').value = name;
    document.getElementById('add_to_cart_btn').disabled = false;
    
    // Handle product image display
    const imageContainer = document.getElementById('product-image-container');
    const imagePreview = document.getElementById('product-image-preview');
    const noImageText = document.getElementById('no-image-text');
    
    imageContainer.style.display = 'flex';
    
    if (imagePath && imagePath !== 'null') {
        imagePreview.src = '../../' + imagePath;
        imagePreview.style.display = 'block';
        noImageText.style.display = 'none';
    } else {
        imagePreview.style.display = 'none';
        noImageText.style.display = 'block';
    }
    
    validateQuantity();
}

function validateQuantity() {
    const quantity = parseInt(document.getElementById('quantity').value);
    const stock = parseInt(document.getElementById('stock').value);
    const price = parseFloat(document.getElementById('price').value);
    
    if (isNaN(quantity) || quantity <= 0) {
        document.getElementById('quantity').value = 1;
        calculateSubtotal();
        return;
    }
    
    if (quantity > stock) {
        document.getElementById('quantity').value = stock;
        alert('Quantity cannot exceed available stock.');
    }
    
    calculateSubtotal();
}

function calculateSubtotal() {
    const price = parseFloat(document.getElementById('price').value);
    const quantity = parseInt(document.getElementById('quantity').value);
    
    if (!isNaN(price) && !isNaN(quantity)) {
        const subtotal = price * quantity;
        document.getElementById('subtotal').value = subtotal.toFixed(2);
    } else {
        document.getElementById('subtotal').value = '';
    }
}

function calculateChange() {
    const paidAmountInput = document.getElementById('paid-amount');
    const changeAmountElement = document.getElementById('change-amount');
    const processTransactionButton = document.getElementById('process-transaction-btn');
    const hiddenPaidAmount = document.getElementById('hidden-paid-amount');
    const hiddenChangeAmount = document.getElementById('hidden-change-amount');
    
    let finalTotal = parseFloat(document.getElementById('final-total-value').textContent.replace('$', ''));
    let paidAmount = parseFloat(paidAmountInput.value);
    
    if (isNaN(paidAmount) || paidAmount < finalTotal) {
        changeAmountElement.textContent = '$0.00';
        processTransactionButton.disabled = true;
        changeAmountElement.style.color = 'red';
        return;
    }
    
    const change = paidAmount - finalTotal;
    changeAmountElement.textContent = `$${change.toFixed(2)}`;
    changeAmountElement.style.color = '';
    processTransactionButton.disabled = false;
    
    // Update hidden fields for form submission
    hiddenPaidAmount.value = paidAmount;
    hiddenChangeAmount.value = change;
}

function updateTotals() {
    const usePointsCheckbox = document.getElementById('use_points');
    const finalTotalElement = document.getElementById('final-total-value');
    
    // Get original total amount
    <?php if (isset($total)): ?>
    let total = <?php echo $total; ?>;
    <?php else: ?>
    let total = 0;
    <?php endif; ?>
    
    let discount = 0;
    
    // Calculate discount if points are being used
    if (usePointsCheckbox && usePointsCheckbox.checked) {
        <?php if (isset($_SESSION['member']) && $_SESSION['member']): ?>
        const points = <?php echo $_SESSION['member']['points']; ?>;
        const pointValue = <?php echo $POINTS_VALUE; ?>;
        const potentialDiscount = points * pointValue;
        discount = (potentialDiscount > total) ? total : potentialDiscount;
        <?php endif; ?>
    }
    
    // Calculate final total and update display
    const finalTotal = total - discount;
    finalTotalElement.textContent = `$${finalTotal.toFixed(2)}`;
    
    // Recalculate change based on new total
    calculateChange();
}

function validatePayment() {
    const paidAmount = parseFloat(document.getElementById('paid-amount').value);
    const finalTotal = parseFloat(document.getElementById('final-total-value').textContent.replace('$', ''));
    
    if (isNaN(paidAmount)) {
        alert('Please enter a valid payment amount.');
        return false;
    }
    
    if (paidAmount < finalTotal) {
        alert('Paid amount must be at least equal to the final total amount.');
        return false;
    }
    
    // Set the hidden input values for form submission
    document.getElementById('hidden-paid-amount').value = paidAmount;
    document.getElementById('hidden-change-amount').value = (paidAmount - finalTotal).toFixed(2);
    
    return true;
}

// Initialize form and event listeners on page load
window.onload = function() {
    updateProduct();
    
    // Set up event listeners for live calculations
    const paidAmountInput = document.getElementById('paid-amount');
    if (paidAmountInput) {
        paidAmountInput.addEventListener('input', calculateChange);
    }
    
    const usePointsCheckbox = document.getElementById('use_points');
    if (usePointsCheckbox) {
        usePointsCheckbox.addEventListener('change', updateTotals);
    }
    
    // Add event listener for the clear member button
    const clearMemberBtn = document.getElementById('clear-member-btn');
    if (clearMemberBtn) {
        clearMemberBtn.addEventListener('click', function() {
            // Submit the hidden form to clear member
            document.getElementById('clear-member-form').submit();
        });
    }
};
</script>

<!-- Clear floating elements before the footer -->
<div style="clear: both; margin-bottom: 40px;"></div>

<?php require_once '../../include/footer.php'; ?>