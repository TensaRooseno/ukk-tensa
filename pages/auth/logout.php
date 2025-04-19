<?php
require_once '../../include/config.php';

// Destroy session and redirect to login
session_destroy();
header("Location: login.php");
exit;
?>