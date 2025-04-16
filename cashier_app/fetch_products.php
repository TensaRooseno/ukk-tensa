<?php
require_once 'includes/db.php';

header('Content-Type: application/json');

try {
    $stmt = $conn->query("SELECT product_id, name FROM products");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($products);
} catch (Exception $e) {
    echo json_encode(['error' => 'Failed to fetch products: ' . $e->getMessage()]);
}
?>