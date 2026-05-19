<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

require_once __DIR__ . '/db.php';

$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);

$adminEmail = $input['admin_email'] ?? '';
$studentEmail = $input['student_email'] ?? '';
$amount = $input['amount'] ?? 3.00;

// Verify MECS Admin Auth
if (strcasecmp($adminEmail, 'ranjith.mecs@gmail.com') !== 0) {
    echo json_encode(["error" => "Unauthorized access to credit allocations."]);
    exit;
}

if (empty($studentEmail)) {
    echo json_encode(["error" => "Student email is required."]);
    exit;
}

$success = JsonDB::extendCredits($studentEmail, $amount);
if ($success) {
    echo json_encode(["success" => true, "message" => "Successfully allocated +$" . number_format($amount, 2) . " credit limit to " . $studentEmail]);
} else {
    echo json_encode(["error" => "Failed to update credits. Student email not found in database."]);
}
?>
