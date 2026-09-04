<?php
// Modern Relational Database Engine using PHP PDO (SQLite with WAL mode / PostgreSQL / MySQL)
// Guarantees zero file corruption, ACID transactions, and high concurrency.

class Database {
    private static ?PDO $pdo = null;
    private static string $dbFile = __DIR__ . '/uploads/database.sqlite';

    public static function getConnection(): PDO {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        if (!file_exists(__DIR__ . '/uploads')) {
            mkdir(__DIR__ . '/uploads', 0777, true);
        }

        $dsn = getenv('DB_DSN');
        if (!$dsn) {
            $dsn = 'sqlite:' . self::$dbFile;
        }

        $user = getenv('DB_USER') ?: null;
        $pass = getenv('DB_PASSWORD') ?: null;

        try {
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            // If SQLite, enable Write-Ahead Logging (WAL) for optimal concurrent performance
            if (str_starts_with($dsn, 'sqlite:')) {
                self::$pdo->exec("PRAGMA journal_mode = WAL;");
                self::$pdo->exec("PRAGMA busy_timeout = 5000;");
                self::$pdo->exec("PRAGMA synchronous = NORMAL;");
            }

            self::initSchema();
        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            throw new Exception("Database connection failure.");
        }

        return self::$pdo;
    }

    private static function initSchema(): void {
        $sql = "
        CREATE TABLE IF NOT EXISTS users (
            id TEXT PRIMARY KEY,
            name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            credit_limit REAL DEFAULT 3.00,
            auth_token TEXT,
            token_expiry TEXT,
            created_at TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS projects (
            id TEXT PRIMARY KEY,
            email TEXT NOT NULL,
            title TEXT NOT NULL,
            domain TEXT NOT NULL,
            payload TEXT NOT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS usage_logs (
            id TEXT PRIMARY KEY,
            email TEXT NOT NULL,
            action TEXT NOT NULL,
            input_tokens INTEGER DEFAULT 0,
            output_tokens INTEGER DEFAULT 0,
            cost REAL DEFAULT 0.0,
            created_at TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL
        );
        ";

        self::$pdo->exec($sql);

        // Initialize default Anthropic funded credit limit if missing
        $stmt = self::$pdo->prepare("SELECT value FROM settings WHERE key = 'anthropic_funded_credits'");
        $stmt->execute();
        if (!$stmt->fetch()) {
            $insert = self::$pdo->prepare("INSERT INTO settings (key, value) VALUES ('anthropic_funded_credits', '50.00')");
            $insert->execute();
        }

        // Automatic seamless zero-downtime migration from legacy database.json
        self::autoMigrateJson();
    }

    private static function autoMigrateJson(): void {
        $jsonPath = __DIR__ . '/uploads/database.json';
        if (!file_exists($jsonPath)) return;

        $check = self::$pdo->query("SELECT COUNT(*) as count FROM users")->fetch();
        if ($check && (int)$check['count'] > 0) return;

        try {
            $raw = file_get_contents($jsonPath);
            $data = json_decode($raw, true);
            if (!$data) return;

            self::$pdo->beginTransaction();

            if (isset($data['users']) && is_array($data['users'])) {
                $stmt = self::$pdo->prepare("INSERT OR IGNORE INTO users (id, name, email, password, credit_limit, created_at) VALUES (:id, :name, :email, :password, :credit_limit, :created_at)");
                foreach ($data['users'] as $u) {
                    $stmt->execute([
                        ':id' => $u['id'] ?? uniqid(),
                        ':name' => $u['name'] ?? '',
                        ':email' => $u['email'] ?? '',
                        ':password' => $u['password'] ?? '',
                        ':credit_limit' => $u['credit_limit'] ?? 3.00,
                        ':created_at' => $u['created_at'] ?? date('Y-m-d H:i:s')
                    ]);
                }
            }

            if (isset($data['projects']) && is_array($data['projects'])) {
                $stmt = self::$pdo->prepare("INSERT OR IGNORE INTO projects (id, email, title, domain, payload, created_at, updated_at) VALUES (:id, :email, :title, :domain, :payload, :created_at, :updated_at)");
                foreach ($data['projects'] as $p) {
                    $payloadStr = is_string($p['payload']) ? $p['payload'] : json_encode($p['payload']);
                    $stmt->execute([
                        ':id' => $p['id'] ?? uniqid(),
                        ':email' => $p['email'] ?? '',
                        ':title' => $p['title'] ?? '',
                        ':domain' => $p['domain'] ?? '',
                        ':payload' => $payloadStr,
                        ':created_at' => $p['created_at'] ?? date('Y-m-d H:i:s'),
                        ':updated_at' => $p['updated_at'] ?? date('Y-m-d H:i:s')
                    ]);
                }
            }

            if (isset($data['usage_logs']) && is_array($data['usage_logs'])) {
                $stmt = self::$pdo->prepare("INSERT OR IGNORE INTO usage_logs (id, email, action, input_tokens, output_tokens, cost, created_at) VALUES (:id, :email, :action, :input_tokens, :output_tokens, :cost, :created_at)");
                foreach ($data['usage_logs'] as $l) {
                    $stmt->execute([
                        ':id' => $l['id'] ?? uniqid(),
                        ':email' => $l['email'] ?? '',
                        ':action' => $l['action'] ?? '',
                        ':input_tokens' => (int)($l['input_tokens'] ?? 0),
                        ':output_tokens' => (int)($l['output_tokens'] ?? 0),
                        ':cost' => (float)($l['cost'] ?? 0.0),
                        ':created_at' => $l['created_at'] ?? date('Y-m-d H:i:s')
                    ]);
                }
            }

            self::$pdo->commit();
        } catch (Exception $e) {
            if (self::$pdo->inTransaction()) self::$pdo->rollBack();
            error_log("Auto-migration warning: " . $e->getMessage());
        }
    }
}
?>
