<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

require_once __DIR__ . '/db.php';

$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);

$action = $input['action'] ?? '';

if ($action === 'signup') {
    $name = trim($input['name'] ?? '');
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $captchaAnswer = trim($input['captcha_answer'] ?? '');
    $captchaInput = trim($input['captcha_input'] ?? '');

    if (empty($name) || empty($email) || empty($password)) {
        echo json_encode(["error" => "All signup fields are required."]);
        exit;
    }

    if ($captchaInput !== $captchaAnswer) {
        echo json_encode(["error" => "Incorrect security CAPTCHA answer. Please try again."]);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(["error" => "Invalid email address format."]);
        exit;
    }

    $newUser = JsonDB::createUser($name, $email, $password);
    if ($newUser) {
        echo json_encode([
            "success" => true,
            "user" => [
                "role" => "student",
                "name" => $newUser['name'],
                "email" => $newUser['email']
            ]
        ]);
    } else {
        echo json_encode(["error" => "An account with this email already exists."]);
    }
} elseif ($action === 'login') {
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';

    if (empty($email) || empty($password)) {
        echo json_encode(["error" => "Email and password are required."]);
        exit;
    }

    $session = JsonDB::verifyUser($email, $password);
    if ($session) {
        echo json_encode([
            "success" => true,
            "user" => $session
        ]);
    } else {
        echo json_encode(["error" => "Invalid email or password."]);
    }
} else {
    echo json_encode(["error" => "Invalid action."]);
}
?>
