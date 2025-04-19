<?php
require_once '../../include/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Validate transaction ID
if (!isset($_POST['transaction_id']) || !is_numeric($_POST['transaction_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid transaction ID']);
    exit;
}

$transaction_id = (int)$_POST['transaction_id'];
$user_id = $_SESSION['user_id'];
$is_admin = hasRole('admin');

// Get transaction data
$query = "SELECT * FROM transactions WHERE transaction_id = $transaction_id";
if (!$is_admin) {
    // Cashiers can only view their own transactions
    $query .= " AND cashier_id = $user_id";
}

$result = mysqli_query($conn, $query);
if (!$result || mysqli_num_rows($result) === 0) {
    echo json_encode(['success' => false, 'message' => 'Transaction not found or you don\'t have permission to view it']);
    exit;
}

$transaction = mysqli_fetch_assoc($result);

// Get transaction items with product names
$query = "SELECT td.*, p.name as product_name 
          FROM transaction_details td
          JOIN products p ON td.product_id = p.product_id
          WHERE td.transaction_id = $transaction_id";
$result = mysqli_query($conn, $query);

$items = [];
while ($row = mysqli_fetch_assoc($result)) {
    $items[] = $row;
}

// Format date for better display
$transaction['date_time'] = date('M d, Y H:i:s', strtotime($transaction['date_time']));

// Return the data as JSON
echo json_encode([
    'success' => true,
    'transaction' => $transaction,
    'items' => $items
]);
?>