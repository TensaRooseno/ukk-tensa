<?php
session_start();
require_once 'includes/db.php';

if ($_POST['action'] == 'add') {
    $product_id = $_POST['product_id'];
    $stmt = $conn->prepare("SELECT * FROM products WHERE product_id = :product_id");
    $stmt->execute(['product_id' => $product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($product && $product['stock_quantity'] > 0) {
        echo json_encode(['product_id' => $product['product_id'], 'name' => $product['name'], 'price' => $product['price']]);
    } else {
        echo json_encode(['error' => 'Product not found or out of stock']);
    }
} elseif ($_POST['action'] == 'process') {
    $items = $_POST['items'];
    $discount = floatval($_POST['discount']);
    $amount_paid = floatval($_POST['amount_paid']);
    $cashier_id = $_SESSION['user_id'];

    $conn->beginTransaction();
    try {
        // Temporarily insert with zero values until calculated later
        $stmt = $conn->prepare("INSERT INTO transactions (date_time, total_amount, discount_amount, amount_paid, change_given, cashier_id)
                                VALUES (NOW(), 0, :discount, 0, 0, :cashier_id)");
        $stmt->execute(['discount' => $discount, 'cashier_id' => $cashier_id]);
        $transaction_id = $conn->lastInsertId();
        $total_amount = 0;

        foreach ($items as $item) {
            $stmt = $conn->prepare("SELECT stock_quantity, price FROM products WHERE product_id = :product_id");
            $stmt->execute(['product_id' => $item['product_id']]);
            $prod = $stmt->fetch();
            if ($prod['stock_quantity'] < $item['quantity']) {
                throw new Exception("Insufficient stock for " . $item['name']);
            }
            $subtotal = $item['quantity'] * $prod['price'];
            $total_amount += $subtotal;

            // Insert detail
            $stmt = $conn->prepare("INSERT INTO transaction_details (transaction_id, product_id, quantity, price_per_unit)
                                    VALUES (:tid, :pid, :qty, :price)");
            $stmt->execute([
                'tid' => $transaction_id,
                'pid' => $item['product_id'],
                'qty' => $item['quantity'],
                'price' => $prod['price']
            ]);

            // Update stock
            $stmt = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity - :qty WHERE product_id = :pid");
            $stmt->execute(['qty' => $item['quantity'], 'pid' => $item['product_id']]);
        }

        $total_after_discount = $total_amount - $discount;
        $change_given = $amount_paid - $total_after_discount;

        if ($change_given < 0) {
            throw new Exception("Amount paid is less than total after discount.");
        }

        // Final update of transaction
        $stmt = $conn->prepare("UPDATE transactions
                                SET total_amount = :total, amount_paid = :paid, change_given = :change
                                WHERE transaction_id = :tid");
        $stmt->execute([
            'total' => $total_after_discount,
            'paid' => $amount_paid,
            'change' => $change_given,
            'tid' => $transaction_id
        ]);

        $conn->commit();
        echo json_encode([
            'message' => 'Transaction processed successfully',
            'transaction_id' => $transaction_id
        ]);
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>
