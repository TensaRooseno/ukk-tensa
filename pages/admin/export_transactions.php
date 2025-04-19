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

// Set headers
$headers = ['ID', 'Cashier', 'Date & Time', 'Member', 'Total Amount ($)', 'Discount ($)', 'Final Amount ($)', 'Points Used', 'Points Earned'];
$sheet->fromArray($headers, NULL, 'A1');

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

$sheet->getStyle('A1:I1')->applyFromArray($headerStyle);

// Get all transactions with details
$query = "SELECT t.*, u.username, m.phone_number as member_phone 
          FROM transactions t 
          JOIN users u ON t.cashier_id = u.user_id 
          LEFT JOIN members m ON t.member_id = m.id
          ORDER BY t.date_time DESC";
$transactions = mysqli_query($conn, $query);

// Add data rows
$row = 2;
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
    
    // Add to spreadsheet
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
}

// Auto size columns
foreach(range('A', 'I') as $col) {
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
$sheet->getStyle('A2:I' . ($row - 1))->applyFromArray($dataStyle);

// Money columns right-aligned
$sheet->getStyle('E2:I' . ($row - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

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