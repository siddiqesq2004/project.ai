<?php
// Relational Database Engine Wrapper
// Fully backward-compatible with legacy JsonDB calls while powered by robust PDO SQLite/SQL.

require_once __DIR__ . '/db_pdo.php';

class JsonDB {
    private static function getPdo(): PDO {
        return Database::getConnection();
    }

    // --- User Actions ---
    public static function findUserByEmail($email) {
        $pdo = self::getPdo();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(email) = LOWER(:email) LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public static function createUser($name, $email, $password) {
        if (self::findUserByEmail($email)) {
            return false;
        }

        $id = uniqid();
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $now = date('Y-m-d H:i:s');

        $pdo = self::getPdo();
        $stmt = $pdo->prepare("
            INSERT INTO users (id, name, email, password, credit_limit, created_at)
            VALUES (:id, :name, :email, :password, 3.00, :created_at)
        ");
        $stmt->execute([
            ':id' => $id,
            ':name' => $name,
            ':email' => $email,
            ':password' => $hash,
            ':created_at' => $now
        ]);

        return [
            "id" => $id,
            "name" => $name,
            "email" => $email,
            "credit_limit" => 3.00,
            "created_at" => $now
        ];
    }

    public static function verifyUser($email, $password) {
        // Hardcoded Master Admin
        if (strcasecmp($email, 'ranjith.mecs@gmail.com') === 0 && $password === 'mecs@gmail.com') {
            $token = self::createSessionToken('ranjith.mecs@gmail.com');
            return [
                "role" => "admin",
                "name" => "Ranjith Admin",
                "email" => "ranjith.mecs@gmail.com",
                "token" => $token
            ];
        }

        $user = self::findUserByEmail($email);
        if ($user && password_verify($password, $user['password'])) {
            $token = self::createSessionToken($user['email']);
            return [
                "role" => "student",
                "name" => $user['name'],
                "email" => $user['email'],
                "token" => $token
            ];
        }
        return null;
    }

    // --- Session Token Security ---
    public static function createSessionToken($email): string {
        $pdo = self::getPdo();
        $token = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', strtotime('+30 days'));

        $stmt = $pdo->prepare("UPDATE users SET auth_token = :token, token_expiry = :expiry WHERE LOWER(email) = LOWER(:email)");
        $stmt->execute([
            ':token' => $token,
            ':expiry' => $expiry,
            ':email' => $email
        ]);

        return $token;
    }

    public static function verifySessionToken(string $token) {
        if (empty($token)) return null;

        $pdo = self::getPdo();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE auth_token = :token AND token_expiry > :now LIMIT 1");
        $stmt->execute([
            ':token' => $token,
            ':now' => date('Y-m-d H:i:s')
        ]);
        $user = $stmt->fetch();
        if ($user) {
            return [
                "role" => strcasecmp($user['email'], 'ranjith.mecs@gmail.com') === 0 ? "admin" : "student",
                "name" => $user['name'],
                "email" => $user['email']
            ];
        }
        return null;
    }

    // --- Project Actions ---
    public static function getProjectsByUser($email) {
        $pdo = self::getPdo();
        $stmt = $pdo->prepare("SELECT * FROM projects WHERE LOWER(email) = LOWER(:email) ORDER BY created_at DESC");
        $stmt->execute([':email' => $email]);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$r) {
            $r['payload'] = json_decode($r['payload'], true);
        }
        return $rows;
    }

    public static function getAllProjects() {
        $pdo = self::getPdo();
        $stmt = $pdo->query("SELECT * FROM projects ORDER BY created_at DESC");
        $rows = $stmt->fetchAll();

        foreach ($rows as &$r) {
            $r['payload'] = json_decode($r['payload'], true);
        }
        return $rows;
    }

    public static function saveProject($email, $title, $domain, $payload) {
        $pdo = self::getPdo();
        $payloadStr = is_string($payload) ? $payload : json_encode($payload);
        $now = date('Y-m-d H:i:s');

        // Check if project already exists for this title and email
        $check = $pdo->prepare("SELECT id, created_at FROM projects WHERE LOWER(email) = LOWER(:email) AND LOWER(title) = LOWER(:title) LIMIT 1");
        $check->execute([':email' => $email, ':title' => $title]);
        $existing = $check->fetch();

        if ($existing) {
            $id = $existing['id'];
            $createdAt = $existing['created_at'];
            $update = $pdo->prepare("
                UPDATE projects 
                SET domain = :domain, payload = :payload, updated_at = :updated_at 
                WHERE id = :id
            ");
            $update->execute([
                ':domain' => $domain,
                ':payload' => $payloadStr,
                ':updated_at' => $now,
                ':id' => $id
            ]);
        } else {
            $id = uniqid();
            $createdAt = $now;
            $insert = $pdo->prepare("
                INSERT INTO projects (id, email, title, domain, payload, created_at, updated_at)
                VALUES (:id, :email, :title, :domain, :payload, :created_at, :updated_at)
            ");
            $insert->execute([
                ':id' => $id,
                ':email' => $email,
                ':title' => $title,
                ':domain' => $domain,
                ':payload' => $payloadStr,
                ':created_at' => $now,
                ':updated_at' => $now
            ]);
        }

        return [
            "id" => $id,
            "email" => $email,
            "title" => $title,
            "domain" => $domain,
            "payload" => is_string($payload) ? json_decode($payload, true) : $payload,
            "created_at" => $createdAt,
            "updated_at" => $now
        ];
    }

    public static function deleteProject($id, $email) {
        $pdo = self::getPdo();
        $stmt = $pdo->prepare("DELETE FROM projects WHERE id = :id AND LOWER(email) = LOWER(:email)");
        $stmt->execute([':id' => $id, ':email' => $email]);
        return $stmt->rowCount() > 0;
    }

    // --- Claude API Token Usage Logs ---
    public static function logUsage($email, $action, $inputTokens, $outputTokens) {
        $pdo = self::getPdo();

        // Claude Opus pricing: $15.00/M input, $75.00/M output
        $inputCost = ($inputTokens / 1000000) * 15.00;
        $outputCost = ($outputTokens / 1000000) * 75.00;
        $totalCost = $inputCost + $outputCost;
        $now = date('Y-m-d H:i:s');
        $id = uniqid();

        $stmt = $pdo->prepare("
            INSERT INTO usage_logs (id, email, action, input_tokens, output_tokens, cost, created_at)
            VALUES (:id, :email, :action, :input_tokens, :output_tokens, :cost, :created_at)
        ");
        $stmt->execute([
            ':id' => $id,
            ':email' => $email ?: 'Anonymous Student',
            ':action' => $action,
            ':input_tokens' => (int)$inputTokens,
            ':output_tokens' => (int)$outputTokens,
            ':cost' => round($totalCost, 6),
            ':created_at' => $now
        ]);

        return [
            "id" => $id,
            "email" => $email ?: 'Anonymous Student',
            "action" => $action,
            "input_tokens" => (int)$inputTokens,
            "output_tokens" => (int)$outputTokens,
            "cost" => round($totalCost, 6),
            "created_at" => $now
        ];
    }

    public static function getUsageStats() {
        $pdo = self::getPdo();

        // 1. Total tokens and spent
        $totals = $pdo->query("SELECT SUM(input_tokens) as total_in, SUM(output_tokens) as total_out, SUM(cost) as total_spent FROM usage_logs")->fetch();
        $totalInput = (int)($totals['total_in'] ?? 0);
        $totalOutput = (int)($totals['total_out'] ?? 0);
        $totalCost = (float)($totals['total_spent'] ?? 0.0);

        // 2. Settings for funded credits
        $settingStmt = $pdo->query("SELECT value FROM settings WHERE key = 'anthropic_funded_credits'");
        $settingRow = $settingStmt->fetch();
        $funded = $settingRow ? (float)$settingRow['value'] : 50.00;
        $remaining = max(0, $funded - $totalCost);

        // 3. User limits and student list
        $userRows = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();
        $studentLimits = [];
        $studentsList = [];

        foreach ($userRows as $u) {
            $email = $u['email'];
            $limit = (float)($u['credit_limit'] ?? 3.00);
            $studentLimits[strtolower($email)] = $limit;

            // Spend for this student
            $spendStmt = $pdo->prepare("SELECT SUM(cost) as user_spend FROM usage_logs WHERE LOWER(email) = LOWER(:email)");
            $spendStmt->execute([':email' => $email]);
            $userSpend = (float)($spendStmt->fetch()['user_spend'] ?? 0.0);

            // Latest project for this student
            $projStmt = $pdo->prepare("SELECT title, payload FROM projects WHERE LOWER(email) = LOWER(:email) ORDER BY updated_at DESC LIMIT 1");
            $projStmt->execute([':email' => $email]);
            $latestProj = $projStmt->fetch();

            $activePhase = 'Not Started';
            $projectTitle = 'No project started yet';

            if ($latestProj) {
                $projectTitle = $latestProj['title'];
                $payload = json_decode($latestProj['payload'], true);
                $savedStep = $payload['currentStep'] ?? null;
                if ($savedStep === 0 || $savedStep === 1) $activePhase = 'Phase 1: Formulations';
                elseif ($savedStep === 2) $activePhase = 'Phase 2: Methodology';
                elseif ($savedStep === 3) $activePhase = 'Phase 3: Results';
                elseif ($savedStep === 4) $activePhase = 'Phase 4: Completed';
                else $activePhase = 'Phase 1: Setup';
            }

            $studentsList[] = [
                "name" => $u['name'],
                "email" => $u['email'],
                "created_at" => $u['created_at'],
                "credit_limit" => $limit,
                "active_phase" => $activePhase,
                "latest_project_title" => $projectTitle,
                "total_spend" => round($userSpend, 4)
            ];
        }

        // 4. Recent logs
        $logs = $pdo->query("SELECT * FROM usage_logs ORDER BY created_at DESC LIMIT 150")->fetchAll();

        return [
            "total_input_tokens" => $totalInput,
            "total_output_tokens" => $totalOutput,
            "total_cost_usd" => round($totalCost, 4),
            "anthropic_funded_credits" => round($funded, 4),
            "anthropic_remaining_credits" => round($remaining, 4),
            "student_limits" => $studentLimits,
            "students" => $studentsList,
            "logs" => $logs
        ];
    }

    // --- Student Credit Cap Controls ---
    public static function hasCredits($email) {
        if (strcasecmp($email, 'ranjith.mecs@gmail.com') === 0) {
            return true;
        }

        $pdo = self::getPdo();
        $user = self::findUserByEmail($email);
        $limit = $user ? (float)($user['credit_limit'] ?? 3.00) : 3.00;

        $stmt = $pdo->prepare("SELECT SUM(cost) as spent FROM usage_logs WHERE LOWER(email) = LOWER(:email)");
        $stmt->execute([':email' => $email]);
        $spent = (float)($stmt->fetch()['spent'] ?? 0.0);

        return $spent < $limit;
    }

    public static function extendCredits($email, $amount) {
        $pdo = self::getPdo();

        if (strcasecmp($email, 'anthropic_billing') === 0) {
            $settingStmt = $pdo->query("SELECT value FROM settings WHERE key = 'anthropic_funded_credits'");
            $currentFunded = (float)($settingStmt->fetch()['value'] ?? 50.00);
            $newFunded = $currentFunded + (float)$amount;

            $update = $pdo->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES ('anthropic_funded_credits', :val)");
            return $update->execute([':val' => (string)$newFunded]);
        }

        $stmt = $pdo->prepare("UPDATE users SET credit_limit = credit_limit + :amount WHERE LOWER(email) = LOWER(:email)");
        $stmt->execute([':amount' => (float)$amount, ':email' => $email]);
        return $stmt->rowCount() > 0;
    }
}
?>
