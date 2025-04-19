<?php
// Start session and include database connection
session_start();
require_once 'includes/db.php';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // Basic validation for empty fields only
    if (empty($username) || empty($password)) {
        $_SESSION['login_error'] = 'Please fill in all fields';
        header("Location: index.php");
        exit;
    }

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
    } else {
        $_SESSION['login_error'] = 'Invalid username or password';
        header("Location: index.php");
        exit;
    }
}

// If we get here, redirect to login page
$_SESSION['login_error'] = 'Please login to continue';
header("Location: index.php");
exit;
?>