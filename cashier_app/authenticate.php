<?php
// Start session and include database connection
session_start();
require_once 'includes/db.php';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Prepare and execute query to find user
    $stmt = $conn->prepare("SELECT user_id, username, password, role_id FROM users WHERE username = :username");
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verify user exists and password matches
    if ($user && password_verify($password, $user['password'])) {
        // Set session variables on successful login
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role_id'] = $user['role_id'];
        
        // Redirect based on user role
        header("Location: " . ($user['role_id'] == 1 ? "admin_dashboard.php" : "cashier_dashboard.php"));
        exit;
    }
}

// If authentication fails, redirect back to login
header("Location: index.php");
exit;
?>