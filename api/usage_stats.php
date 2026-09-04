<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/middleware.php';

$session = authenticateRequest(false);
$email = $_GET['email'] ?? ($session ? $session['email'] : '');

// Only the hardcoded admin is authorized to see global Claude API usage logs
if (strcasecmp($email, 'ranjith.mecs@gmail.com') !== 0) {
    echo json_encode(["error" => "Unauthorized access to audit logs."]);
    exit;
}

$stats = JsonDB::getUsageStats();
echo json_encode(array_merge(["success" => true], $stats));
?>
