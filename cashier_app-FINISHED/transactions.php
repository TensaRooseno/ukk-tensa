<?php
session_start();
require_once 'includes/db.php';
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Authentication check
if (!isset($_SESSION['user_id']) || ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 2)) {
    header("Location: index.php");
    exit;
}

// Define sidebar menu items based on user role
$role_id = $_SESSION['role_id'];
if ($role_id == 2) {
    // Cashier menu items
    $menu_items = [
        ['link' => 'cashier_dashboard.php', 'label' => 'Transactions'],
        ['link' => 'view_products.php', 'label' => 'View Products'],
        ['link' => 'transactions.php', 'label' => 'Transaction History']
    ];
} else {
    // Admin menu items
    $menu_items = [
        ['link' => 'admin_dashboard.php', 'label' => 'Overview'],
        ['link' => 'user_management.php', 'label' => 'User Management'],
        ['link' => 'product_management.php', 'label' => 'Product Management'],
        ['link' => 'transactions.php', 'label' => 'Transactions']
    ];
}

// Set interface properties based on user role
$sidebar_title = $role_id == 1 ? 'Admin Dashboard' : 'Cashier Dashboard';
$sidebar_bg_color = $role_id == 1 ? '#343a40' : '#28a745';
$sidebar_hover_color = $role_id == 1 ? '#495057' : '#218838';
$sidebar_width = $role_id == 1 ? '250px' : '200px';

