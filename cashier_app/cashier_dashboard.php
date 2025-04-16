<?php
// Start session and include database connection
session_start();
require_once 'includes/db.php';

// Authentication check - Only cashier can access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    header("Location: index.php");
    exit;
}

// Create members table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS members (
    id INT PRIMARY KEY AUTO_INCREMENT,
    phone_number VARCHAR(15) UNIQUE,
    points DECIMAL(10,2) DEFAULT 0.00,
    first_purchase_date DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Add member fields to transactions if they don't exist
$conn->query("ALTER TABLE transactions 
    ADD COLUMN IF NOT EXISTS member_id INT NULL,
    ADD COLUMN IF NOT EXISTS points_used DECIMAL(10,2) DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS points_earned DECIMAL(10,2) DEFAULT 0.00,
    ADD FOREIGN KEY IF NOT EXISTS (member_id) REFERENCES members(id)
");
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
        #memberInfo {
            display: none;
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
                        <!-- Member Section -->
                        <div class="mb-3">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="isMember">
                                <label class="form-check-label" for="isMember">
                                    Customer is a Member
                                </label>
                            </div>
                            <div id="memberInfo">
                                <div class="mb-2">
                                    <label for="phoneNumber" class="form-label">Phone Number</label>
                                    <input type="tel" id="phoneNumber" class="form-control" placeholder="Enter phone number">
                                </div>
                                <div id="memberDetails" class="alert alert-info d-none">
                                    <!-- Member details will be shown here -->
                                </div>
                                <div class="form-check mb-2 d-none" id="usePointsCheck">
                                    <input class="form-check-input" type="checkbox" id="usePoints">
                                    <label class="form-check-label" for="usePoints">
                                        Use available points as discount
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="discount" class="form-label">Additional Discount</label>
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
                            Subtotal: <span id="subtotalAmount">$0.00</span>
                        </div>
                        <div class="total-section">
                            Points Discount: <span id="pointsDiscount">$0.00</span>
                        </div>
                        <div class="total-section">
                            Additional Discount: <span id="additionalDiscount">$0.00</span>
                        </div>
                        <div class="total-section">
                            Final Total: <span id="totalAmount">$0.00</span>
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
            let transactionItems = [];
            let memberPoints = 0;
            let isFirstPurchase = true;

            // Toggle member info section
            $('#isMember').change(function() {
                $('#memberInfo').toggle(this.checked);
                if (!this.checked) {
                    $('#phoneNumber').val('');
                    $('#memberDetails').addClass('d-none');
                    $('#usePointsCheck').addClass('d-none');
                    $('#usePoints').prop('checked', false);
                }
                updateTotals();
            });

            // Phone number lookup
            $('#phoneNumber').on('blur', function() {
                const phone = $(this).val();
                if (phone) {
                    $.post('check_member.php', { phone_number: phone }, function(response) {
                        try {
                            const data = typeof response === 'string' ? JSON.parse(response) : response;
                            if (data.exists) {
                                memberPoints = parseFloat(data.points);
                                isFirstPurchase = false;
                                $('#memberDetails').removeClass('d-none')
                                    .html(`Available Points: $${memberPoints.toFixed(2)}`);
                                if (memberPoints > 0) {
                                    $('#usePointsCheck').removeClass('d-none');
                                }
                            } else {
                                memberPoints = 0;
                                isFirstPurchase = true;
                                $('#memberDetails').removeClass('d-none')
                                    .html('New member will be registered with first purchase');
                                $('#usePointsCheck').addClass('d-none');
                                $('#usePoints').prop('checked', false);
                            }
                            updateTotals();
                        } catch (e) {
                            console.error('Failed to parse response:', e);
                            alert('Error checking member status');
                        }
                    }).fail(function(jqXHR, textStatus, errorThrown) {
                        console.error('AJAX error:', textStatus, errorThrown);
                        alert('Failed to check member status');
                    });
                }
            });

            // Points usage toggle
            $('#usePoints').change(function() {
                updateTotals();
            });

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
                        try {
                            const data = typeof response === 'string' ? JSON.parse(response) : response;
                            if (data.error) {
                                alert(data.error);
                            } else {
                                transactionItems.push({ ...data, quantity: 1 });
                                renderItems();
                            }
                        } catch (e) {
                            console.error('Failed to parse response:', e);
                            alert('Error adding item to transaction');
                        }
                    }).fail(function(jqXHR, textStatus, errorThrown) {
                        console.error('AJAX error:', textStatus, errorThrown);
                        alert('Failed to add item to transaction');
                    });
                }
            });

            // Process transaction on button click
            $('#processTransaction').click(function() {
                const isMember = $('#isMember').is(':checked');
                const phoneNumber = $('#phoneNumber').val();
                const usePoints = $('#usePoints').is(':checked');
                const additionalDiscount = parseFloat($('#discount').val()) || 0;
                const amountPaid = parseFloat($('#amountPaid').val()) || 0;
                const subtotal = calculateSubtotal();
                const pointsDiscount = usePoints ? Math.min(memberPoints, subtotal) : 0;
                const finalTotal = subtotal - pointsDiscount - additionalDiscount;

                if (amountPaid < finalTotal) {
                    alert('Insufficient payment amount');
                    return;
                }

                if (isMember && !phoneNumber) {
                    alert('Please enter phone number for member');
                    return;
                }

                if (transactionItems.length === 0) {
                    alert('Please add items to the transaction');
                    return;
                }

                $.ajax({
                    url: 'transaction.php',
                    method: 'POST',
                    data: { 
                        action: 'process', 
                        items: transactionItems, 
                        is_member: isMember,
                        phone_number: phoneNumber,
                        use_points: usePoints,
                        points_used: pointsDiscount,
                        additional_discount: additionalDiscount,
                        amount_paid: amountPaid,
                        subtotal: subtotal
                    },
                    success: function(response) {
                        try {
                            if (typeof response === 'string') {
                                response = JSON.parse(response);
                            }
                            
                            if (response.error) {
                                alert('Error: ' + response.error);
                            } else {
                                alert('Transaction processed successfully!' + 
                                    (isMember ? '\nPoints earned: $' + response.points_earned.toFixed(2) : ''));
                                // Reset transaction after successful processing
                                transactionItems = [];
                                $('#isMember').prop('checked', false).trigger('change');
                                $('#discount').val('');
                                $('#amountPaid').val('');
                                renderItems();
                            }
                        } catch (e) {
                            console.error('JSON parsing error:', e);
                            alert('Error processing transaction. Please try again.');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error:', status, error);
                        alert('Failed to process transaction. Please try again.');
                    }
                });
            });

            // Calculate subtotal
            function calculateSubtotal() {
                return transactionItems.reduce((total, item) => {
                    return total + (parseFloat(item.price) * item.quantity);
                }, 0);
            }

            // Update all totals
            function updateTotals() {
                const subtotal = calculateSubtotal();
                const usePoints = $('#usePoints').is(':checked');
                const pointsDiscount = usePoints ? Math.min(memberPoints, subtotal) : 0;
                const additionalDiscount = parseFloat($('#discount').val()) || 0;
                const finalTotal = Math.max(0, subtotal - pointsDiscount - additionalDiscount);
                const amountPaid = parseFloat($('#amountPaid').val()) || 0;
                const change = amountPaid - finalTotal;

                $('#subtotalAmount').text(`$${subtotal.toFixed(2)}`);
                $('#pointsDiscount').text(`$${pointsDiscount.toFixed(2)}`);
                $('#additionalDiscount').text(`$${additionalDiscount.toFixed(2)}`);
                $('#totalAmount').text(`$${finalTotal.toFixed(2)}`);
                $('#changeGiven').text(change >= 0 ? 
                    `$${change.toFixed(2)}` : 'Insufficient amount');
            }

            // Handle discount and amount paid changes
            $(document).on('input', '#discount, #amountPaid', updateTotals);

            // Render transaction items table
            function renderItems() {
                const $tbody = $('#transactionItems');
                $tbody.empty();

                transactionItems.forEach((item, index) => {
                    const price = parseFloat(item.price) || 0;
                    const subtotal = price * item.quantity;

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
                                <input type="number" class="form-control quantity-input" data-index="${index}" 
                                       value="${item.quantity}" min="1" style="width: 80px">
                            </td>
                            <td>${subtotal.toFixed(2)}</td>
                            <td>
                                <button class="btn btn-danger btn-sm" onclick="removeItem(${index})">Remove</button>
                            </td>
                        </tr>
                    `);
                });

                updateTotals();
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