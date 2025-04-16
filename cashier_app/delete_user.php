<?php
// Start session and include database connection
session_start();
require_once 'includes/db.php';

// Security check - Only admin can delete users
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: index.php");
    exit;
}

// Check if user ID was provided
if (isset($_GET['id'])) {
    $user_id = $_GET['id'];
    
    // Prevent admin from deleting their own account
    if ($user_id == $_SESSION['user_id']) {
        header("Location: user_management.php");
        exit;
    }
    
    try {
        // Delete user from database
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = :id");
        $stmt->execute(['id' => $user_id]);
        
        // Redirect back to user management page
        header("Location: user_management.php");
        exit;
    } catch (PDOException $e) {
        // Handle deletion error (e.g., if user has related records)
        die("Delete failed: " . $e->getMessage());
    }
} else {
    // Redirect if no user ID provided
    header("Location: user_management.php");
    exit;
}