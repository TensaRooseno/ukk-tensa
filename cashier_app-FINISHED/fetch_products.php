<?php
// Include database connection
require_once 'includes/db.php';

// Set response header to JSON
header('Content-Type: application/json');

try {
    // Fetch basic product information needed for the transaction interface
    $stmt = $conn->query("SELECT product_id, name, image_path FROM products");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return products as JSON
    echo json_encode($products);
} catch (Exception $e) {
    // Return error message if query fails
    echo json_encode(['error' => 'Failed to fetch products: ' . $e->getMessage()]);
}
?>