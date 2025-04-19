<?php
// Database configuration for version 1 cashier app using the existing database
$host = "localhost";
$username = "root";
$password = "";
$database = "cashier_app"; // Using the existing database name

// Create database connection
$conn = mysqli_connect($host, $username, $password, $database);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Function to check user role - modified to be more flexible
function hasRole($role) {
    if (!isset($_SESSION['role'])) {
        return false;
    }
    
    // For admin role, be more flexible with matching
    if ($role == 'admin') {
        $adminRoles = ['admin', 'Admin', 'ADMIN', 'administrator', 'Administrator', '1'];
        return in_array($_SESSION['role'], $adminRoles);
    }
    
    // For other roles like 'cashier', still do a direct comparison
    return $_SESSION['role'] == $role;
}

// Function to redirect with message
function redirectWithMessage($url, $message, $type = 'success') {
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $type;
    header("Location: $url");
    exit;
}
?>