// Handle export requests
if (isset($_GET['export'])) {
    $format = $_GET['export'];
    $period = $_GET['period'] ?? 'monthly';
    $selected_year = isset($_GET['year']) ? $_GET['year'] : date('Y');
    
    if ($period === 'annual') {
        $start_date = "$selected_year-01-01";
        $end_date = "$selected_year-12-31";
        $period_text = "Annual Transaction Report - $selected_year";
        $sheet_title = "Annual Report $selected_year"; // Shorter title for worksheet
    } else {
        $selected_month = isset($_GET['month']) ? $_GET['month'] : date('m');
        $start_date = "$selected_year-$selected_month-01";
        $end_date = date('Y-m-t', strtotime($start_date));
        $period_text = 'Monthly Transaction Report - ' . date('F Y', strtotime($start_date));
        $sheet_title = date('F Y', strtotime($start_date)); // Shorter title for worksheet
    }
    
    // Fetch transactions for the period
    $stmt = $conn->prepare("
        SELECT 
            t.*,
            u.username,
            m.phone_number as member_phone,
            p.name as product_name,
            td.quantity,
            td.price_per_unit
        FROM transactions t
        JOIN users u ON t.cashier_id = u.user_id
        LEFT JOIN members m ON t.member_id = m.id
        LEFT JOIN transaction_details td ON t.transaction_id = td.transaction_id
        LEFT JOIN products p ON td.product_id = p.product_id
        WHERE DATE(t.date_time) BETWEEN :start_date AND :end_date
        ORDER BY t.date_time DESC, t.transaction_id
    ");
    $stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($format === 'pdf') {
        // Create PDF using TCPDF with full namespace
        $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator('Cashier App');
        $pdf->SetAuthor('Admin');
        $pdf->SetTitle($period_text);
        
        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Set default monospaced font
        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
        
        // Set margins
        $pdf->SetMargins(15, 15, 15);
        
        // Set auto page breaks
        $pdf->SetAutoPageBreak(TRUE, 15);
        
        // Add a page
        $pdf->AddPage('P'); // Portrait orientation for better readability
        
        // Set font
        $pdf->SetFont('helvetica', 'B', 16);
        
        // Add title
        $pdf->Cell(0, 10, $period_text, 0, 1, 'C');
        $pdf->Ln(5);
        
        // Add period information
        $pdf->SetFont('helvetica', '', 11);
        $pdf->Cell(0, 10, "Period: $start_date to $end_date", 0, 1, 'C');
        $pdf->Ln(10);
        
        // Group transactions by date
        $grouped_transactions = [];
        foreach ($transactions as $trans) {
            $date = date('Y-m-d', strtotime($trans['date_time']));
            if (!isset($grouped_transactions[$date])) {
                $grouped_transactions[$date] = [];
            }
            $grouped_transactions[$date][] = $trans;
        }
        
        // For each date
        foreach ($grouped_transactions as $date => $daily_transactions) {
            // Add date header
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 10, date('F j, Y', strtotime($date)), 0, 1, 'L');
            $pdf->Ln(2);
            
            // Group transactions by transaction_id
            $grouped_by_id = [];
            foreach ($daily_transactions as $trans) {
                if (!isset($grouped_by_id[$trans['transaction_id']])) {
                    $grouped_by_id[$trans['transaction_id']] = [
                        'details' => $trans,
                        'items' => []
                    ];
                }
                $grouped_by_id[$trans['transaction_id']]['items'][] = [
                    'name' => $trans['product_name'],
                    'quantity' => $trans['quantity'],
                    'price' => $trans['price_per_unit']
                ];
            }
            
            // For each transaction
            foreach ($grouped_by_id as $transaction_id => $transaction) {
                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->Cell(0, 8, "Transaction #" . $transaction_id, 0, 1, 'L');
                
                $pdf->SetFont('helvetica', '', 10);
                $pdf->Cell(80, 6, "Time: " . date('H:i', strtotime($transaction['details']['date_time'])), 0, 0);
                $pdf->Cell(110, 6, "Cashier: " . $transaction['details']['username'], 0, 1);
                $pdf->Cell(80, 6, "Member: " . ($transaction['details']['member_phone'] ?? 'Non-member'), 0, 1);
                
                // Items table header
                $pdf->Ln(2);
                $pdf->SetFont('helvetica', 'B', 10);
                $pdf->SetFillColor(245, 245, 245);
                $pdf->Cell(80, 7, "Item", 1, 0, 'L', true);
                $pdf->Cell(30, 7, "Quantity", 1, 0, 'C', true);
                $pdf->Cell(35, 7, "Price", 1, 0, 'R', true);
                $pdf->Cell(35, 7, "Subtotal", 1, 1, 'R', true);
                
                // Items
                $pdf->SetFont('helvetica', '', 10);
                foreach ($transaction['items'] as $item) {
                    $subtotal = $item['quantity'] * $item['price'];
                    $pdf->Cell(80, 6, $item['name'], 1);
                    $pdf->Cell(30, 6, $item['quantity'], 1, 0, 'C');
                    $pdf->Cell(35, 6, '$' . number_format($item['price'], 2), 1, 0, 'R');
                    $pdf->Cell(35, 6, '$' . number_format($subtotal, 2), 1, 1, 'R');
                }
                
                // Transaction summary
                $pdf->SetFont('helvetica', 'B', 10);
                $pdf->Cell(145, 7, "Total Amount:", 1, 0, 'R', true);
                $pdf->Cell(35, 7, '$' . number_format($transaction['details']['total_amount'], 2), 1, 1, 'R', true);
                
                if ($transaction['details']['discount_amount'] > 0) {
                    $pdf->Cell(145, 7, "Discount:", 1, 0, 'R', true);
                    $pdf->Cell(35, 7, '$' . number_format($transaction['details']['discount_amount'], 2), 1, 1, 'R', true);
                }
                
                if ($transaction['details']['points_used'] > 0 || $transaction['details']['points_earned'] > 0) {
                    $pdf->SetFont('helvetica', '', 9);
                    $pdf->Cell(0, 6, "Points Used: $" . number_format($transaction['details']['points_used'], 2) . 
                                   " | Points Earned: $" . number_format($transaction['details']['points_earned'], 2), 0, 1, 'R');
                }
                
                $pdf->Ln(5);
            }
            
            $pdf->Ln(5);
            
            // Add new page if needed
            if ($pdf->GetY() > 250) {
                $pdf->AddPage();
            }
        }
        
        // Output the PDF
        $pdf->Output($period_text . '.pdf', 'D');
        exit;
        
    } elseif ($format === 'excel') {
        // Create Excel
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($sheet_title);
        
        // Set headers
        $headers = ['ID', 'Date', 'Items', 'Quantity', 'Price', 'Total Amount', 'Discount', 'Cashier', 'Member Phone', 'Points Used', 'Points Earned'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col.'1', $header);
            $col++;
        }
        
        // Add data
        $row = 2;
        $current_transaction = null;
        foreach ($transactions as $trans) {
            $is_same_transaction = ($current_transaction && $current_transaction['id'] === $trans['transaction_id']);
            
            $sheet->setCellValue('A'.$row, $is_same_transaction ? '' : $trans['transaction_id']);
            $sheet->setCellValue('B'.$row, $is_same_transaction ? '' : $trans['date_time']);
            $sheet->setCellValue('C'.$row, $trans['product_name']);
            $sheet->setCellValue('D'.$row, $trans['quantity']);
            $sheet->setCellValue('E'.$row, $trans['price_per_unit']);
            $sheet->setCellValue('F'.$row, $is_same_transaction ? '' : $trans['total_amount']);
            $sheet->setCellValue('G'.$row, $is_same_transaction ? '' : $trans['discount_amount']);
            $sheet->setCellValue('H'.$row, $is_same_transaction ? '' : $trans['username']);
            $sheet->setCellValue('I'.$row, $is_same_transaction ? '' : ($trans['member_phone'] ?? 'Non-member'));
            $sheet->setCellValue('J'.$row, $is_same_transaction ? '' : $trans['points_used']);
            $sheet->setCellValue('K'.$row, $is_same_transaction ? '' : $trans['points_earned']);
            
            if (!$is_same_transaction) {
                $current_transaction = [
                    'id' => $trans['transaction_id'],
                    'date' => $trans['date_time']
                ];
                // Add border to indicate new transaction
                $sheet->getStyle('A'.$row.':K'.$row)->getBorders()->getTop()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM);
            }
            
            $row++;
        }

        // Format currency columns
        $lastRow = count($transactions) + 1;
        $sheet->getStyle('E2:E'.$lastRow)->getNumberFormat()->setFormatCode('$#,##0.00');
        $sheet->getStyle('F2:G'.$lastRow)->getNumberFormat()->setFormatCode('$#,##0.00');
        $sheet->getStyle('J2:K'.$lastRow)->getNumberFormat()->setFormatCode('$#,##0.00');
        
        // Auto-size columns
        foreach (range('A', 'K') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // Create Excel file
        $writer = new Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $period_text . '.xlsx"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
        exit;
    }
}

