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
$sheet->setTitle('Products');

// Set headers
$headers = ['ID', 'Name', 'Price ($)', 'Stock Quantity', 'Image Path'];
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

$sheet->getStyle('A1:E1')->applyFromArray($headerStyle);

// Get all products
$query = "SELECT * FROM products ORDER BY name ASC";
$products = mysqli_query($conn, $query);

// Add data rows
$row = 2;
while ($product = mysqli_fetch_assoc($products)) {
    // Add to spreadsheet
    $sheet->setCellValue('A' . $row, $product['product_id']);
    $sheet->setCellValue('B' . $row, $product['name']);
    $sheet->setCellValue('C' . $row, number_format($product['price'], 2));
    $sheet->setCellValue('D' . $row, $product['stock_quantity']);
    $sheet->setCellValue('E' . $row, $product['image_path'] ?? 'No Image');
    
    $row++;
}

// Auto size columns
foreach(range('A', 'E') as $col) {
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

$lastRow = $row - 1;
if ($lastRow > 1) {
    $sheet->getStyle('A2:E' . $lastRow)->applyFromArray($dataStyle);
}

// Center the ID and Stock columns
$sheet->getStyle('A2:A' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('D2:D' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Right align price column
$sheet->getStyle('C2:C' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

// Set the filename
$fileName = 'Products_Export_' . date('Y-m-d') . '.xlsx';

// Redirect output to a client's web browser (Excel2007)
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $fileName . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>