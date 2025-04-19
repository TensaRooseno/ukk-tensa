<?php
// Define database connection parameters
$host = 'localhost';      // MySQL server hostname
$dbname = 'cashier_app'; // Database name
$username = 'root';      // Database username
$password = '';          // Database password for XAMPP default setup

try {
    // Create PDO instance for database connection
    $conn = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            // Set error mode to throw exceptions
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            // Return associative arrays by default
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Use persistent connections for better performance
            PDO::ATTR_PERSISTENT => true,
            // Disable emulated prepared statements
            PDO::ATTR_EMULATE_PREPARES => false,
            // Ensure proper character encoding
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ]
    );
} catch (PDOException $e) {
    // Log error securely without exposing sensitive information
    error_log("Database connection error: " . $e->getMessage());
    die("A database error occurred. Please contact the system administrator.");
}
?>