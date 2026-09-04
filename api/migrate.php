<?php
// Automated Zero-Data-Loss Migration Script
// Migrates users, projects, and token logs from database.json to SQLite/PDO database.

header("Content-Type: application/json");

require_once __DIR__ . '/db_pdo.php';

$jsonPath = __DIR__ . '/uploads/database.json';
if (!file_exists($jsonPath)) {
    // Check if it's in frontend/dist for local environments
    if (file_exists(__DIR__ . '/../frontend/dist/database.json')) {
        $jsonPath = __DIR__ . '/../frontend/dist/database.json';
    }
}

if (!file_exists($jsonPath)) {
    echo json_encode(["status" => "skipped", "message" => "No legacy database.json file found to migrate."]);
    exit;
}

try {
    $pdo = Database::getConnection();
    $raw = file_get_contents($jsonPath);
    $data = json_decode($raw, true);

    if (!$data) {
        echo json_encode(["error" => "Invalid or corrupt database.json."]);
        exit;
    }

    $pdo->beginTransaction();

    $userCount = 0;
    $projectCount = 0;
    $logCount = 0;

    // 1. Migrate Users
    if (isset($data['users']) && is_array($data['users'])) {
        $stmtUser = $pdo->prepare("
            INSERT OR IGNORE INTO users (id, name, email, password, credit_limit, created_at)
            VALUES (:id, :name, :email, :password, :credit_limit, :created_at)
        ");

        foreach ($data['users'] as $u) {
            $stmtUser->execute([
                ':id' => $u['id'] ?? uniqid(),
                ':name' => $u['name'] ?? '',
                ':email' => $u['email'] ?? '',
                ':password' => $u['password'] ?? '',
                ':credit_limit' => $u['credit_limit'] ?? 3.00,
                ':created_at' => $u['created_at'] ?? date('Y-m-d H:i:s')
            ]);
            $userCount++;
        }
    }

    // 2. Migrate Projects
    if (isset($data['projects']) && is_array($data['projects'])) {
        $stmtProj = $pdo->prepare("
            INSERT OR IGNORE INTO projects (id, email, title, domain, payload, created_at, updated_at)
            VALUES (:id, :email, :title, :domain, :payload, :created_at, :updated_at)
        ");

        foreach ($data['projects'] as $p) {
            $payloadStr = is_string($p['payload']) ? $p['payload'] : json_encode($p['payload']);
            $stmtProj->execute([
                ':id' => $p['id'] ?? uniqid(),
                ':email' => $p['email'] ?? '',
                ':title' => $p['title'] ?? '',
                ':domain' => $p['domain'] ?? '',
                ':payload' => $payloadStr,
                ':created_at' => $p['created_at'] ?? date('Y-m-d H:i:s'),
                ':updated_at' => $p['updated_at'] ?? date('Y-m-d H:i:s')
            ]);
            $projectCount++;
        }
    }

    // 3. Migrate Usage Logs
    if (isset($data['usage_logs']) && is_array($data['usage_logs'])) {
        $stmtLog = $pdo->prepare("
            INSERT OR IGNORE INTO usage_logs (id, email, action, input_tokens, output_tokens, cost, created_at)
            VALUES (:id, :email, :action, :input_tokens, :output_tokens, :cost, :created_at)
        ");

        foreach ($data['usage_logs'] as $l) {
            $stmtLog->execute([
                ':id' => $l['id'] ?? uniqid(),
                ':email' => $l['email'] ?? '',
                ':action' => $l['action'] ?? '',
                ':input_tokens' => (int)($l['input_tokens'] ?? 0),
                ':output_tokens' => (int)($l['output_tokens'] ?? 0),
                ':cost' => (float)($l['cost'] ?? 0.0),
                ':created_at' => $l['created_at'] ?? date('Y-m-d H:i:s')
            ]);
            $logCount++;
        }
    }

    // 4. Migrate Funded Credits Setting
    if (isset($data['anthropic_funded_credits'])) {
        $stmtSetting = $pdo->prepare("
            INSERT OR REPLACE INTO settings (key, value) VALUES ('anthropic_funded_credits', :val)
        ");
        $stmtSetting->execute([':val' => (string)$data['anthropic_funded_credits']]);
    }

    $pdo->commit();

    echo json_encode([
        "success" => true,
        "message" => "Database migration completed seamlessly!",
        "stats" => [
            "migrated_users" => $userCount,
            "migrated_projects" => $projectCount,
            "migrated_usage_logs" => $logCount
        ]
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        "error" => "Migration failed: " . $e->getMessage()
    ]);
}
?>
