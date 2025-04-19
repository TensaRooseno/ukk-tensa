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
                $query = "INSERT INTO members (phone_number, points) VALUES ('$phone', 0)";
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
                // Use all available points (up to the total amount)
                $points_used = $_SESSION['member']['points'];
                $discount_amount = $points_used * $POINTS_VALUE;
                
                // Make sure discount doesn't exceed total
                if ($discount_amount > $total_amount) {
                    $discount_amount = $total_amount;
                    $points_used = $total_amount / $POINTS_VALUE;
                }
            }
            
            // Calculate final total after discount
            $final_total = $total_amount - $discount_amount;
            
            // Calculate points earned (only on the paid amount)
            $points_earned = $final_total * $POINTS_RATE;
            
            // Insert transaction
            $cashier_id = $_SESSION['user_id'];
            $query = "INSERT INTO transactions (
                        cashier_id, total_amount, discount_amount, 
                        member_id, points_used, points_earned
                      ) VALUES (
                        '$cashier_id', '$total_amount', '$discount_amount',
                        " . ($member_id ? "'$member_id'" : "NULL") . ", 
                        '$points_used', '$points_earned'
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

<div class="row">
    <div class="col-md-12">
        <h1>Point of Sale</h1>
    </div>
</div>

<div class="row">
    <!-- Add Product Form -->
    <div class="col-md-4">
        <h3>Add Product</h3>
        <form method="POST" action="">
            <div>
                <label>Select Product:</label>
                <select name="product_id" required onchange="updatePrice()">
                    <option value="">-- Select Product --</option>
                    <?php 
                    // Reset the result pointer to the beginning
                    mysqli_data_seek($products, 0);
                    while ($row = mysqli_fetch_assoc($products)): 
                    ?>
                        <option value="<?php echo $row['product_id']; ?>" 
                                data-price="<?php echo $row['price']; ?>"
                                data-name="<?php echo $row['name']; ?>"
                                data-stock="<?php echo $row['stock_quantity']; ?>">
                            <?php echo $row['name']; ?> ($<?php echo $row['price']; ?>) - Stock: <?php echo $row['stock_quantity']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div>
                <label>Price:</label>
                <input type="number" name="price" id="price" readonly>
            </div>
            
            <div>
                <label>Available Stock:</label>
                <input type="text" id="stock" readonly>
            </div>
            
            <div>
                <label>Quantity:</label>
                <input type="number" name="quantity" id="quantity" min="1" value="1" onchange="validateQuantity()">
            </div>
            
            <div>
                <label>Subtotal:</label>
                <input type="text" id="subtotal" readonly>
            </div>

            <div>
                <label>Member Phone Number:</label>
                <input type="text" name="phone_number" value="<?php echo isset($_SESSION['member']) ? $_SESSION['member']['phone_number'] : ''; ?>" 
                       placeholder="Enter phone number">
                <?php if (isset($_SESSION['member']) && $_SESSION['member']): ?>
                <div style="margin: 5px 0;">
                    <strong>Points Available:</strong> <?php echo number_format($_SESSION['member']['points'], 2); ?>
                    <button type="submit" name="clear_member" style="margin-left: 10px;">Clear</button>
                </div>
                <?php endif; ?>
            </div>
            
            <input type="hidden" name="product_name" id="product_name">
            <button type="submit" name="add_to_cart" id="add_to_cart_btn">Add to Cart</button>
        </form>
    </div>
    
    <!-- Cart -->
    <div class="col-md-8">
        <h3>Cart</h3>
        <?php if (empty($_SESSION['cart'])): ?>
            <p>Cart is empty</p>
        <?php else: ?>
            <table border="1" width="100%">
                <tr>
                    <th>Product</th>
                    <th>Price</th>
                    <th>Quantity</th>
                    <th>Subtotal</th>
                    <th>Action</th>
                </tr>
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
                                <button type="submit" name="remove_item">Remove</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <td colspan="3" align="right"><strong>Total:</strong></td>
                    <td colspan="2"><strong>$<?php echo number_format($total, 2); ?></strong></td>
                </tr>
            </table>
            
            <div style="margin-top: 15px;">
                <form method="POST" action="" id="transactionForm">
                    <?php if (isset($_SESSION['member']) && $_SESSION['member'] && $_SESSION['member']['points'] > 0): ?>
                    <div style="margin-bottom: 15px; padding: 10px; background-color: #f5f5f5; border: 1px solid #ddd;">
                        <label style="margin-right: 10px;">
                            <input type="checkbox" name="use_points" id="use_points" checked> 
                            Use all available points for discount (<?php echo number_format($_SESSION['member']['points'], 2); ?> points = $<?php echo number_format($_SESSION['member']['points'] * $POINTS_VALUE, 2); ?>)
                        </label>
                    </div>
                    <?php endif; ?>
                    
                    <div>
                        <button type="submit" name="process_transaction">Process Transaction</button>
                        <button type="submit" name="clear_cart" style="margin-left: 10px;">Clear Cart</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function updatePrice() {
    const select = document.querySelector('select[name="product_id"]');
    if (select.selectedIndex === 0) {
        document.getElementById('price').value = '';
        document.getElementById('stock').value = '';
        document.getElementById('product_name').value = '';
        document.getElementById('subtotal').value = '';
        document.getElementById('add_to_cart_btn').disabled = true;
        return;
    }
    
    const price = select.options[select.selectedIndex].getAttribute('data-price');
    const name = select.options[select.selectedIndex].getAttribute('data-name');
    const stock = select.options[select.selectedIndex].getAttribute('data-stock');
    
    document.getElementById('price').value = price;
    document.getElementById('stock').value = stock;
    document.getElementById('product_name').value = name;
    document.getElementById('add_to_cart_btn').disabled = false;
    
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

// Initialize form on page load
window.onload = function() {
    updatePrice();
};
</script>

<!-- Clear floating elements before the footer -->
<div style="clear: both; margin-bottom: 40px;"></div>

<?php require_once '../../include/footer.php'; ?>