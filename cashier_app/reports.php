<?php
session_start();
require_once 'includes/db.php';
require_once 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: index.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $type = $_POST['type'];
    $period = $_POST['period']; // Expected format: YYYY-MM for monthly, YYYY for annual
    
    // Validate period format
    if ($type == 'monthly' && !preg_match('/^\d{4}-\d{2}$/', $period)) {
        $error = "Invalid monthly period format. Use YYYY-MM.";
    } elseif ($type == 'annual' && !preg_match('/^\d{4}$/', $period)) {
        $error = "Invalid annual period format. Use YYYY.";
    } else {
        list($year, $month) = $type == 'monthly' ? explode('-', $period) : [$period, null];
        
        $query = $type == 'monthly' ?
            "SELECT t.transaction_id, t.date_time, t.total_amount, t.discount_amount, u.username 
             FROM transactions t JOIN users u ON t.cashier_id = u.user_id 
             WHERE YEAR(t.date_time) = :year AND MONTH(t.date_time) = :month" :
            "SELECT t.transaction_id, t.date_time, t.total_amount, t.discount_amount, u.username 
             FROM transactions t JOIN users u ON t.cashier_id = u.user_id 
             WHERE YEAR(t.date_time) = :year";
        $stmt = $conn->prepare($query);
        $params = $type == 'monthly' ? ['year' => $year, 'month' => $month] : ['year' => $year];
        $stmt->execute($params);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($transactions)) {
            $error = "No transactions found for the specified period.";
        } elseif ($_POST['format'] == 'excel') {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setCellValue('A1', 'Transaction ID');
            $sheet->setCellValue('B1', 'Date');
            $sheet->setCellValue('C1', 'Total Amount');
            $sheet->setCellValue('D1', 'Discount');
            $sheet->setCellValue('E1', 'Cashier');
            $row = 2;
            foreach ($transactions as $t) {
                $sheet->setCellValue("A$row", $t['transaction_id']);
                $sheet->setCellValue("B$row", $t['date_time']);
                $sheet->setCellValue("C$row", $t['total_amount']);
                $sheet->setCellValue("D$row", $t['discount_amount']);
                $sheet->setCellValue("E$row", $t['username']);
                $row++;
            }
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header("Content-Disposition: attachment; filename=\"$type-report-$period.xlsx\"");
            $writer->save('php://output');
            exit;
        } else {
            $pdf = new TCPDF();
            $pdf->AddPage();
            $pdf->SetFont('helvetica', '', 12);
            $pdf->Cell(0, 10, "$type Report - $period", 0, 1, 'C');
            $pdf->Ln(10);
            $pdf->Cell(30, 10, 'Trans ID', 1);
            $pdf->Cell(50, 10, 'Date', 1);
            $pdf->Cell(30, 10, 'Total', 1);
            $pdf->Cell(30, 10, 'Discount', 1);
            $pdf->Cell(40, 10, 'Cashier', 1);
            $pdf->Ln();
            foreach ($transactions as $t) {
                $pdf->Cell(30, 10, $t['transaction_id'], 1);
                $pdf->Cell(50, 10, $t['date_time'], 1);
                $pdf->Cell(30, 10, '$' . number_format($t['total_amount'], 2), 1);
                $pdf->Cell(30, 10, '$' . number_format($t['discount_amount'], 2), 1);
                $pdf->Cell(40, 10, $t['username'], 1);
                $pdf->Ln();
            }
            $pdf->Output("$type-report-$period.pdf", 'D');
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Reports - Cashier App</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="/ukk/cashier_app/assets/bootstrap.min.css" rel="stylesheet">
    <!-- Pikaday CSS via CDN -->
    <link href="https://cdn.jsdelivr.net/npm/pikaday/css/pikaday.css" rel="stylesheet">
    <script src="/ukk/cashier_app/assets/bootstrap.min.js"></script>
    <!-- Moment.js via CDN (required by Pikaday for date manipulation) -->
    <script src="https://cdn.jsdelivr.net/npm/moment/min/moment.min.js"></script>
    <!-- Pikaday JS via CDN -->
    <script src="https://cdn.jsdelivr.net/npm/pikaday/pikaday.js"></script>
    <style>
        .sidebar {
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            padding-top: 20px;
            background-color: #343a40;
        }
        .sidebar .nav-link {
            color: #fff;
        }
        .sidebar .nav-link:hover {
            background-color: #495057;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        /* Custom Pikaday Styling */
        #pika-container {
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            background-color: #fff;
            font-family: 'Helvetica', sans-serif;
        }
        .pika-single {
            border: none;
        }
        .pika-day:hover, .pika-day:focus {
            background-color: #e9ecef;
        }
        .pika-day.is-selected {
            background-color: #007bff;
            color: #fff;
        }
        .pika-prev, .pika-next {
            color: #007bff;
        }
        .pika-select-month, .pika-select-year {
            color: #343a40;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <h4 class="text-white text-center">Admin Dashboard</h4>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="admin_dashboard.php">Overview</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="user_management.php">User Management</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="product_management.php">Product Management</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="transaction_history.php">Transaction History</a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="reports.php">Reports</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="logout.php">Logout</a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <h2>Generate Reports</h2>
        <p>Create monthly or annual transaction reports in Excel or PDF format.</p>

        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label for="type" class="form-label">Report Type</label>
                        <select name="type" id="type" class="form-control">
                            <option value="monthly">Monthly</option>
                            <option value="annual">Annual</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="period" class="form-label">Period</label>
                        <input type="text" name="period" id="period" class="form-control" required>
                        <div id="pika-container" style="position: absolute; z-index: 1000;"></div>
                    </div>
                    <div class="mb-3">
                        <label for="format" class="form-label">Format</label>
                        <select name="format" id="format" class="form-control">
                            <option value="excel">Excel</option>
                            <option value="pdf">PDF</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Generate Report</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const typeSelect = document.getElementById('type');
            const periodInput = document.getElementById('period');
            let picker;

            // Initialize Pikaday
            function initializePicker() {
                if (picker) picker.destroy(); // Destroy existing picker
                const isMonthly = typeSelect.value === 'monthly';
                picker = new Pikaday({
                    field: periodInput,
                    container: document.getElementById('pika-container'),
                    format: isMonthly ? 'YYYY-MM' : 'YYYY',
                    minDate: new Date(2020, 0, 1), // January 2020
                    maxDate: new Date(), // Today
                    yearRange: [2020, new Date().getFullYear()],
                    onSelect: function(date) {
                        const format = isMonthly ? 'YYYY-MM' : 'YYYY';
                        periodInput.value = this.getMoment().format(format);
                    },
                    toString: function(date, format) {
                        return moment(date).format(format);
                    },
                    parse: function(dateString, format) {
                        return moment(dateString, format).toDate();
                    },
                    showMonthAfterYear: false,
                    showDaysInNextAndPreviousMonths: false,
                    firstDay: 1 // Monday
                });

                // Hide day view for annual picker
                if (!isMonthly) {
                    picker.config({
                        showMonthAfterYear: true,
                        onOpen: function() {
                            const calendar = this.el;
                            const dayContainer = calendar.querySelector('.pika-table');
                            if (dayContainer) dayContainer.style.display = 'none';
                            const monthSelect = calendar.querySelector('.pika-select-month');
                            if (monthSelect) monthSelect.style.display = 'none';
                        }
                    });
                }
            }

            // Update picker when type changes
            typeSelect.addEventListener('change', initializePicker);

            // Initialize on page load
            initializePicker();
        });
    </script>
</body>
</html>