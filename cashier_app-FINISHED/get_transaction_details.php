<?php
session_start();
require_once 'includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Check if transaction_id is provided
if (!isset($_GET['transaction_id'])) {
    echo json_encode(['error' => 'No transaction ID provided']);
    exit;
}

try {
    $transaction_id = $_GET['transaction_id'];
    
    // Add logging
    error_log("Processing transaction ID: " . $transaction_id);

    // Get transaction details with member and cashier information
    $stmt = $conn->prepare("
        SELECT 
            t.*,
            u.username as cashier_name,
            m.phone_number as member_phone,
            m.created_at as member_join_date,
            m.points as current_points,
            CASE WHEN m.id IS NOT NULL THEN 1 ELSE 0 END as is_member
        FROM transactions t
        JOIN users u ON t.cashier_id = u.user_id
        LEFT JOIN members m ON t.member_id = m.id
        WHERE t.transaction_id = :transaction_id
    ");
    $stmt->execute(['transaction_id' => $transaction_id]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$transaction) {
        echo json_encode(['error' => 'Transaction not found']);
        exit;
    }

    // Get transaction items
    $stmt = $conn->prepare("
        SELECT 
            p.name as product_name,
            td.quantity,
            td.price_per_unit as price
        FROM transaction_details td
        JOIN products p ON td.product_id = p.product_id
        WHERE td.transaction_id = :transaction_id
    ");
    $stmt->execute(['transaction_id' => $transaction_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Log the items array before mapping
    error_log("Items before mapping: " . print_r($items, true));

    // Format the response
    $transaction['items'] = $items;

    // Log the final response
    error_log("Final response: " . print_r($transaction, true));

    echo json_encode($transaction);
} catch (PDOException $e) {
    error_log("Database error in get_transaction_details.php: " . $e->getMessage());
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit;
}
?>