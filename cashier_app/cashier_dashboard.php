<?php
// Start session and include database connection
session_start();
require_once 'includes/db.php';

// Authentication check - Only cashier can access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Cashier Dashboard - Cashier App</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Include required CSS and JS files -->
    <link href="/ukk/cashier_app/assets/bootstrap.min.css" rel="stylesheet">
    <script src="/ukk/cashier_app/assets/bootstrap.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Cashier dashboard styling -->
    <style>
        .sidebar {
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            width: 200px;
            padding-top: 20px;
            background-color: #28a745;
        }
        .sidebar .nav-link {
            color: #fff;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background-color: #218838;
        }
        .main-content {
            margin-left: 200px;
            padding: 20px;
        }
        /* Transaction table scrolling styles */
        .item-table {
            max-height: 400px;
            overflow-y: auto;
        }
        /* Totals section styling */
        .total-section {
            font-size: 1.5rem;
            font-weight: bold;
        }
        .change-section {
            font-size: 1.2rem;
            font-weight: bold;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <!-- Cashier Sidebar Navigation -->
    <div class="sidebar">
        <h4 class="text-white text-center">Cashier Dashboard</h4>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link active" href="cashier_dashboard.php">Transactions</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="view_products.php">View Products</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="transaction_history.php">Transaction History</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="logout.php">Logout</a>
            </li>
        </ul>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
        <h2>Process Transactions</h2>
        <p>Select products from the dropdown to add items.</p>

        <!-- Product Selection Card -->
        <div class="card mb-3">
            <div class="card-body">
                <div class="mb-3">
                    <label for="productSelect" class="form-label">Select Product</label>
                    <select id="productSelect" class="form-control">
                        <!-- Options will be dynamically populated via AJAX -->
                    </select>
                </div>
                <button id="addItem" class="btn btn-primary">Add Item</button>
            </div>
        </div>

        <!-- Transaction Items Table Card -->
        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title">Transaction Items</h5>
                <div class="item-table">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Name</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th>Subtotal</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="transactionItems">
                            <!-- Items will be dynamically added here -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Payment Processing Card -->
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <!-- Payment Input Fields -->
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="discount" class="form-label">Discount</label>
                            <input type="number" id="discount" class="form-control" placeholder="Enter discount">
                        </div>
                        <div class="mb-3">
                            <label for="amountPaid" class="form-label">Amount Paid</label>
                            <input type="number" id="amountPaid" class="form-control" placeholder="Enter amount paid">
                        </div>
                    </div>
                    <!-- Total and Change Display -->
                    <div class="col-md-6">
                        <div class="total-section">
                            Total: <span id="totalAmount">$0.00</span>
                        </div>
                        <div class="change-section">
                            Change: <span id="changeGiven">$0.00</span>
                        </div>
                        <button id="processTransaction" class="btn btn-success mt-3">Process Transaction</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Transaction Processing JavaScript -->
    <script>
        $(document).ready(function() {
            // Initialize transaction items array
            let transactionItems = [];

            // Fetch and populate product dropdown on page load
            $.get('fetch_products.php', function(products) {
                const productSelect = $('#productSelect');
                products.forEach(product => {
                    productSelect.append(new Option(product.name, product.product_id));
                });
            });

            // Handle adding items to transaction
            $('#addItem').click(function() {
                const productId = $('#productSelect').val();
                if (!productId) return;

                // Check if product already exists in transaction
                const existingItemIndex = transactionItems.findIndex(item => item.product_id == productId);
                if (existingItemIndex !== -1) {
                    // Increment quantity if product exists
                    transactionItems[existingItemIndex].quantity++;
                    renderItems();
                } else {
                    // Fetch and add new product if it doesn't exist
                    $.post('transaction.php', { action: 'add', product_id: productId }, function(response) {
                        const data = JSON.parse(response);
                        if (data.error) {
                            alert(data.error);
                        } else {
                            transactionItems.push({ ...data, quantity: 1 });
                            renderItems();
                        }
                    });
                }
            });

            // Process transaction on button click
            $('#processTransaction').click(function() {
                const discount = parseFloat($('#discount').val()) || 0;
                const amountPaid = parseFloat($('#amountPaid').val()) || 0;

                $.post('transaction.php', { 
                    action: 'process', 
                    items: transactionItems, 
                    discount, 
                    amount_paid: amountPaid 
                }, function(response) {
                    const data = JSON.parse(response);
                    if (data.error) {
                        alert(data.error);
                    } else {
                        alert('Transaction processed successfully!');
                        // Reset transaction after successful processing
                        transactionItems = [];
                        renderItems();
                    }
                });
            });

            // Update totals when discount or amount paid changes
            $(document).on('input', '#discount, #amountPaid', function() {
                const discount = parseFloat($('#discount').val()) || 0;
                const amountPaid = parseFloat($('#amountPaid').val()) || 0;

                let total = 0;
                transactionItems.forEach(item => {
                    const price = parseFloat(item.price) || 0;
                    total += price * item.quantity;
                });

                const totalAfterDiscount = total - discount;
                const changeGiven = amountPaid - totalAfterDiscount;

                // Update displayed totals
                $('#totalAmount').text(`$${totalAfterDiscount.toFixed(2)}`);
                $('#changeGiven').text(changeGiven >= 0 ? 
                    `$${changeGiven.toFixed(2)}` : 'Insufficient amount');
            });

            // Render transaction items table
            function renderItems() {
                const $tbody = $('#transactionItems');
                $tbody.empty();
                let total = 0;

                transactionItems.forEach((item, index) => {
                    const price = parseFloat(item.price) || 0;
                    const subtotal = price * item.quantity;
                    total += subtotal;

                    $tbody.append(`
                        <tr>
                            <td>
                                ${item.image_path ? 
                                    `<img src="${item.image_path}" alt="${item.name}" class="img-thumbnail" style="max-width: 50px">` :
                                    '<span class="text-muted">No image</span>'}
                            </td>
                            <td>${item.name}</td>
                            <td>${price.toFixed(2)}</td>
                            <td>
                                <input type="number" class="form-control quantity-input" data-index="${index}" value="${item.quantity}" min="1" style="width: 80px">
                            </td>
                            <td>${subtotal.toFixed(2)}</td>
                            <td><button class="btn btn-danger btn-sm" onclick="removeItem(${index})">Remove</button></td>
                        </tr>
                    `);
                });

                // Update totals after rendering
                $('#totalAmount').text(`$${total.toFixed(2)}`);
                const discount = parseFloat($('#discount').val()) || 0;
                const amountPaid = parseFloat($('#amountPaid').val()) || 0;
                const totalAfterDiscount = total - discount;
                const changeGiven = amountPaid - totalAfterDiscount;
                $('#changeGiven').text(changeGiven >= 0 ? 
                    `$${changeGiven.toFixed(2)}` : 'Insufficient amount');
            }

            // Handle quantity changes
            $(document).on('input', '.quantity-input', function() {
                const index = $(this).data('index');
                const newQuantity = parseInt($(this).val()) || 1;
                transactionItems[index].quantity = newQuantity;
                renderItems();
            });

            // Remove item function (made global for button onclick access)
            window.removeItem = function(index) {
                transactionItems.splice(index, 1);
                renderItems();
            };
        });
    </script>
</body>
</html>