// Get current month and year for default selection
$current_month = isset($_GET['month']) ? $_GET['month'] : date('m');
$current_year = isset($_GET['year']) ? $_GET['year'] : date('Y');

// Pagination setup
$limit = 10; // Transactions per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Get total number of rows for pagination
$totalStmt = $conn->query("
    SELECT COUNT(*) as total 
    FROM transactions t
    JOIN transaction_details td ON t.transaction_id = td.transaction_id
");
$total = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($total / $limit);

// Fetch transactions with items for current page
$stmt = $conn->prepare("
    SELECT 
        t.transaction_id,
        t.date_time,
        t.total_amount,
        t.discount_amount,
        t.points_used,
        t.points_earned,
        u.username,
        m.phone_number as member_phone,
        p.name as product_name,
        td.quantity,
        td.price_per_unit
    FROM transactions t 
    JOIN users u ON t.cashier_id = u.user_id 
    LEFT JOIN members m ON t.member_id = m.id
    JOIN transaction_details td ON t.transaction_id = td.transaction_id
    JOIN products p ON td.product_id = p.product_id
    ORDER BY t.date_time DESC, t.transaction_id DESC, p.name ASC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Generate month options
$months = [];
for ($i = 1; $i <= 12; $i++) {
    $month_num = str_pad($i, 2, '0', STR_PAD_LEFT);
    $month_name = date('F', strtotime("2025-$month_num-01"));
    $months[$month_num] = $month_name;
}

// Generate year options (last 5 years to next year)
$years = range(date('Y') - 5, date('Y') + 1);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Transactions - Cashier App</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Include Bootstrap CSS and JS -->
    <link href="/ukk/cashier_app/assets/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="/ukk/cashier_app/assets/bootstrap.min.js"></script>
    <style>
        .sidebar {
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            width: <?php echo $sidebar_width; ?>;
            padding-top: 20px;
            background-color: <?php echo $sidebar_bg_color; ?>;
        }
        .sidebar .nav-link {
            color: #fff;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background-color: <?php echo $sidebar_hover_color; ?>;
        }
        .main-content {
            margin-left: <?php echo $sidebar_width; ?>;
            padding: 1.5rem;
        }
        .btn-export {
            min-width: 120px;
            margin: 0 4px;
        }
        .export-section {
            background-color: #f8f9fa;
            border-radius: 0.5rem;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
        }
        /* Table Styling */
        .table {
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 1rem;
        }
        .table th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            padding: 0.75rem 0.5rem;
            font-size: 0.95rem;
        }
        .table td {
            padding: 0.625rem 0.5rem;
            vertical-align: middle;
        }
        /* Add extra padding for multi-product rows */
        tr:has(td:empty) + tr {
            border-top: none !important;
        }
        tr:has(td:empty) td {
            padding-top: 0 !important;
            padding-bottom: 0.3rem !important;
        }
        tr:not(:has(td:empty)) {
            border-top: 2px solid #dee2e6 !important;
            margin-top: 0.5rem !important;
        }
        /* First row of each transaction group */
        tr:not(:has(td:empty)) td {
            padding-top: 0.8rem !important;
        }
        /* Modal Styling */
        .modal-body .card {
            border: none;
            box-shadow: 0 0 8px rgba(0,0,0,0.08);
            margin-bottom: 1.25rem;
        }
        .modal-body .card-body {
            padding: 1rem;
        }
        .modal-body .card-subtitle {
            font-size: 0.95rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
        }
        .modal-body .table th {
            background-color: #f8f9fa;
            font-weight: 600;
            padding: 0.625rem;
        }
        .modal-body .table td {
            padding: 0.625rem;
        }
        /* Pagination Styling */
        .pagination {
            margin-top: 1.5rem;
            justify-content: center;
        }
        .pagination .page-link {
            padding: 0.375rem 0.75rem;
        }
        /* Action Buttons */
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        .profile-dropdown {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 1030;
        }
        .profile-button {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: <?php echo $role_id == 1 ? '#343a40' : '#28a745'; ?>;
            border: none;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.2s;
        }
        .profile-button:hover,
        .profile-button:focus {
            background-color: <?php echo $role_id == 1 ? '#495057' : '#218838'; ?>;
        }
        .profile-icon {
            font-size: 1.2rem;
        }
        .profile-dropdown .dropdown-menu {
            right: 0;
            left: auto;
            margin-top: 0.5rem;
        }
        .user-info {
            padding: 0.5rem 1rem;
            border-bottom: 1px solid #dee2e6;
        }
    </style>
</head>
<body>
    <!-- Profile Dropdown -->
    <div class="profile-dropdown dropdown">
        <button class="profile-button" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fas fa-user profile-icon"></i>
        </button>
        <ul class="dropdown-menu">
            <li class="user-info">
                <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
                <div class="small text-muted"><?php echo $role_id == 1 ? 'Administrator' : 'Cashier'; ?></div>
            </li>
            <li><a class="dropdown-item text-danger" href="logout.php">Logout</a></li>
        </ul>
    </div>

    <!-- Role-based Sidebar Navigation -->
    <div class="sidebar">
        <h4 class="text-white text-center"><?php echo $sidebar_title; ?></h4>
        <ul class="nav flex-column">
            <?php foreach ($menu_items as $item): ?>
            <?php if ($item['link'] !== 'logout.php'): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == $item['link'] ? 'active' : ''; ?>" 
                   href="<?php echo $item['link']; ?>"><?php echo $item['label']; ?></a>
            </li>
            <?php endif; ?>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
        <h2>Transactions</h2>
        <p>View and export transaction records.</p>

        <!-- Export Section -->
        <div class="export-section mb-4">
            <div class="row">
                <div class="col-md-6">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#monthlyExportModal">
                        Monthly Export
                    </button>
                    <button type="button" class="btn btn-primary ms-2" data-bs-toggle="modal" data-bs-target="#annualExportModal">
                        Annual Export
                    </button>
                </div>
            </div>
        </div>

        <!-- Monthly Export Modal -->
        <div class="modal fade" id="monthlyExportModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Monthly Export</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="GET">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="monthSelect" class="form-label">Select Month</label>
                                <select id="monthSelect" name="month" class="form-select">
                                    <?php foreach ($months as $num => $name): ?>
                                    <option value="<?php echo $num; ?>" <?php echo $current_month == $num ? 'selected' : ''; ?>>
                                        <?php echo $name; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="yearSelect" class="form-label">Select Year</label>
                                <select id="yearSelect" name="year" class="form-select">
                                    <?php foreach ($years as $year): ?>
                                    <option value="<?php echo $year; ?>" <?php echo $current_year == $year ? 'selected' : ''; ?>>
                                        <?php echo $year; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label d-block">Export Format</label>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="export" id="monthlyPDF" value="pdf" checked>
                                    <label class="form-check-label" for="monthlyPDF">PDF</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="export" id="monthlyExcel" value="excel">
                                    <label class="form-check-label" for="monthlyExcel">Excel</label>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Export</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Annual Export Modal -->
        <div class="modal fade" id="annualExportModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Annual Export</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="GET">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="annualYearSelect" class="form-label">Select Year</label>
                                <select id="annualYearSelect" name="year" class="form-select">
                                    <?php foreach ($years as $year): ?>
                                    <option value="<?php echo $year; ?>" <?php echo $current_year == $year ? 'selected' : ''; ?>>
                                        <?php echo $year; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label d-block">Export Format</label>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="export" id="annualPDF" value="pdf" checked>
                                    <label class="form-check-label" for="annualPDF">PDF</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="export" id="annualExcel" value="excel">
                                    <label class="form-check-label" for="annualExcel">Excel</label>
                                </div>
                            </div>
                            <input type="hidden" name="period" value="annual">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Export</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Transactions Table -->
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Date</th>
                    <th>Product</th>
                    <th>Quantity</th>
                    <th>Price/Unit</th>
                    <th>Total Amount</th>
                    <th>Discount</th>
                    <th>Cashier</th>
                    <th>Member Phone</th>
                    <th>Points Used</th>
                    <th>Points Earned</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $current_transaction = null;
                foreach ($transactions as $trans): 
                    $is_same_transaction = ($current_transaction && $current_transaction['id'] === $trans['transaction_id']);
                ?>
                <tr>
                    <td><?php echo $is_same_transaction ? '' : $trans['transaction_id']; ?></td>
                    <td><?php echo $is_same_transaction ? '' : $trans['date_time']; ?></td>
                    <td><?php echo htmlspecialchars($trans['product_name']); ?></td>
                    <td><?php echo $trans['quantity']; ?></td>
                    <td>$<?php echo number_format($trans['price_per_unit'], 2); ?></td>
                    <td><?php echo $is_same_transaction ? '' : '$' . number_format($trans['total_amount'], 2); ?></td>
                    <td><?php echo $is_same_transaction ? '' : '$' . number_format($trans['discount_amount'], 2); ?></td>
                    <td><?php echo $is_same_transaction ? '' : htmlspecialchars($trans['username']); ?></td>
                    <td><?php echo $is_same_transaction ? '' : ($trans['member_phone'] ?? 'Non-member'); ?></td>
                    <td><?php echo $is_same_transaction ? '' : '$' . number_format($trans['points_used'], 2); ?></td>
                    <td><?php echo $is_same_transaction ? '' : '$' . number_format($trans['points_earned'], 2); ?></td>
                    <td>
                        <?php if (!$is_same_transaction): ?>
                        <div class="d-flex gap-1">
                            <button class="btn btn-primary btn-sm view-transaction" 
                                    data-transaction-id="<?php echo $trans['transaction_id']; ?>">
                                View Details
                            </button>
                            <a href="generate_invoice.php?transaction_id=<?php echo $trans['transaction_id']; ?>" 
                               class="btn btn-secondary btn-sm">
                                Download Invoice
                            </a>
                        </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php 
                    if (!$is_same_transaction) {
                        $current_transaction = [
                            'id' => $trans['transaction_id'],
                            'date' => $trans['date_time']
                        ];
                    }
                endforeach; 
                ?>
            </tbody>
        </table>

        <!-- Transaction Detail Modal -->
        <div class="modal fade" id="transactionModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Transaction Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Member Information -->
                        <div class="card mb-3">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 text-muted">Member Information</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Member Status:</strong> <span id="memberStatus"></span></p>
                                        <p><strong>Phone:</strong> <span id="memberPhone"></span></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Member Since:</strong> <span id="memberJoinDate"></span></p>
                                        <p><strong>Current Points:</strong> <span id="memberPoints"></span></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Transaction Items -->
                        <div class="card mb-3">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 text-muted">Items Purchased</h6>
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Quantity</th>
                                            <th>Price</th>
                                            <th>Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody id="transactionItems">
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="3" class="text-end"><strong>Total:</strong></td>
                                            <td><strong id="totalAmount"></strong></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <!-- Transaction Footer -->
                        <div class="card">
                            <div class="card-body">
                                <p><strong>Transaction Date:</strong> <span id="transactionDate"></span></p>
                                <p><strong>Processed By:</strong> <span id="cashierName"></span></p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pagination Navigation -->
        <nav>
            <ul class="pagination">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>&month=<?php echo $current_month; ?>&year=<?php echo $current_year; ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>

    <!-- Add jQuery and Bootstrap JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.view-transaction').click(function() {
                const transactionId = $(this).data('transaction-id');
                
                // Fetch transaction details
                $.get('get_transaction_details.php', { transaction_id: transactionId }, function(response) {
                    try {
                        const data = typeof response === 'string' ? JSON.parse(response) : response;
                        
                        if (data.error) {
                            alert('Error: ' + data.error);
                            return;
                        }

                        // Update member information
                        $('#memberStatus').text(data.is_member ? 'Yes' : 'No');
                        $('#memberPhone').text(data.member_phone || '-');
                        $('#memberJoinDate').text(data.member_join_date || '-');
                        $('#memberPoints').text(data.current_points ? '$' + parseFloat(data.current_points).toFixed(2) : '-');

                        // Update items table
                        $('#transactionItems').empty();
                        if (Array.isArray(data.items)) {
                            data.items.forEach(item => {
                                $('#transactionItems').append(`
                                    <tr>
                                        <td>${item.product_name}</td>
                                        <td>${item.quantity}</td>
                                        <td>$${parseFloat(item.price).toFixed(2)}</td>
                                        <td>$${(parseFloat(item.price) * item.quantity).toFixed(2)}</td>
                                    </tr>
                                `);
                            });
                        }

                        // Update totals and footer
                        $('#totalAmount').text('$' + parseFloat(data.total_amount).toFixed(2));
                        $('#transactionDate').text(data.date_time);
                        $('#cashierName').text(data.cashier_name);

                        // Show the modal
                        $('#transactionModal').modal('show');
                    } catch (e) {
                        console.error('Error processing response:', e);
                        alert('Error processing transaction details');
                    }
                }).fail(function(jqXHR, textStatus, errorThrown) {
                    console.error('AJAX error:', textStatus, errorThrown);
                    alert('Failed to fetch transaction details');
                });
            });
        });
    </script>
</body>
</html>