<?php
// Simple, zero-configuration file-based JSON database for Hostinger deployment.
// Stores all users and projects inside the writable uploads folder.

class JsonDB {
    private static $dbPath = __DIR__ . '/uploads/database.json';

    private static function init() {
        if (!file_exists(__DIR__ . '/uploads')) {
            mkdir(__DIR__ . '/uploads', 0777, true);
        }
        if (!file_exists(self::$dbPath)) {
            $initialData = [
                "users" => [],
                "projects" => []
            ];
            file_put_contents(self::$dbPath, json_encode($initialData, JSON_PRETTY_PRINT));
        }
    }

    private static function read() {
        self::init();
        $content = file_get_contents(self::$dbPath);
        $data = json_decode($content, true);
        if (!$data) {
            return ["users" => [], "projects" => []];
        }
        return $data;
    }

    private static function write($data) {
        self::init();
        file_put_contents(self::$dbPath, json_encode($data, JSON_PRETTY_PRINT));
    }

    // --- User Actions ---
    public static function findUserByEmail($email) {
        $data = self::read();
        foreach ($data['users'] as $user) {
            if (strcasecmp($user['email'], $email) === 0) {
                return $user;
            }
        }
        return null;
    }

    public static function createUser($name, $email, $password) {
        $data = self::read();
        
        // Check if user already exists
        if (self::findUserByEmail($email)) {
            return false;
        }

        $newUser = [
            "id" => uniqid(),
            "name" => $name,
            "email" => $email,
            "password" => password_hash($password, PASSWORD_DEFAULT),
            "created_at" => date('Y-m-d H:i:s')
        ];

        $data['users'][] = $newUser;
        self::write($data);
        return $newUser;
    }

    public static function verifyUser($email, $password) {
        // Handle Hardcoded Admin Account
        if (strcasecmp($email, 'ranjith.mecs@gmail.com') === 0 && $password === 'mecs@gmail.com') {
            return [
                "role" => "admin",
                "name" => "Ranjith Admin",
                "email" => "ranjith.mecs@gmail.com"
            ];
        }

        $user = self::findUserByEmail($email);
        if ($user && password_verify($password, $user['password'])) {
            return [
                "role" => "student",
                "name" => $user['name'],
                "email" => $user['email']
            ];
        }
        return null;
    }

    // --- Project Actions ---
    public static function getProjectsByUser($email) {
        $data = self::read();
        $userProjects = [];
        foreach ($data['projects'] as $proj) {
            if (strcasecmp($proj['email'], $email) === 0) {
                $userProjects[] = $proj;
            }
        }
        // Return reverse sorted by creation time
        usort($userProjects, function($a, $b) {
            return strcmp($b['created_at'], $a['created_at']);
        });
        return $userProjects;
    }

    public static function getAllProjects() {
        $data = self::read();
        $all = $data['projects'];
        usort($all, function($a, $b) {
            return strcmp($b['created_at'], $a['created_at']);
        });
        return $all;
    }

    public static function saveProject($email, $title, $domain, $payload) {
        $data = self::read();
        
        // Check if project with same title already exists for this user to update it
        $foundIndex = -1;
        foreach ($data['projects'] as $idx => $proj) {
            if (strcasecmp($proj['email'], $email) === 0 && strcasecmp($proj['title'], $title) === 0) {
                $foundIndex = $idx;
                break;
            }
        }

        $projectObj = [
            "id" => ($foundIndex !== -1) ? $data['projects'][$foundIndex]['id'] : uniqid(),
            "email" => $email,
            "title" => $title,
            "domain" => $domain,
            "payload" => $payload, // Full generator state json
            "created_at" => ($foundIndex !== -1) ? $data['projects'][$foundIndex]['created_at'] : date('Y-m-d H:i:s'),
            "updated_at" => date('Y-m-d H:i:s')
        ];

        if ($foundIndex !== -1) {
            $data['projects'][$foundIndex] = $projectObj;
        } else {
            $data['projects'][] = $projectObj;
        }

        self::write($data);
        return $projectObj;
    }

    public static function deleteProject($id, $email) {
        $data = self::read();
        $filtered = [];
        $deleted = false;
        foreach ($data['projects'] as $proj) {
            if ($proj['id'] === $id && strcasecmp($proj['email'], $email) === 0) {
                $deleted = true;
                continue;
            }
            $filtered[] = $proj;
        }
        $data['projects'] = $filtered;
        self::write($data);
        return $deleted;
    }

    // --- Claude API Token Usage Logs ---
    public static function logUsage($email, $action, $inputTokens, $outputTokens) {
        $data = self::read();
        
        // Claude Opus pricing: $15.00/M input, $75.00/M output
        $inputCost = ($inputTokens / 1000000) * 15.00;
        $outputCost = ($outputTokens / 1000000) * 75.00;
        $totalCost = $inputCost + $outputCost;

        $newLog = [
            "id" => uniqid(),
            "email" => $email ?: 'Anonymous Student',
            "action" => $action,
            "input_tokens" => (int)$inputTokens,
            "output_tokens" => (int)$outputTokens,
            "cost" => round($totalCost, 6),
            "created_at" => date('Y-m-d H:i:s')
        ];

        if (!isset($data['usage_logs'])) {
            $data['usage_logs'] = [];
        }
        $data['usage_logs'][] = $newLog;
        self::write($data);
        return $newLog;
    }

