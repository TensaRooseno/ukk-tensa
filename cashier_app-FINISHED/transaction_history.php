<!DOCTYPE html>
<html lang="en">
<head>
    <title>Transaction History - Cashier App</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Add Bootstrap JS and Popper.js for modals -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>
</head>
<body class="bg-gray-100">
    <!-- Navigation -->
    <nav class="fixed top-0 left-0 h-full w-64 bg-gray-800">
        <div class="p-4">
            <h4 class="text-white text-xl font-bold text-center mb-6">Transaction History</h4>
            <ul class="space-y-2">
                <li>
                    <a href="cashier_dashboard.php" class="text-gray-300 hover:bg-gray-700 hover:text-white group flex items-center px-4 py-2 rounded-md">
                        New Transaction
                    </a>
                </li>
                <li>
                    <a href="view_products.php" class="text-gray-300 hover:bg-gray-700 hover:text-white group flex items-center px-4 py-2 rounded-md">
                        View Products
                    </a>
                </li>
                <li>
                    <a href="transaction_history.php" class="bg-gray-900 text-white group flex items-center px-4 py-2 rounded-md">
                        Transaction History
                    </a>
                </li>
                <li>
                    <a href="logout.php" class="text-gray-300 hover:bg-gray-700 hover:text-white group flex items-center px-4 py-2 rounded-md">
                        Logout
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Main Content Area -->
    <div class="ml-64 p-8">
        <div class="max-w-7xl mx-auto">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">Transaction History</h2>

            <!-- Transaction Table -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Discount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cashier</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Member</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Points Used</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Points Earned</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($transactions as $trans): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $trans['transaction_id']; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $trans['date_time']; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">$<?php echo number_format($trans['total_amount'], 2); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">$<?php echo number_format($trans['discount_amount'], 2); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $trans['username']; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $trans['member_phone'] ?? 'Non-member'; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">$<?php echo number_format($trans['points_used'], 2); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">$<?php echo number_format($trans['points_earned'], 2); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <button class="text-blue-600 hover:text-blue-900 view-transaction" 
                                        data-transaction-id="<?php echo $trans['transaction_id']; ?>">
                                    View Details
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="flex justify-center mt-6">
                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>" 
                       class="<?php echo $i == $page ? 'bg-blue-50 border-blue-500 text-blue-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'; ?> relative inline-flex items-center px-4 py-2 border text-sm font-medium">
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>
                </nav>
            </div>
        </div>
    </div>

    <!-- Transaction Details Modal -->
    <div class="modal fade" id="transactionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content bg-white rounded-lg shadow-xl">
                <div class="modal-header border-b p-4">
                    <h5 class="text-lg font-semibold">Transaction Details</h5>
                    <button type="button" class="text-gray-400 hover:text-gray-500" data-bs-dismiss="modal">×</button>
                </div>
                <div class="modal-body p-4 space-y-4">
                    <!-- Member Information -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h6 class="text-sm font-medium text-gray-700 mb-3">Member Information</h6>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p><span class="font-medium">Member Status:</span> <span id="memberStatus"></span></p>
                                <p><span class="font-medium">Phone:</span> <span id="memberPhone"></span></p>
                            </div>
                            <div>
                                <p><span class="font-medium">Points Used:</span> <span id="pointsUsed"></span></p>
                                <p><span class="font-medium">Points Earned:</span> <span id="pointsEarned"></span></p>
                            </div>
                        </div>
                    </div>

                    <!-- Transaction Items -->
                    <div class="bg-white rounded-lg border border-gray-200">
                        <h6 class="text-sm font-medium text-gray-700 p-4 border-b">Items Purchased</h6>
                        <div class="p-4">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                        <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Quantity</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Price</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody id="transactionItems" class="divide-y divide-gray-200"></tbody>
                                <tfoot>
                                    <tr class="border-t-2">
                                        <td colspan="3" class="px-4 py-2 text-right font-medium">Subtotal:</td>
                                        <td class="px-4 py-2 text-right font-medium" id="subtotal"></td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" class="px-4 py-2 text-right font-medium">Discount:</td>
                                        <td class="px-4 py-2 text-right font-medium" id="discount"></td>
                                    </tr>
                                    <tr class="border-t">
                                        <td colspan="3" class="px-4 py-2 text-right font-medium">Total:</td>
                                        <td class="px-4 py-2 text-right font-bold" id="total"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- Transaction Footer -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <p><span class="font-medium">Transaction Date:</span> <span id="transactionDate"></span></p>
                        <p><span class="font-medium">Processed By:</span> <span id="cashierName"></span></p>
                    </div>
                </div>
                <div class="modal-footer bg-gray-50 px-4 py-3">
                    <button type="button" class="inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:text-sm"
                            data-bs-dismiss="modal">Close</button>
                    <button type="button" class="ml-3 inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:text-sm"
                            onclick="printTransactionDetails()">Print</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // View Transaction Details
            $('.view-transaction').click(function() {
                const transactionId = $(this).data('transaction-id');
                
                $.get('get_transaction_details.php', { transaction_id: transactionId }, function(response) {
                    const data = JSON.parse(response);
                    if (data.error) {
                        alert('Error: ' + data.error);
                        return;
                    }

                    // Update member information
                    $('#memberStatus').text(data.member_id ? 'Member' : 'Non-member');
                    $('#memberPhone').text(data.member_phone || '-');
                    $('#pointsUsed').text('$' + (data.points_used || 0).toFixed(2));
                    $('#pointsEarned').text('$' + (data.points_earned || 0).toFixed(2));

                    // Update items table
                    $('#transactionItems').empty();
                    data.items.forEach(item => {
                        const subtotal = item.price * item.quantity;
                        $('#transactionItems').append(`
                            <tr>
                                <td class="px-4 py-2">${item.product_name}</td>
                                <td class="px-4 py-2 text-center">${item.quantity}</td>
                                <td class="px-4 py-2 text-right">$${parseFloat(item.price).toFixed(2)}</td>
                                <td class="px-4 py-2 text-right">$${subtotal.toFixed(2)}</td>
                            </tr>
                        `);
                    });

                    // Update totals
                    $('#subtotal').text('$' + parseFloat(data.subtotal).toFixed(2));
                    $('#discount').text('$' + parseFloat(data.discount_amount).toFixed(2));
                    $('#total').text('$' + parseFloat(data.total_amount).toFixed(2));

                    // Update footer information
                    $('#transactionDate').text(data.date_time);
                    $('#cashierName').text(data.cashier_name);

                    // Show modal
                    new bootstrap.Modal(document.getElementById('transactionModal')).show();
                });
            });
        });

        // Print transaction details
        function printTransactionDetails() {
            const printContent = document.querySelector('.modal-body').innerHTML;
            const printWindow = window.open('', '', 'width=800,height=600');
            
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Transaction Details</title>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 20px; }
                        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
                        .text-center { text-align: center; }
                        .text-right { text-align: right; }
                        .bg-gray-50 { background-color: #f9fafb; }
                        .rounded-lg { border-radius: 8px; }
                        .p-4 { padding: 1rem; }
                        .mb-3 { margin-bottom: 0.75rem; }
                        .font-medium { font-weight: 500; }
                        .grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; }
                        @media print {
                            .bg-gray-50 { -webkit-print-color-adjust: exact; }
                        }
                    </style>
                </head>
                <body>
                    ${printContent}
                </body>
                </html>
            `);
            
            printWindow.document.close();
            printWindow.focus();
            setTimeout(() => {
                printWindow.print();
                printWindow.close();
            }, 250);
        }
    </script>
</body>
</html>