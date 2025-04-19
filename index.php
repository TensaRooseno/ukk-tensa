<?php
require_once 'include/config.php';

// Redirect to login if not logged in
if (!isLoggedIn()) {
    header("Location: pages/auth/login.php");
    exit;
}

// Simple redirect to the appropriate dashboard based on role
if (hasRole('admin')) {
    header("Location: pages/admin/products.php");
} else {
    header("Location: pages/cashier/pos.php");
}
exit;
?>