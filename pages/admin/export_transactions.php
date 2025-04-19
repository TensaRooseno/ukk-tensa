<?php
require_once '../../include/config.php';
require '../../vendor/autoload.php';

// Check if user is logged in and has admin role
if (!isLoggedIn() || !hasRole('admin')) {
    header('Location: ../auth/login.php');
    exit;
}

// Use PhpSpreadsheet classes
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Create new Spreadsheet object
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Transactions');

// Add title
$sheet->setCellValue('A1', 'Cashier Transactions Export Data');
$sheet->mergeCells('A1:M1');

// Title style
$titleStyle = [
    'font' => [
        'bold' => true,
        'size' => 16,
        'color' => ['rgb' => '000000'],
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
];
$sheet->getStyle('A1:M1')->applyFromArray($titleStyle);
$sheet->getRowDimension(1)->setRowHeight(30);

// Set columns for transaction data (not including products)
$headers = ['Transaction ID', 'Cashier', 'Date & Time', 'Member', 'Total Amount ($)', 'Discount ($)', 'Final Amount ($)', 'Points Used', 'Points Earned', 'Product Name', 'Quantity', 'Price ($)', 'Subtotal ($)'];
$sheet->fromArray($headers, NULL, 'A2');

// Style headers
$headerStyle = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'color' => ['rgb' => '4472C4'],
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
        ],
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
];

$sheet->getStyle('A2:M2')->applyFromArray($headerStyle);

// Get all transactions with details
$query = "SELECT t.*, u.username, m.phone_number as member_phone 
          FROM transactions t 
          JOIN users u ON t.cashier_id = u.user_id 
          LEFT JOIN members m ON t.member_id = m.id
          ORDER BY t.date_time DESC";
$transactions = mysqli_query($conn, $query);

// Add data rows
$row = 3;
while ($transaction = mysqli_fetch_assoc($transactions)) {
    // Calculate final amount
    $finalAmount = $transaction['total_amount'] - $transaction['discount_amount'];
    
    // Member info
    $memberInfo = $transaction['member_phone'] ? $transaction['member_phone'] : 'Non-Member';
    
    // Get transaction items
    $itemsQuery = "SELECT td.*, p.name as product_name
                   FROM transaction_details td
                   JOIN products p ON td.product_id = p.product_id
                   WHERE td.transaction_id = " . $transaction['transaction_id'];
    $items = mysqli_query($conn, $itemsQuery);
    
    // Format date
    $formattedDate = date('Y-m-d H:i', strtotime($transaction['date_time']));
    
    // Process products
    $productCount = mysqli_num_rows($items);
    
    if ($productCount == 0) {
        // If no products, just add the transaction row with empty product columns
        $sheet->setCellValue('A' . $row, $transaction['transaction_id']);
        $sheet->setCellValue('B' . $row, $transaction['username']);
        $sheet->setCellValue('C' . $row, $formattedDate);
        $sheet->setCellValue('D' . $row, $memberInfo);
        $sheet->setCellValue('E' . $row, number_format($transaction['total_amount'], 2));
        $sheet->setCellValue('F' . $row, number_format($transaction['discount_amount'], 2));
        $sheet->setCellValue('G' . $row, number_format($finalAmount, 2));
        $sheet->setCellValue('H' . $row, $transaction['points_used'] ?? 0);
        $sheet->setCellValue('I' . $row, $transaction['points_earned'] ?? 0);
        $row++;
    } else {
        // For each product in the transaction, create a new row with all transaction data
        $firstProduct = true;
        while ($item = mysqli_fetch_assoc($items)) {
            // For the first product, include all transaction details
            if ($firstProduct) {
                $sheet->setCellValue('A' . $row, $transaction['transaction_id']);
                $sheet->setCellValue('B' . $row, $transaction['username']);
                $sheet->setCellValue('C' . $row, $formattedDate);
                $sheet->setCellValue('D' . $row, $memberInfo);
                $sheet->setCellValue('E' . $row, number_format($transaction['total_amount'], 2));
                $sheet->setCellValue('F' . $row, number_format($transaction['discount_amount'], 2));
                $sheet->setCellValue('G' . $row, number_format($finalAmount, 2));
                $sheet->setCellValue('H' . $row, $transaction['points_used'] ?? 0);
                $sheet->setCellValue('I' . $row, $transaction['points_earned'] ?? 0);
                $firstProduct = false;
            } else {
                // For subsequent products, just repeat the transaction ID (helps with grouping)
                $sheet->setCellValue('A' . $row, $transaction['transaction_id']);
                
                // Merge cells for transaction data across product rows to visually group them
                if ($productCount > 1 && $row > 3) {
                    // Don't merge if this is the first row
                    $previousRow = $row - 1;
                    $previousTransactionId = $sheet->getCell('A' . $previousRow)->getValue();
                    
                    // If the previous row has the same transaction ID, it's part of the same transaction
                    if ($previousTransactionId == $transaction['transaction_id']) {
                        // Skip other transaction columns to leave them empty (cleaner look)
                    }
                }
            }
            
            // Add product details on each row
            $subtotal = $item['quantity'] * $item['price_per_unit'];
            $sheet->setCellValue('J' . $row, $item['product_name']);
            $sheet->setCellValue('K' . $row, $item['quantity']);
            $sheet->setCellValue('L' . $row, number_format($item['price_per_unit'], 2));
            $sheet->setCellValue('M' . $row, number_format($subtotal, 2));
            
            $row++;
        }
    }
}

// Auto size columns
foreach(range('A', 'M') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Apply style to data rows
$dataStyle = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
        ],
    ],
    'alignment' => [
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
];
$sheet->getStyle('A3:M' . ($row - 1))->applyFromArray($dataStyle);

// Money columns right-aligned
$sheet->getStyle('E3:I' . ($row - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
$sheet->getStyle('K3:M' . ($row - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

// Set file name
$fileName = 'Transactions_Export_' . date('Y-m-d_H-i-s') . '.xlsx';

// Redirect output to a client's web browser
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $fileName . '"');
header('Cache-Control: max-age=0');

// Output the file
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>