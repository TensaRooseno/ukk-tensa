<?php
session_start();
require_once 'includes/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: index.php");
    exit;
}
if (isset($_GET['id'])) {
    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = :id");
    $stmt->execute(['id' => $_GET['id']]);
}
header("Location: user_management.php");
exit;
?>