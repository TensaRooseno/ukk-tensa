<?php
// Prevent any output before headers
ob_start();

// Set response header to JSON
header('Content-Type: application/json');

// Start session and include database connection
session_start();
require_once 'includes/db.php';

// Authentication check - Only cashier can access this endpoint
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['phone_number'])) {
    $phone = $_POST['phone_number'];
    
    try {
        // Look up member by phone number
        $stmt = $conn->prepare("SELECT id, points FROM members WHERE phone_number = :phone");
        $stmt->execute(['phone' => $phone]);
        $member = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($member) {
            // Return existing member details
            echo json_encode([
                'exists' => true,
                'points' => $member['points']
            ]);
        } else {
            // Indicate new member registration needed
            echo json_encode([
                'exists' => false,
                'points' => 0
            ]);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => 'Database error']);
    }
} else {
    echo json_encode(['error' => 'Invalid request']);
}

// Clear any buffered output
ob_end_flush();