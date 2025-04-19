<?php
// Start session and include database connection
session_start();
require_once 'includes/db.php';

// Check if user is authenticated as cashier
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    echo json_encode(['error' => 'Unauthorized access'], JSON_THROW_ON_ERROR);
    exit;
}

// Set response header to JSON
header('Content-Type: application/json');

// Handle different transaction actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';

    // Handle adding product to transaction
    if ($action == 'add') {
        $product_id = $_POST['product_id'] ?? 0;
        
        // Fetch product details for the transaction
        $stmt = $conn->prepare("SELECT product_id, name, price, image_path FROM products WHERE product_id = :id");
        $stmt->execute(['id' => $product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product) {
            echo json_encode($product, JSON_THROW_ON_ERROR);
        } else {
            echo json_encode(['error' => 'Product not found'], JSON_THROW_ON_ERROR);
        }
    }
    // Handle processing complete transaction
    elseif ($action == 'process') {
        try {
            // Start transaction to ensure data consistency
            $conn->beginTransaction();
            
            // Get and validate transaction data
            $items = isset($_POST['items']) && is_array($_POST['items']) ? $_POST['items'] : [];
            if (empty($items)) {
                throw new Exception('No items in transaction');
            }

            $is_member = filter_var($_POST['is_member'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $phone_number = filter_var($_POST['phone_number'] ?? '', FILTER_SANITIZE_STRING);
            $use_points = filter_var($_POST['use_points'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $points_used = max(0, filter_var($_POST['points_used'] ?? 0, FILTER_VALIDATE_FLOAT));
            $additional_discount = max(0, filter_var($_POST['additional_discount'] ?? 0, FILTER_VALIDATE_FLOAT));
            $amount_paid = max(0, filter_var($_POST['amount_paid'] ?? 0, FILTER_VALIDATE_FLOAT));
            $subtotal = max(0, filter_var($_POST['subtotal'] ?? 0, FILTER_VALIDATE_FLOAT));
            
            // Validate total amount
            if ($subtotal <= 0) {
                throw new Exception('Invalid transaction amount');
            }
            
            // Calculate final amount with validation
            $final_amount = $subtotal;
            if ($points_used > 0 && $is_member) {
                // Verify member has enough points
                $stmt = $conn->prepare("SELECT points FROM members WHERE phone_number = ?");
                $stmt->execute([$phone_number]);
                $member_points = $stmt->fetchColumn();
                
                if ($points_used > $member_points) {
                    throw new Exception('Insufficient points balance');
                }
                $final_amount -= $points_used;
            }
            
            if ($additional_discount > 0) {
                if ($additional_discount > $final_amount) {
                    throw new Exception('Discount cannot exceed total amount');
                }
                $final_amount -= $additional_discount;
            }
            
            // Ensure final amount is not negative
            $final_amount = max(0, $final_amount);
            
            // Validate payment amount
            if ($amount_paid < $final_amount) {
                throw new Exception('Insufficient payment amount');
            }
            
            $member_id = null;
            $points_earned = 0;

            // Handle member processing
            if ($is_member) {
                // Check if member exists
                $stmt = $conn->prepare("SELECT id, points FROM members WHERE phone_number = :phone");
                $stmt->execute(['phone' => $phone_number]);
                $member = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($member) {
                    $member_id = $member['id'];
                    // Calculate new points balance
                    $points_balance = $member['points'] - $points_used;
                    
                    // Only earn points if they paid some amount (not just using points)
                    $points_earned = ($final_amount > 0) ? ($subtotal * 0.01) : 0;
                    $points_balance += $points_earned;

                    // Update member points
                    $stmt = $conn->prepare("UPDATE members SET points = :points WHERE id = :id");
                    $stmt->execute([
                        'points' => $points_balance,
                        'id' => $member_id
                    ]);
                } else {
                    // Register new member
                    $stmt = $conn->prepare("INSERT INTO members (phone_number, points, first_purchase_date) VALUES (:phone, :points, NOW())");
                    $stmt->execute([
                        'phone' => $phone_number,
                        'points' => $subtotal * 0.01 // 1% points from first purchase
                    ]);
                    $member_id = $conn->lastInsertId();
                    $points_earned = $subtotal * 0.01;
                }
            }
            
            // Insert transaction record
            $stmt = $conn->prepare("INSERT INTO transactions (cashier_id, date_time, total_amount, discount_amount, member_id, points_used, points_earned) 
                                  VALUES (:cashier_id, NOW(), :total_amount, :discount_amount, :member_id, :points_used, :points_earned)");
            $stmt->execute([
                'cashier_id' => $_SESSION['user_id'],
                'total_amount' => $final_amount,
                'discount_amount' => $additional_discount + $points_used,
                'member_id' => $member_id,
                'points_used' => $points_used,
                'points_earned' => $points_earned
            ]);
            
            $transaction_id = $conn->lastInsertId();
            
            // Insert transaction items and update stock
            $stmt = $conn->prepare("INSERT INTO transaction_details (transaction_id, product_id, quantity, price_per_unit) 
                                  VALUES (:transaction_id, :product_id, :quantity, :price)");
            
            $updateStock = $conn->prepare("UPDATE products 
                                         SET stock_quantity = stock_quantity - :quantity 
                                         WHERE product_id = :product_id");
            
            foreach ($items as $item) {
                // Insert item details
                $stmt->execute([
                    'transaction_id' => $transaction_id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price']
                ]);
                
                // Update product stock
                $updateStock->execute([
                    'quantity' => $item['quantity'],
                    'product_id' => $item['product_id']
                ]);
            }
            
            // Commit all changes
            $conn->commit();
            echo json_encode([
                'success' => true,
                'points_earned' => $points_earned
            ], JSON_THROW_ON_ERROR);
            
        } catch (Exception $e) {
            // Rollback changes on error
            $conn->rollBack();
            echo json_encode(['error' => $e->getMessage()], JSON_THROW_ON_ERROR);
        }
    }
    // Handle invalid action
    else {
        echo json_encode(['error' => 'Invalid action'], JSON_THROW_ON_ERROR);
    }
}
?>
