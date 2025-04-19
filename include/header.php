<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Cashier App</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Bootstrap JS (for alerts, modals etc.) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        body { 
            font-family: 'Segoe UI', Arial, sans-serif; 
            margin: 0; 
            padding: 0; 
            background-color: #f8f9fa; 
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        main {
            padding: 20px;
            flex: 1;
        }
        .container-fluid {
            padding: 0 20px;
        }
        label { 
            display: block; 
            margin: 10px 0 5px; 
            font-weight: 500; 
        }
        input, select { 
            padding: 8px; 
            border: 1px solid #ddd; 
            border-radius: 4px; 
            width: 100%; 
        }
        /* Navigation bar */
        .nav { 
            background: #343a40; 
            padding: 12px 20px; 
            margin-bottom: 25px; 
            display: flex; 
            justify-content: space-between;
            color: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .nav-menu { 
            display: flex; 
            gap: 5px;
        }
        .nav-menu a { 
            text-decoration: none;
            color: rgba(255,255,255,0.85);
            padding: 8px 12px;
            border-radius: 4px;
            transition: all 0.2s ease;
        }
        .nav-menu a:hover {
            background-color: rgba(255,255,255,0.1);
            color: white;
        }
        .user-info {
            display: flex;
            align-items: center;
        }
        .user-info span {
            margin-right: 15px;
        }
        .user-info a {
            color: rgba(255,255,255,0.85);
            text-decoration: none;
            padding: 5px 12px;
            border-radius: 4px;
            background-color: rgba(255,255,255,0.1);
            transition: all 0.2s ease;
        }
        .user-info a:hover {
            background-color: rgba(255,255,255,0.2);
            color: white;
        }
        /* Card styling */
        .card {
            background-color: white;
            border-radius: 6px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            border: none;
            height: 100%;
        }
        .card-header {
            padding: 15px 20px;
            background-color: #f8f9fa;
            border-bottom: 1px solid rgba(0,0,0,0.1);
            font-weight: 500;
        }
        .card-body {
            padding: 20px;
        }
        /* Table styling */
        .table-responsive {
            margin-bottom: 0;
        }
        .table {
            margin-bottom: 0;
        }
        .table th {
            background-color: #f8f9fa;
            font-weight: 500;
        }
        /* Button styling */
        .btn {
            border-radius: 4px;
            padding: 8px 16px;
            font-weight: 500;
            transition: all 0.2s;
        }
        .btn-sm {
            padding: 4px 8px;
        }
        /* Image styling */
        .product-img {
            border-radius: 4px;
            border: 1px solid #ddd;
            padding: 4px;
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            object-fit: contain;
        }
        .product-img-container {
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 15px 0;
            min-height: 120px;
        }
        .img-thumbnail {
            padding: 5px;
            background-color: #fff;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            max-width: 100%;
            height: auto;
        }
        /* Fix input group alignment for dollar sign */
        .input-group {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
        }
        .input-group-text {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.375rem 0.75rem;
            font-size: 1rem;
            font-weight: 400;
            line-height: 1.5;
            text-align: center;
            white-space: nowrap;
            background-color: #e9ecef;
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
            height: 38px; /* Match the height of inputs */
            margin: 0;
            width: auto;
        }
        .input-group > .form-control {
            position: relative;
            flex: 1 1 auto;
            min-width: 0;
            margin: 0;
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
            height: 38px; /* Match the height of input-group-text */
            padding: 0.375rem 0.75rem;
        }
        .input-group > .form-control:focus {
            z-index: 3;
        }
        /* Fix for number inputs in particular */
        .input-group input[type="number"] {
            height: 38px; /* Ensure consistent height */
            padding: 0.375rem 0.75rem;
            margin-bottom: 0; /* Reset margin from general input styling */
        }
        /* Page title styling */
        .page-title {
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }
        /* Grid fixes */
        .row {
            --bs-gutter-x: 1.5rem;
            --bs-gutter-y: 0;
            display: flex;
            flex-wrap: wrap;
            margin-top: 0;
            margin-right: calc(var(--bs-gutter-x) * -0.5);
            margin-left: calc(var(--bs-gutter-x) * -0.5);
        }
        .row > * {
            box-sizing: border-box;
            flex-shrink: 0;
            width: 100%;
            max-width: 100%;
            padding-right: calc(var(--bs-gutter-x) * 0.5);
            padding-left: calc(var(--bs-gutter-x) * 0.5);
            margin-top: var(--bs-gutter-y);
        }
        .col-md-4 {
            flex: 0 0 auto;
            width: 33.33333333%;
        }
        .col-md-8 {
            flex: 0 0 auto;
            width: 66.66666667%;
        }
        .col-md-12 {
            flex: 0 0 auto;
            width: 100%;
        }
        /* Make content take up full height to push footer down */
        .content-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        /* Footer styling */
        footer {
            margin-top: auto;
            padding: 15px 20px;
            background-color: #f8f9fa;
            border-top: 1px solid #dee2e6;
            text-align: center;
            font-size: 12px;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="nav">
        <div class="nav-menu">
            <?php if (isset($_SESSION['role']) && hasRole('admin')): ?>
                <!-- Admin Navigation -->
                <a href="/ukk/pages/admin/dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
                <a href="/ukk/pages/admin/users.php"><i class="fas fa-users me-2"></i>Users</a>
                <a href="/ukk/pages/admin/products.php"><i class="fas fa-box me-2"></i>Products</a>
                <a href="/ukk/pages/admin/members.php"><i class="fas fa-id-card me-2"></i>Members</a>
                <a href="/ukk/pages/admin/transactions.php"><i class="fas fa-exchange-alt me-2"></i>Transactions</a>
            <?php else: ?>
                <!-- Cashier Navigation -->
                <a href="/ukk/pages/cashier/pos.php"><i class="fas fa-cash-register me-2"></i>Point of Sale</a>
                <a href="/ukk/pages/cashier/transactions.php"><i class="fas fa-receipt me-2"></i>My Transactions</a>
                <a href="/ukk/pages/cashier/products.php"><i class="fas fa-box me-2"></i>Products</a>
            <?php endif; ?>
        </div>
        
        <div class="user-info">
            <?php if (isset($_SESSION['user_id'])): ?>
                <span>
                    <i class="fas fa-user-circle me-1"></i>
                    <strong><?php echo ucfirst($_SESSION['role']); ?>:</strong> 
                    <?php echo $_SESSION['username']; ?>
                </span>
                <a href="/ukk/pages/auth/logout.php"><i class="fas fa-sign-out-alt me-1"></i>Logout</a>
            <?php endif; ?>
        </div>
    </div>
    
    <main class="container-fluid">
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo ($_SESSION['message_type'] == 'success') ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
            <?php 
            echo $_SESSION['message']; 
            unset($_SESSION['message']);
            unset($_SESSION['message_type']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>