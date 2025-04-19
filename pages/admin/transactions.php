<?php
require_once '../../include/config.php';

// Check if user is logged in and has admin role
if (!isLoggedIn() || !hasRole('admin')) {
    header('Location: ../auth/login.php');
    exit;
}

// Get all transactions
$query = "SELECT t.*, u.username, m.phone_number as member_phone 
          FROM transactions t 
          JOIN users u ON t.cashier_id = u.user_id 
          LEFT JOIN members m ON t.member_id = m.id
          ORDER BY t.date_time DESC";
$transactions = mysqli_query($conn, $query);

require_once '../../include/header.php';
?>

<div class="row mb-3">
    <div class="col-md-8">
        <h1 class="page-title"><i class="fas fa-exchange-alt me-2"></i>Transaction Management</h1>
    </div>
    <div class="col-md-4 text-end">
        <a href="export_transactions.php" class="btn btn-success">
            <i class="fas fa-file-excel me-2"></i>Export to Excel
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0">All Transactions</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Cashier</th>
                                <th>Date</th>
                                <th>Member</th>
                                <th>Total Amount</th>
                                <th>Discount</th>
                                <th>Final Amount</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($transactions) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($transactions)): 
                                    $finalAmount = $row['total_amount'] - $row['discount_amount'];
                                ?>
                                    <tr>
                                        <td><?php echo $row['transaction_id']; ?></td>
                                        <td><?php echo $row['username']; ?></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($row['date_time'])); ?></td>
                                        <td>
                                            <?php if ($row['member_phone']): ?>
                                                <span class="badge bg-info">
                                                    <i class="fas fa-user me-1"></i><?php echo $row['member_phone']; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Non-Member</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>$<?php echo number_format($row['total_amount'], 2); ?></td>
                                        <td>
                                            <?php if ($row['discount_amount'] > 0): ?>
                                                <span class="badge bg-success">$<?php echo number_format($row['discount_amount'], 2); ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">$0.00</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong>$<?php echo number_format($finalAmount, 2); ?></strong></td>
                                        <td>
                                            <div class="btn-group">
                                                <button onclick="showDetails(<?php echo $row['transaction_id']; ?>)" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-search me-1"></i>Details
                                                </button>
                                                <a href="generate_invoice.php?transaction_id=<?php echo $row['transaction_id']; ?>" class="btn btn-sm btn-success">
                                                    <i class="fas fa-file-pdf me-1"></i>Invoice
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center">No transactions found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal for transaction details -->
<div id="detailsModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); overflow-y: auto; z-index: 1000;">
    <div style="background-color: white; margin: 5% auto; padding: 20px; width: 90%; max-width: 800px; position: relative; border-radius: 5px; box-shadow: 0 4px 8px rgba(0,0,0,0.2);">
        <span style="position: absolute; top: 10px; right: 15px; cursor: pointer; font-size: 24px; font-weight: bold;" onclick="closeModal()">&times;</span>
        <h2 style="margin-top: 10px;"><i class="fas fa-receipt me-2"></i>Transaction Details</h2>
        <div id="transactionDetails" style="max-height: 70vh; overflow-y: auto; padding: 10px 0;">
            <div class="d-flex justify-content-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function showDetails(transactionId) {
    document.getElementById('detailsModal').style.display = 'block';
    document.body.style.overflow = 'hidden'; // Prevent body scrolling when modal is open
    document.getElementById('transactionDetails').innerHTML = `
        <div class="d-flex justify-content-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    `;
    
    // AJAX request to get transaction details
    const xhr = new XMLHttpRequest();
    xhr.open('POST', '../cashier/get_transaction_details.php', true);
    xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        if (this.status === 200) {
            try {
                const response = JSON.parse(this.responseText);
                if (response.success) {
                    let html = `<div class="card mb-3">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Transaction ID:</strong> ${response.transaction.transaction_id}</p>
                                    <p><strong>Date:</strong> ${response.transaction.date_time}</p>
                                    <p><strong>Cashier:</strong> ${response.transaction.username}</p>
                                </div>
                                <div class="col-md-6">`;
                    
                    // Add member information if available
                    if (response.member) {
                        const pointsBefore = (parseFloat(response.member.points) - parseFloat(response.transaction.points_earned) + parseFloat(response.transaction.points_used)).toFixed(2);
                        html += `<div class="alert alert-info">
                            <h5><i class="fas fa-user-circle me-2"></i>Member Information</h5>
                            <p><strong>Phone:</strong> ${response.member.phone_number}</p>
                            <p><strong>Points Before:</strong> ${pointsBefore}</p>
                            <p><strong>Points Used:</strong> ${response.transaction.points_used}</p>
                            <p><strong>Points Earned:</strong> ${response.transaction.points_earned}</p>
                            <p><strong>Current Points:</strong> ${response.member.points}</p>
                            <p><strong>Discount Applied:</strong> $${parseFloat(response.transaction.discount_amount).toFixed(2)}</p>
                        </div>`;
                    } else {
                        html += `<div class="alert alert-secondary">
                            <h5><i class="fas fa-user me-2"></i>Customer Type: Non-Member</h5>
                        </div>`;
                    }
                    
                    html += `</div></div></div></div>`;
                    
                    html += `<div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Purchased Items</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Product</th>
                                            <th class="text-center">Quantity</th>
                                            <th class="text-end">Price</th>
                                            <th class="text-end">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>`;
                    
                    let totalAmount = 0;
                    response.items.forEach(item => {
                        const subtotal = item.quantity * item.price_per_unit;
                        totalAmount += subtotal;
                        html += `<tr>
                            <td>${item.product_name}</td>
                            <td class="text-center">${item.quantity}</td>
                            <td class="text-end">$${parseFloat(item.price_per_unit).toFixed(2)}</td>
                            <td class="text-end">$${subtotal.toFixed(2)}</td>
                        </tr>`;
                    });
                    
                    html += `</tbody>
                        <tfoot>
                            <tr class="table-primary">
                                <td colspan="3" class="text-end"><strong>Subtotal:</strong></td>
                                <td class="text-end"><strong>$${totalAmount.toFixed(2)}</strong></td>
                            </tr>`;
                    
                    if (response.transaction.discount_amount > 0) {
                        html += `<tr class="table-primary">
                            <td colspan="3" class="text-end"><strong>Discount:</strong></td>
                            <td class="text-end"><strong>-$${parseFloat(response.transaction.discount_amount).toFixed(2)}</strong></td>
                        </tr>
                        <tr class="table-success">
                            <td colspan="3" class="text-end"><strong>Final Total:</strong></td>
                            <td class="text-end"><strong>$${(parseFloat(response.transaction.total_amount) - parseFloat(response.transaction.discount_amount)).toFixed(2)}</strong></td>
                        </tr>`;
                    }
                    
                    html += `</tfoot></table>
                            </div>
                        </div>
                    </div>`;
                    
                    document.getElementById('transactionDetails').innerHTML = html;
                } else {
                    document.getElementById('transactionDetails').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>Error: ${response.message}
                        </div>`;
                }
            } catch (e) {
                document.getElementById('transactionDetails').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>Error parsing response
                    </div>`;
                console.error(e);
            }
        }
    };
    xhr.send('transaction_id=' + transactionId);
}

function closeModal() {
    document.getElementById('detailsModal').style.display = 'none';
    document.body.style.overflow = ''; // Restore body scrolling when modal is closed
}

// Close modal when clicking outside of it
window.onclick = function(event) {
    const modal = document.getElementById('detailsModal');
    if (event.target === modal) {
        closeModal();
    }
};
</script>

<?php require_once '../../include/footer.php'; ?>