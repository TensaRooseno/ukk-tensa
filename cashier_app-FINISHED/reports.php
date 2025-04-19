<?php
session_start();
require_once 'includes/db.php';
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use TCPDF;

// Authentication check - Only admin can access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: index.php");
    exit;
}

// Get date range filter
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // Default to first day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-d'); // Default to today

// Fetch transactions data with details
$stmt = $conn->prepare("
    SELECT 
        t.transaction_id,
        t.date_time,
        t.total_amount,
        t.discount_amount,
        u.username as cashier_name,
        m.phone_number as member_phone,
        t.points_used,
        t.points_earned,
        GROUP_CONCAT(CONCAT(p.name, ' (', td.quantity, ' × $', td.price_per_unit, ')') SEPARATOR ', ') as items
    FROM transactions t
    JOIN users u ON t.cashier_id = u.user_id
    LEFT JOIN members m ON t.member_id = m.id
    JOIN transaction_details td ON t.transaction_id = td.transaction_id
    JOIN products p ON td.product_id = p.product_id
    WHERE DATE(t.date_time) BETWEEN :start_date AND :end_date
    GROUP BY t.transaction_id
    ORDER BY t.date_time DESC
");
$stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle export requests
if (isset($_GET['export'])) {
    $format = $_GET['export'];
    
    if ($format === 'pdf') {
        // Create PDF
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetCreator('Cashier App');
        $pdf->SetAuthor('Admin');
        $pdf->SetTitle('Transaction Report');
        
        // Set default header data
        $pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, 'Transaction Report', "Period: $start_date to $end_date");
        
        // Set header and footer fonts
        $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
        $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
        
        // Add a page
        $pdf->AddPage();
        
        // Create the table content
        $html = '<table border="1" cellpadding="4">
            <tr>
                <th>ID</th>
                <th>Date</th>
                <th>Cashier</th>
                <th>Member</th>
                <th>Items</th>
                <th>Total</th>
                <th>Discount</th>
                <th>Points Used</th>
                <th>Points Earned</th>
            </tr>';
            
        foreach ($transactions as $trans) {
            $html .= '<tr>
                <td>'.$trans['transaction_id'].'</td>
                <td>'.$trans['date_time'].'</td>
                <td>'.$trans['cashier_name'].'</td>
                <td>'.($trans['member_phone'] ?? 'Non-member').'</td>
                <td>'.$trans['items'].'</td>
                <td>$'.number_format($trans['total_amount'], 2).'</td>
                <td>$'.number_format($trans['discount_amount'], 2).'</td>
                <td>$'.number_format($trans['points_used'], 2).'</td>
                <td>$'.number_format($trans['points_earned'], 2).'</td>
            </tr>';
        }
        $html .= '</table>';
        
        // Print the table
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // Output the PDF
        $pdf->Output('transaction_report.pdf', 'D');
        exit;
        
    } elseif ($format === 'excel') {
        // Create Excel
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set headers
        $headers = ['ID', 'Date', 'Cashier', 'Member', 'Items', 'Total', 'Discount', 'Points Used', 'Points Earned'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col.'1', $header);
            $col++;
        }
        
        // Add data
        $row = 2;
        foreach ($transactions as $trans) {
            $sheet->setCellValue('A'.$row, $trans['transaction_id']);
            $sheet->setCellValue('B'.$row, $trans['date_time']);
            $sheet->setCellValue('C'.$row, $trans['cashier_name']);
            $sheet->setCellValue('D'.$row, $trans['member_phone'] ?? 'Non-member');
            $sheet->setCellValue('E'.$row, $trans['items']);
            $sheet->setCellValue('F'.$row, $trans['total_amount']);
            $sheet->setCellValue('G'.$row, $trans['discount_amount']);
            $sheet->setCellValue('H'.$row, $trans['points_used']);
            $sheet->setCellValue('I'.$row, $trans['points_earned']);
            $row++;
        }
        
        // Auto-size columns
        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // Create Excel file
        $writer = new Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="transaction_report.xlsx"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Reports - Cashier App</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <!-- Admin Navigation -->
    <nav class="fixed top-0 left-0 h-full w-64 bg-gray-800">
        <div class="p-4">
            <h4 class="text-white text-xl font-bold text-center mb-6">Admin Dashboard</h4>
            <ul class="space-y-2">
                <li>
                    <a href="admin_dashboard.php" class="text-gray-300 hover:bg-gray-700 hover:text-white group flex items-center px-4 py-2 rounded-md">
                        Overview
                    </a>
                </li>
                <li>
                    <a href="user_management.php" class="text-gray-300 hover:bg-gray-700 hover:text-white group flex items-center px-4 py-2 rounded-md">
                        User Management
                    </a>
                </li>
                <li>
                    <a href="product_management.php" class="text-gray-300 hover:bg-gray-700 hover:text-white group flex items-center px-4 py-2 rounded-md">
                        Product Management
                    </a>
                </li>
                <li>
                    <a href="transactions.php" class="text-gray-300 hover:bg-gray-700 hover:text-white group flex items-center px-4 py-2 rounded-md">
                        Transactions
                    </a>
                </li>
                <li>
                    <a href="reports.php" class="bg-gray-900 text-white group flex items-center px-4 py-2 rounded-md">
                        Reports
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
            <h2 class="text-2xl font-bold text-gray-800 mb-6">Transaction Reports</h2>
            <p class="text-gray-600 mb-6">Generate and export transaction reports by date range.</p>

            <!-- Date Range Filter and Export Options -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <form method="GET" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label for="start_date" class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
                            <input type="date" id="start_date" name="start_date" 
                                   value="<?php echo $start_date; ?>" required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label for="end_date" class="block text-sm font-medium text-gray-700 mb-2">End Date</label>
                            <input type="date" id="end_date" name="end_date" 
                                   value="<?php echo $end_date; ?>" required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div class="flex items-end space-x-4">
                            <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Apply Filter
                            </button>
                            <a href="?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&export=pdf" 
                               class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                Export PDF
                            </a>
                            <a href="?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&export=excel" 
                               class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                Export Excel
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Transactions Table -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cashier</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Member</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Items</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Discount</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Points Used</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Points Earned</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($transactions as $trans): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo $trans['transaction_id']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo $trans['date_time']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo $trans['cashier_name']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo $trans['member_phone'] ?? 'Non-member'; ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    <div class="max-w-xs overflow-hidden">
                                        <?php echo $trans['items']; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                    $<?php echo number_format($trans['total_amount'], 2); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                    $<?php echo number_format($trans['discount_amount'], 2); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                    $<?php echo number_format($trans['points_used'], 2); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                    $<?php echo number_format($trans['points_earned'], 2); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>