<?php
require_once '../../include/config.php';

// Check if user is logged in and has admin role
if (!isLoggedIn() || !hasRole('admin')) {
    header('Location: ../auth/login.php');
    exit;
}

// Validate transaction ID
if (!isset($_GET['transaction_id']) || !is_numeric($_GET['transaction_id'])) {
    die("Invalid transaction ID");
}

$transaction_id = intval($_GET['transaction_id']);

// Get transaction data
$query = "SELECT t.*, u.username, m.phone_number as member_phone 
          FROM transactions t 
          JOIN users u ON t.cashier_id = u.user_id 
          LEFT JOIN members m ON t.member_id = m.id
          WHERE t.transaction_id = $transaction_id";
$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) === 0) {
    die("Transaction not found");
}

$transaction = mysqli_fetch_assoc($result);

// Get transaction items with product names
$query = "SELECT td.*, p.name as product_name 
          FROM transaction_details td
          JOIN products p ON td.product_id = p.product_id
          WHERE td.transaction_id = $transaction_id";
$result = mysqli_query($conn, $query);

$items = [];
while ($row = mysqli_fetch_assoc($result)) {
    $items[] = $row;
}

// Get member information if the transaction has a member
$member = null;
if ($transaction['member_id']) {
    $query = "SELECT * FROM members WHERE id = " . $transaction['member_id'];
    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $member = mysqli_fetch_assoc($result);
    }
}

// Include FPDF library
require('../../assets/fpdf/fpdf186/fpdf.php');

// Create PDF class
class PDF extends FPDF
{
    // Page header
    function Header()
    {
        // Logo (if you want to add one)
        // $this->Image('logo.png', 10, 6, 30);
        
        // Set font
        $this->SetFont('Arial', 'B', 15);
        
        // Title
        $this->Cell(0, 10, 'INVOICE', 0, 1, 'C');
        
        // Line break
        $this->Ln(5);
    }

    // Page footer
    function Footer()
    {
        // Position at 1.5 cm from bottom
        $this->SetY(-15);
        
        // Set font
        $this->SetFont('Arial', 'I', 8);
        
        // Page number
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

// Create new PDF document
$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 12);

// Add invoice details
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'Invoice #' . $transaction_id, 0, 1);
$pdf->SetFont('Arial', '', 12);

$pdf->Cell(0, 10, 'Date: ' . date('Y-m-d H:i', strtotime($transaction['date_time'])), 0, 1);
$pdf->Cell(0, 10, 'Cashier: ' . $transaction['username'], 0, 1);

// Add member information if exists
if ($member) {
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, 'Member Information:', 0, 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 10, 'Phone: ' . $member['phone_number'], 0, 1);
    
    // Calculate points before transaction
    $pointsBefore = $member['points'] - $transaction['points_earned'] + $transaction['points_used'];
    
    $pdf->Cell(0, 10, 'Points Before: ' . number_format($pointsBefore, 2), 0, 1);
    $pdf->Cell(0, 10, 'Points Used: ' . number_format($transaction['points_used'], 2), 0, 1);
    $pdf->Cell(0, 10, 'Points Earned: ' . number_format($transaction['points_earned'], 2), 0, 1);
    $pdf->Cell(0, 10, 'Current Points: ' . number_format($member['points'], 2), 0, 1);
    $pdf->Cell(0, 10, 'Discount Applied: $' . number_format($transaction['discount_amount'], 2), 0, 1);
} else {
    $pdf->Ln(5);
    $pdf->Cell(0, 10, 'Customer Type: Non-Member', 0, 1);
}

// Add items table
$pdf->Ln(10);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(90, 10, 'Product', 1);
$pdf->Cell(30, 10, 'Quantity', 1, 0, 'C');
$pdf->Cell(30, 10, 'Price', 1, 0, 'R');
$pdf->Cell(40, 10, 'Subtotal', 1, 0, 'R');
$pdf->Ln();

// Item rows
$pdf->SetFont('Arial', '', 12);
$totalAmount = 0;

foreach ($items as $item) {
    $subtotal = $item['quantity'] * $item['price_per_unit'];
    $totalAmount += $subtotal;
    
    $pdf->Cell(90, 10, $item['product_name'], 1);
    $pdf->Cell(30, 10, $item['quantity'], 1, 0, 'C');
    $pdf->Cell(30, 10, '$' . number_format($item['price_per_unit'], 2), 1, 0, 'R');
    $pdf->Cell(40, 10, '$' . number_format($subtotal, 2), 1, 0, 'R');
    $pdf->Ln();
}

// Total section
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(120, 10, '', 0);
$pdf->Cell(30, 10, 'Subtotal:', 0);
$pdf->Cell(40, 10, '$' . number_format($totalAmount, 2), 0, 0, 'R');
$pdf->Ln();

// Add discount row if applicable
if ($transaction['discount_amount'] > 0) {
    $pdf->Cell(120, 10, '', 0);
    $pdf->Cell(30, 10, 'Discount:', 0);
    $pdf->Cell(40, 10, '-$' . number_format($transaction['discount_amount'], 2), 0, 0, 'R');
    $pdf->Ln();
}

// Final total
$finalTotal = $transaction['total_amount'] - $transaction['discount_amount'];
$pdf->Cell(120, 10, '', 0);
$pdf->Cell(30, 10, 'Final Total:', 0);
$pdf->Cell(40, 10, '$' . number_format($finalTotal, 2), 0, 0, 'R');

// Add payment information
$pdf->Ln(15);
$pdf->Cell(0, 10, 'Payment Information:', 0, 1);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, 'Paid Amount: $' . number_format($transaction['paid_amount'], 2), 0, 1);
$pdf->Cell(0, 10, 'Change Given: $' . number_format($transaction['change_amount'], 2), 0, 1);

// Add footer text
$pdf->Ln(10);
$pdf->SetFont('Arial', 'I', 10);
$pdf->Cell(0, 10, 'Thank you for your purchase!', 0, 1, 'C');

// Output the PDF
$pdf->Output('Invoice-' . $transaction_id . '.pdf', 'D');
?>