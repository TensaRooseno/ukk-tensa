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
<div id="detailsModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
    <div style="background-color: white; margin: 10% auto; padding: 20px; width: 80%; max-width: 700px;">
        <span style="float: right; cursor: pointer; font-size: 20px;" onclick="closeModal()">&times;</span>
        <h2>Transaction Details</h2>
        <div id="transactionDetails">
            <p>Loading...</p>
        </div>
    </div>
</div>

<script>
function showDetails(transactionId) {
    document.getElementById('detailsModal').style.display = 'block';
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
                    let html = `<div style="margin-bottom: 10px;">
                        <strong>Transaction ID:</strong> ${response.transaction.transaction_id}<br>
                        <strong>Date:</strong> ${response.transaction.date_time}<br>
                        <strong>Cashier ID:</strong> ${response.transaction.cashier_id}<br>
                        <strong>Total Amount:</strong> $${response.transaction.total_amount}
                    </div>`;
                    
                    html += '<table border="1" width="100%"><tr><th>Product</th><th>Quantity</th><th>Price</th><th>Subtotal</th></tr>';
                    
                    let totalAmount = 0;
                    response.items.forEach(item => {
                        const subtotal = item.quantity * item.price_per_unit;
                        totalAmount += subtotal;
                        html += `<tr>
                            <td>${item.product_name}</td>
                            <td>${item.quantity}</td>
                            <td>$${item.price_per_unit}</td>
                            <td>$${subtotal.toFixed(2)}</td>
                        </tr>`;
                    });
                    
                    html += `<tr>
                        <td colspan="3" align="right"><strong>Total:</strong></td>
                        <td><strong>$${totalAmount.toFixed(2)}</strong></td>
                    </tr></table>`;
                    
                    document.getElementById('transactionDetails').innerHTML = html;
                } else {
                    document.getElementById('transactionDetails').innerHTML = `<p>Error: ${response.message}</p>`;
                }
            } catch (e) {
                document.getElementById('transactionDetails').innerHTML = '<p>Error parsing response</p>';
            }
        }
    };
    xhr.send('transaction_id=' + transactionId);
}

function closeModal() {
    document.getElementById('detailsModal').style.display = 'none';
}

// Close modal when clicking outside of it
window.onclick = function(event) {
    const modal = document.getElementById('detailsModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
};
</script>

<?php require_once '../../include/footer.php'; ?>