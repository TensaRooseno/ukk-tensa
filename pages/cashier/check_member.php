<?php
require_once '../../include/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Validate phone number
if (!isset($_POST['phone_number']) || empty($_POST['phone_number'])) {
    echo json_encode(['success' => false, 'message' => 'Phone number is required']);
    exit;
}

$phone_number = mysqli_real_escape_string($conn, $_POST['phone_number']);

// Search for member
$query = "SELECT * FROM members WHERE phone_number = '$phone_number'";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) > 0) {
    // Member found
    $member = mysqli_fetch_assoc($result);
    echo json_encode([
        'success' => true,
        'found' => true,
        'member' => $member
    ]);
} else {
    // Member not found
    echo json_encode([
        'success' => true,
        'found' => false,
        'message' => 'No member found with this phone number'
    ]);
}
?>