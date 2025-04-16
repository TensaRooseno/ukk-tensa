<?php
// Start session and include database connection
session_start();
require_once 'includes/db.php';

// Check if user is authenticated as cashier
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    echo json_encode(['error' => 'Unauthorized access']);
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
            echo json_encode($product);
        } else {
            echo json_encode(['error' => 'Product not found']);
        }
    }
    // Handle processing complete transaction
    elseif ($action == 'process') {
        try {
            // Start transaction to ensure data consistency
            $conn->beginTransaction();
            
            // Get transaction data
            $items = $_POST['items'] ?? [];
            $discount = floatval($_POST['discount'] ?? 0);
            $amount_paid = floatval($_POST['amount_paid'] ?? 0);
            
            // Calculate total amount
            $total_amount = 0;
            foreach ($items as $item) {
                $total_amount += floatval($item['price']) * intval($item['quantity']);
            }
            
            // Apply discount
            $final_amount = $total_amount - $discount;
            
            // Validate payment amount
            if ($amount_paid < $final_amount) {
                throw new Exception('Insufficient payment amount');
            }
            
            // Insert transaction record
            $stmt = $conn->prepare("INSERT INTO transactions (cashier_id, date_time, total_amount, discount_amount) 
                                  VALUES (:cashier_id, NOW(), :total_amount, :discount_amount)");
            $stmt->execute([
                'cashier_id' => $_SESSION['user_id'],
                'total_amount' => $final_amount,
                'discount_amount' => $discount
            ]);
            
            $transaction_id = $conn->lastInsertId();
            
            // Insert transaction items
            $stmt = $conn->prepare("INSERT INTO transaction_items (transaction_id, product_id, quantity, price) 
                                  VALUES (:transaction_id, :product_id, :quantity, :price)");
            
            // Update product stock
            $updateStock = $conn->prepare("UPDATE products 
                                         SET stock_quantity = stock_quantity - :quantity 
                                         WHERE product_id = :product_id");
            
            // Process each item in the transaction
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
            
            // Commit all changes if successful
            $conn->commit();
            echo json_encode(['success' => true]);
            
        } catch (Exception $e) {
            // Rollback changes if any error occurs
            $conn->rollBack();
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    // Handle invalid action
    else {
        echo json_encode(['error' => 'Invalid action']);
    }
}
?>
