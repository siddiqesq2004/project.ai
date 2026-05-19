<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

require_once __DIR__ . '/db.php';

$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);

$action = $input['action'] ?? $_GET['action'] ?? '';
$email = trim($input['email'] ?? $_GET['email'] ?? '');

if (empty($email)) {
    echo json_encode(["error" => "Authorization error. User email is required."]);
    exit;
}

if ($action === 'save') {
    $title = trim($input['title'] ?? '');
    $domain = trim($input['domain'] ?? '');
    $payload = $input['payload'] ?? null; // Generator state JSON

    if (empty($title) || empty($domain) || empty($payload)) {
        echo json_encode(["error" => "Project title, domain, and data are required to save."]);
        exit;
    }

    $project = JsonDB::saveProject($email, $title, $domain, $payload);
    echo json_encode(["success" => true, "project" => $project]);
} elseif ($action === 'list') {
    // If user is Admin, list ALL projects. Otherwise, list USER's projects.
    if (strcasecmp($email, 'ranjith.mecs@gmail.com') === 0) {
        $projects = JsonDB::getAllProjects();
    } else {
        $projects = JsonDB::getProjectsByUser($email);
    }
    echo json_encode(["success" => true, "projects" => $projects]);
} elseif ($action === 'delete') {
    $projectId = $input['id'] ?? '';
    if (empty($projectId)) {
        echo json_encode(["error" => "Project ID is required to delete."]);
        exit;
    }

    $deleted = JsonDB::deleteProject($projectId, $email);
    if ($deleted) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["error" => "Project not found or unauthorized to delete."]);
    }
} else {
    echo json_encode(["error" => "Invalid action."]);
}
?>
