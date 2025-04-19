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
$sheet->setTitle('Users');

// Add title
$sheet->setCellValue('A1', 'User Data Export');
$sheet->mergeCells('A1:D1');

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
$sheet->getStyle('A1:D1')->applyFromArray($titleStyle);
$sheet->getRowDimension(1)->setRowHeight(30);

// Set headers (now in row 2)
$headers = ['ID', 'Username', 'Email', 'Role'];
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

$sheet->getStyle('A2:D2')->applyFromArray($headerStyle);

// Get all users with their roles
$query = "SELECT u.user_id, u.username, u.email, r.role_name 
          FROM users u 
          JOIN roles r ON u.role_id = r.role_id 
          ORDER BY u.user_id";
$users = mysqli_query($conn, $query);

// Add data rows
$row = 3; // Start from row 3 since we now have a title row
while ($user = mysqli_fetch_assoc($users)) {
    // Add to spreadsheet
    $sheet->setCellValue('A' . $row, $user['user_id']);
    $sheet->setCellValue('B' . $row, $user['username']);
    $sheet->setCellValue('C' . $row, $user['email']);
    $sheet->setCellValue('D' . $row, $user['role_name']);
    
    $row++;
}

// Auto size columns
foreach(range('A', 'D') as $col) {
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
if ($lastRow > 2) {
    $sheet->getStyle('A3:D' . $lastRow)->applyFromArray($dataStyle);
}

// Center the ID and Role columns
$sheet->getStyle('A3:A' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('D3:D' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Set the filename
$fileName = 'Users_Export_' . date('Y-m-d') . '.xlsx';

// Redirect output to a client's web browser (Excel2007)
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $fileName . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>