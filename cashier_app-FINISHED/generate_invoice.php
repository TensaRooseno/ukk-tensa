<?php
session_start();
require_once 'includes/db.php';
require_once 'vendor/autoload.php';

// Authentication check
if (!isset($_SESSION['user_id']) || ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 2)) {
    header("Location: index.php");
    exit;
}

if (!isset($_GET['transaction_id'])) {
    die("Transaction ID is required");
}

$transaction_id = $_GET['transaction_id'];

// Fetch transaction details
$stmt = $conn->prepare("
    SELECT 
        t.*,
        u.username as cashier_name,
        m.phone_number as member_phone,
        m.created_at as member_join_date,
        m.points as current_points,
        p.name as product_name,
        td.quantity,
        td.price_per_unit
    FROM transactions t
    JOIN users u ON t.cashier_id = u.user_id
    LEFT JOIN members m ON t.member_id = m.id
    JOIN transaction_details td ON t.transaction_id = td.transaction_id
    JOIN products p ON td.product_id = p.product_id
    WHERE t.transaction_id = :transaction_id
");

$stmt->execute(['transaction_id' => $transaction_id]);
$transaction_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($transaction_data)) {
    die("Transaction not found");
}

// Create PDF
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('Cashier App');
$pdf->SetAuthor('System');
$pdf->SetTitle('Invoice #' . $transaction_id);

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
$pdf->AddPage();

// Set font
$pdf->SetFont('helvetica', 'B', 20);

// Add company header
$pdf->Cell(0, 10, 'INVOICE', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 5, 'Your Company Name', 0, 1, 'C');
$pdf->Cell(0, 5, 'Address Line 1', 0, 1, 'C');
$pdf->Cell(0, 5, 'Phone: (123) 456-7890', 0, 1, 'C');
$pdf->Cell(0, 5, 'Email: contact@example.com', 0, 1, 'C');
$pdf->Ln(10);

// Add invoice information
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 10, 'Invoice #' . $transaction_id, 0, 1, 'L');
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 5, 'Date: ' . $transaction_data[0]['date_time'], 0, 1, 'L');
$pdf->Cell(0, 5, 'Cashier: ' . $transaction_data[0]['cashier_name'], 0, 1, 'L');

// Add customer information if available
if (!empty($transaction_data[0]['member_phone'])) {
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Member Information', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 5, 'Phone: ' . $transaction_data[0]['member_phone'], 0, 1, 'L');
    $pdf->Cell(0, 5, 'Member Since: ' . $transaction_data[0]['member_join_date'], 0, 1, 'L');
    $pdf->Cell(0, 5, 'Current Points: $' . number_format($transaction_data[0]['current_points'], 2), 0, 1, 'L');
}

// Add items table
$pdf->Ln(10);
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 10, 'Items Purchased', 0, 1, 'L');

// Table header
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetFillColor(245, 245, 245);
$pdf->Cell(80, 7, 'Product', 1, 0, 'L', true);
$pdf->Cell(30, 7, 'Quantity', 1, 0, 'C', true);
$pdf->Cell(35, 7, 'Price', 1, 0, 'R', true);
$pdf->Cell(35, 7, 'Subtotal', 1, 1, 'R', true);

// Table content
$pdf->SetFont('helvetica', '', 10);
foreach ($transaction_data as $item) {
    $subtotal = $item['quantity'] * $item['price_per_unit'];
    $pdf->Cell(80, 6, $item['product_name'], 1);
    $pdf->Cell(30, 6, $item['quantity'], 1, 0, 'C');
    $pdf->Cell(35, 6, '$' . number_format($item['price_per_unit'], 2), 1, 0, 'R');
    $pdf->Cell(35, 6, '$' . number_format($subtotal, 2), 1, 1, 'R');
}

// Add totals
$pdf->SetFont('helvetica', 'B', 10);

if ($transaction_data[0]['discount_amount'] > 0) {
    $pdf->Cell(145, 7, 'Discount:', 1, 0, 'R', true);
    $pdf->Cell(35, 7, '-$' . number_format($transaction_data[0]['discount_amount'], 2), 1, 1, 'R', true);
}

$pdf->Cell(145, 7, 'Subtotal:', 1, 0, 'R', true);
$pdf->Cell(35, 7, '$' . number_format($transaction_data[0]['total_amount'], 2), 1, 1, 'R', true);

if ($transaction_data[0]['discount_amount'] > 0) {
    $pdf->Cell(145, 7, 'Final Amount:', 1, 0, 'R', true);
    $pdf->Cell(35, 7, '$' . number_format($transaction_data[0]['total_amount'] - $transaction_data[0]['discount_amount'], 2), 1, 1, 'R', true);
}

// Add points information if applicable
if ($transaction_data[0]['points_used'] > 0 || $transaction_data[0]['points_earned'] > 0) {
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', '', 10);
    if ($transaction_data[0]['points_used'] > 0) {
        $pdf->Cell(0, 5, 'Points Used: $' . number_format($transaction_data[0]['points_used'], 2), 0, 1, 'R');
    }
    if ($transaction_data[0]['points_earned'] > 0) {
        $pdf->Cell(0, 5, 'Points Earned: $' . number_format($transaction_data[0]['points_earned'], 2), 0, 1, 'R');
    }
}

// Add footer
$pdf->Ln(10);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 5, 'Thank you for your business!', 0, 1, 'C');
$pdf->Cell(0, 5, 'For any questions, please contact us at support@example.com', 0, 1, 'C');

// Output the PDF
$pdf->Output('Invoice_' . $transaction_id . '.pdf', 'D');
?>