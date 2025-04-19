<?php
require_once '../../include/config.php';

// Check if user is logged in and has admin role
if (!isLoggedIn() || !hasRole('admin')) {
    header('Location: ../auth/login.php');
    exit;
}

// Get all transactions
$query = "SELECT t.*, u.username FROM transactions t 
          JOIN users u ON t.cashier_id = u.user_id 
          ORDER BY t.date_time DESC";
$transactions = mysqli_query($conn, $query);

require_once '../../include/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <h1>All Transactions</h1>
        
        <table border="1" width="100%">
            <tr>
                <th>ID</th>
                <th>Cashier</th>
                <th>Date</th>
                <th>Total Amount</th>
                <th>Actions</th>
            </tr>
            <?php if (mysqli_num_rows($transactions) > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($transactions)): ?>
                    <tr>
                        <td><?php echo $row['transaction_id']; ?></td>
                        <td><?php echo $row['username']; ?></td>
                        <td><?php echo date('Y-m-d H:i', strtotime($row['date_time'])); ?></td>
                        <td>$<?php echo number_format($row['total_amount'], 2); ?></td>
                        <td>
                            <button onclick="showDetails(<?php echo $row['transaction_id']; ?>)">View Details</button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" align="center">No transactions found</td>
                </tr>
            <?php endif; ?>
        </table>
    </div>
</div>

<!-- Modal for transaction details -->
<div id="detailsModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); overflow-y: auto; z-index: 1000;">
    <div style="background-color: white; margin: 5% auto; padding: 20px; width: 90%; max-width: 800px; position: relative; border-radius: 5px; box-shadow: 0 4px 8px rgba(0,0,0,0.2);">
        <span style="position: absolute; top: 10px; right: 15px; cursor: pointer; font-size: 24px; font-weight: bold;" onclick="closeModal()">&times;</span>
        <h2 style="margin-top: 10px;">Transaction Details</h2>
        <div id="transactionDetails" style="max-height: 70vh; overflow-y: auto; padding: 10px 0;">
            <p>Loading...</p>
        </div>
    </div>
</div>

<script>
function showDetails(transactionId) {
    document.getElementById('detailsModal').style.display = 'block';
    document.body.style.overflow = 'hidden'; // Prevent body scrolling when modal is open
    document.getElementById('transactionDetails').innerHTML = '<p>Loading...</p>';
    
    // AJAX request to get transaction details
    const xhr = new XMLHttpRequest();
    xhr.open('POST', '../cashier/get_transaction_details.php', true);
    xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        if (this.status === 200) {
            try {
                const response = JSON.parse(this.responseText);
                if (response.success) {
                    let html = `<div style="margin-bottom: 15px; padding: 10px; border-bottom: 1px solid #ddd;">
                        <strong>Transaction ID:</strong> ${response.transaction.transaction_id}<br>
                        <strong>Date:</strong> ${response.transaction.date_time}<br>
                        <strong>Cashier:</strong> ${response.transaction.username}<br>
                        <strong>Total Amount:</strong> $${response.transaction.total_amount}`;
                    
                    // Add member information if available
                    if (response.member) {
                        const pointsBefore = (parseFloat(response.member.points) - parseFloat(response.transaction.points_earned) + parseFloat(response.transaction.points_used)).toFixed(2);
                        html += `<br><br><strong>Member Information:</strong><br>
                        <table border="0" cellpadding="3">
                            <tr><td><strong>Phone:</strong></td><td>${response.member.phone_number}</td></tr>
                            <tr><td><strong>Points Before:</strong></td><td>${pointsBefore}</td></tr>
                            <tr><td><strong>Points Used:</strong></td><td>${response.transaction.points_used}</td></tr>
                            <tr><td><strong>Points Earned:</strong></td><td>${response.transaction.points_earned}</td></tr>
                            <tr><td><strong>Current Points:</strong></td><td>${response.member.points}</td></tr>
                            <tr><td><strong>Discount Applied:</strong></td><td>$${parseFloat(response.transaction.discount_amount).toFixed(2)}</td></tr>
                        </table>`;
                    } else {
                        html += `<br><br><strong>Customer Type:</strong> Non-Member`;
                    }
                    
                    html += `</div>`;
                    
                    html += '<table border="1" width="100%" style="border-collapse: collapse; margin-bottom: 15px;"><tr style="background-color: #f2f2f2;"><th>Product</th><th>Quantity</th><th>Price</th><th>Subtotal</th></tr>';
                    
                    let totalAmount = 0;
                    response.items.forEach(item => {
                        const subtotal = item.quantity * item.price_per_unit;
                        totalAmount += subtotal;
                        html += `<tr>
                            <td style="padding: 8px;">${item.product_name}</td>
                            <td style="padding: 8px; text-align: center;">${item.quantity}</td>
                            <td style="padding: 8px; text-align: right;">$${parseFloat(item.price_per_unit).toFixed(2)}</td>
                            <td style="padding: 8px; text-align: right;">$${subtotal.toFixed(2)}</td>
                        </tr>`;
                    });
                    
                    html += `<tr style="background-color: #f9f9f9;">
                        <td colspan="3" align="right" style="padding: 8px;"><strong>Subtotal:</strong></td>
                        <td style="padding: 8px; text-align: right;"><strong>$${totalAmount.toFixed(2)}</strong></td>
                    </tr>`;
                    
                    if (response.transaction.discount_amount > 0) {
                        html += `<tr style="background-color: #f9f9f9;">
                            <td colspan="3" align="right" style="padding: 8px;"><strong>Discount:</strong></td>
                            <td style="padding: 8px; text-align: right;"><strong>-$${parseFloat(response.transaction.discount_amount).toFixed(2)}</strong></td>
                        </tr>
                        <tr style="background-color: #eafaea;">
                            <td colspan="3" align="right" style="padding: 8px;"><strong>Final Total:</strong></td>
                            <td style="padding: 8px; text-align: right;"><strong>$${(parseFloat(response.transaction.total_amount) - parseFloat(response.transaction.discount_amount)).toFixed(2)}</strong></td>
                        </tr>`;
                    }
                    
                    html += `</table>`;
                    
                    document.getElementById('transactionDetails').innerHTML = html;
                } else {
                    document.getElementById('transactionDetails').innerHTML = `<p style="color: red;">Error: ${response.message}</p>`;
                }
            } catch (e) {
                document.getElementById('transactionDetails').innerHTML = '<p style="color: red;">Error parsing response</p>';
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