    public static function getUsageStats() {
        $data = self::read();
        $logs = isset($data['usage_logs']) ? $data['usage_logs'] : [];
        
        $totalInput = 0;
        $totalOutput = 0;
        $totalCost = 0.0;
        
        foreach ($logs as $log) {
            $totalInput += $log['input_tokens'];
            $totalOutput += $log['output_tokens'];
            $totalCost += $log['cost'];
        }
        
        // Compute active limit specs per student and collect registered student profiles
        $studentLimits = [];
        $studentsList = [];
        if (isset($data['users'])) {
            foreach ($data['users'] as $u) {
                $email = $u['email'];
                $limit = isset($u['credit_limit']) ? (float)$u['credit_limit'] : 3.00;
                $studentLimits[strtolower($email)] = $limit;

                // Determine active projects and active phase
                $userProjects = [];
                if (isset($data['projects'])) {
                    foreach ($data['projects'] as $proj) {
                        if (strcasecmp($proj['email'], $email) === 0) {
                            $userProjects[] = $proj;
                        }
                    }
                }

                $latestProject = null;
                $activePhase = 'Not Started';
                if (count($userProjects) > 0) {
                    usort($userProjects, function($a, $b) {
                        return strcmp($b['updated_at'] ?? $b['created_at'], $a['updated_at'] ?? $a['created_at']);
                    });
                    $latestProject = $userProjects[0];
                    
                    // Determine phase based on currentStep saved in payload
                    $savedStep = $latestProject['payload']['currentStep'] ?? null;
                    if ($savedStep === 0) {
                        $activePhase = 'Phase 1: Setup';
                    } elseif ($savedStep === 1) {
                        $activePhase = 'Phase 1: Formulations';
                    } elseif ($savedStep === 2) {
                        $activePhase = 'Phase 2: Methodology';
                    } elseif ($savedStep === 3) {
                        $activePhase = 'Phase 3: Results';
                    } elseif ($savedStep === 4) {
                        $activePhase = 'Phase 4: Completed';
                    } else {
                        $activePhase = 'Phase 1: Setup';
                    }
                }

                // Calculate cumulative spend for this user
                $userSpend = 0.0;
                foreach ($logs as $log) {
                    if (strcasecmp($log['email'], $email) === 0) {
                        $userSpend += (float)$log['cost'];
                    }
                }

                $studentsList[] = [
                    "name" => $u['name'],
                    "email" => $u['email'],
                    "created_at" => $u['created_at'],
                    "credit_limit" => $limit,
                    "active_phase" => $activePhase,
                    "latest_project_title" => $latestProject ? $latestProject['title'] : 'No project started yet',
                    "total_spend" => round($userSpend, 4)
                ];
            }
        }

        $funded = isset($data['anthropic_funded_credits']) ? (float)$data['anthropic_funded_credits'] : 50.00;
        $remaining = $funded - $totalCost;
        if ($remaining < 0) $remaining = 0;

        return [
            "total_input_tokens" => $totalInput,
            "total_output_tokens" => $totalOutput,
            "total_cost_usd" => round($totalCost, 4),
            "anthropic_funded_credits" => round($funded, 4),
            "anthropic_remaining_credits" => round($remaining, 4),
            "student_limits" => $studentLimits,
            "students" => $studentsList,
            "logs" => array_slice(array_reverse($logs), 0, 150) // Return last 150 logs
        ];
    }

    // --- Student Credit Cap Controls ---
    public static function hasCredits($email) {
        // MECS Admin has infinite credits
        if (strcasecmp($email, 'ranjith.mecs@gmail.com') === 0) {
            return true;
        }

        $data = self::read();
        $logs = isset($data['usage_logs']) ? $data['usage_logs'] : [];
        
        $spent = 0.0;
        foreach ($logs as $log) {
            if (strcasecmp($log['email'], $email) === 0) {
                $spent += (float)$log['cost'];
            }
        }

        // Default limit is $3.00
        $limit = 3.00;
        foreach ($data['users'] as $u) {
            if (strcasecmp($u['email'], $email) === 0) {
                if (isset($u['credit_limit'])) {
                    $limit = (float)$u['credit_limit'];
                }
                break;
            }
        }

        return $spent < $limit;
    }

    public static function extendCredits($email, $amount) {
        $data = self::read();
        $updated = false;

        if (strcasecmp($email, 'anthropic_billing') === 0) {
            $currentFunded = isset($data['anthropic_funded_credits']) ? (float)$data['anthropic_funded_credits'] : 50.00;
            $data['anthropic_funded_credits'] = $currentFunded + (float)$amount;
            $updated = true;
        } else {
            foreach ($data['users'] as &$u) {
                if (strcasecmp($u['email'], $email) === 0) {
                    $currentLimit = isset($u['credit_limit']) ? (float)$u['credit_limit'] : 3.00;
                    $u['credit_limit'] = $currentLimit + (float)$amount;
                    $updated = true;
                    break;
                }
            }
        }

        if ($updated) {
            self::write($data);
        }
        return $updated;
    }
}
?>
