<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");
set_time_limit(300);
ini_set('memory_limit', '256M');

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

require_once __DIR__ . '/config.php';
$apiKey = CLAUDE_API_KEY;
$title = $_POST['title'] ?? '';
$domain = $_POST['domain'] ?? '';
$requirements = $_POST['requirements'] ?? 'None';
$email = $_POST['email'] ?? '';

require_once __DIR__ . '/db.php';
if (!JsonDB::hasCredits($email)) {
    echo json_encode(["error" => "You have reached your allocated student API credit limit ($3.00). Please contact Rademics for further process."]);
    exit;
}

if (empty($title) || empty($domain)) {
    echo json_encode(["error" => "Title and Domain are required."]);
    exit;
}

if (!file_exists('uploads')) mkdir('uploads', 0777, true);
$reportTemplatePath = null;
$pptTemplatePath = null;

if (isset($_FILES['reportTemplate']) && $_FILES['reportTemplate']['error'] === UPLOAD_ERR_OK) {
    $reportTemplatePath = 'uploads/' . time() . '_' . $_FILES['reportTemplate']['name'];
    move_uploaded_file($_FILES['reportTemplate']['tmp_name'], $reportTemplatePath);
}

if (isset($_FILES['pptTemplate']) && $_FILES['pptTemplate']['error'] === UPLOAD_ERR_OK) {
    $pptTemplatePath = 'uploads/' . time() . '_' . $_FILES['pptTemplate']['name'];
    move_uploaded_file($_FILES['pptTemplate']['tmp_name'], $pptTemplatePath);
}

function callClaude($system, $user, $apiKey, $email, $logAction, $maxRetries = 3) {
    $data = [
        "model" => "claude-sonnet-4-20250514",
        "max_tokens" => 8192,
        "system" => $system,
        "messages" => [["role" => "user", "content" => $user]]
    ];

    $payload = json_encode($data);
    $headers = [
        'x-api-key: ' . $apiKey,
        'anthropic-version: 2023-06-01',
        'Content-Type: application/json'
    ];

    $lastError = "";

    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_errno($ch) ? curl_error($ch) : "";
        curl_close($ch);

        if ($httpCode === 200) {
            $decoded = json_decode($response, true);
            if ($decoded && isset($decoded['content'][0]['text'])) {
                // Log usage
                if (isset($decoded['usage'])) {
                    $in = $decoded['usage']['input_tokens'] ?? 0;
                    $out = $decoded['usage']['output_tokens'] ?? 0;
                    require_once __DIR__ . '/db.php';
                    JsonDB::logUsage($email, $logAction, $in, $out);
                }
                return [
                    "content" => $decoded['content'][0]['text'],
                    "stop_reason" => $decoded['stop_reason'] ?? "end_turn",
                    "error" => ""
                ];
            }
        }

        $lastError = $curlError ? "cURL Error: $curlError" : "HTTP $httpCode: $response";
        if (strpos($response, 'balance is too low') !== false || strpos($response, 'Plans & Billing') !== false) {
            $lastError = "System Maintenance: Our high-performance AI processing nodes are currently undergoing optimization. Our engineering team is actively working on it. Access will be restored in a few minutes. Thank you for your patience!";
        }
        $retryable = $curlError || $httpCode === 429 || $httpCode === 529 || $httpCode >= 500;
        if (!$retryable || $attempt === $maxRetries) break;

        sleep(pow(2, $attempt));
    }

    return ["content" => "", "stop_reason" => "", "error" => $lastError];
}

function handleClaudeError($err, $context) {
    if (strpos($err, 'System Maintenance:') !== false) {
        return $err;
    }
    return $context . ": " . $err;
}

function repairTruncatedJSON($json) {
    $json = trim($json);
    if (empty($json)) return null;

    $json = rtrim($json, ',');
    $len = strlen($json);
    $stack = [];
    $inString = false;
    $escape = false;

    for ($i = 0; $i < $len; $i++) {
        $char = $json[$i];
        if ($escape) {
            $escape = false;
            continue;
        }
        if ($char === '\\') {
            $escape = true;
            continue;
        }
        if ($char === '"') {
            $inString = !$inString;
            continue;
        }
        if (!$inString) {
            if ($char === '{' || $char === '[') {
                $stack[] = $char;
            } elseif ($char === '}') {
                if (!empty($stack) && end($stack) === '{') {
                    array_pop($stack);
                }
            } elseif ($char === ']') {
                if (!empty($stack) && end($stack) === '[') {
                    array_pop($stack);
                }
            }
        }
    }

    if ($inString) {
        $json .= '"';
    }

    $json = rtrim($json, ',');

    while (!empty($stack)) {
        $open = array_pop($stack);
        if ($open === '{') {
            $json .= '}';
        } elseif ($open === '[') {
            $json .= ']';
        }
    }

    return $json;
}

function extractJSON($content) {
    if (!$content) return null;
    
    // Find first { and last }
    $start = strpos($content, '{');
    $end = strrpos($content, '}');
    
    if ($start === false) return null;
    
    if ($end === false || $end <= $start) {
        // Truncated JSON without closing }
        $json = substr($content, $start);
    } else {
        $json = substr($content, $start, ($end - $start) + 1);
    }
    
    // Attempt to repair in case of truncation
    $repaired = repairTruncatedJSON($json);
    if (!$repaired) return null;
    
    // Clean common JSON-breaking characters but preserve newlines and tabs
    $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $repaired);
    
    $decoded = json_decode($cleaned, true);
    
    // If it fails, try to aggressively clean escaping
    if ($decoded === null) {
        $decoded = json_decode(stripslashes($cleaned), true);
    }
    
    return $decoded;
}

// 1. Get Meta Info
$metaSys = "You are an academic project architect. Return ONLY raw JSON: {\"keywords\": [], \"chapters\": []}. No explanation.";
$metaUsr = "Generate 6-8 keywords and a list of 7-8 technical chapters for a project: $title ($domain). Requirements: $requirements. IMPORTANT: The first chapter MUST be 'Introduction'.";
$metaResult = callClaude($metaSys, $metaUsr, $apiKey, $email, "Generate Outline: Chapters");
if ($metaResult['error']) { echo json_encode(["error" => handleClaudeError($metaResult['error'], "AI Error (Meta)")]); exit; }
$metaData = extractJSON($metaResult['content']);

// 2. Get Abstract
$absSys = "You are an academic project architect. Return ONLY raw JSON: {\"abstract\": \"<p>...</p>\"}. No explanation.";
$absUsr = "Generate a technical abstract (200 words) for: $title ($domain).";
$abstractResult = callClaude($absSys, $absUsr, $apiKey, $email, "Generate Outline: Abstract");
if ($abstractResult['error']) { echo json_encode(["error" => handleClaudeError($abstractResult['error'], "AI Error (Abstract)")]); exit; }
$abstractData = extractJSON($abstractResult['content']);

// 3. Get PPT
$pptSys = "You are an academic project architect. Return ONLY raw JSON: {\"ppt\": [{\"type\": \"title\", \"title\": \"...\", \"subtitle\": \"...\", \"points\": []}]}. No explanation.";
$pptUsr = "Generate exactly 8 detailed slides for: $title ($domain).";
$pptResult = callClaude($pptSys, $pptUsr, $apiKey, $email, "Generate Outline: PPT Slides");
if ($pptResult['error']) { echo json_encode(["error" => handleClaudeError($pptResult['error'], "AI Error (PPT)")]); exit; }
$pptData = extractJSON($pptResult['content']);

// 4. Get Code
$codeSys = "You are an academic project architect. Return ONLY raw JSON with very brief boilerplate: {\"code\": {\"files\": [{\"filename\": \"...\", \"content\": \"...\"}]}}. Keep files extremely short (max 40 lines each), no comments, and absolutely no explanations.";
$codeUsr = "Generate 1-2 brief boilerplate files of core code for: $title ($domain). Keep it highly summarized so it fits easily within token limits.";
$codeResult = callClaude($codeSys, $codeUsr, $apiKey, $email, "Generate Outline: Boilerplate Code");
if ($codeResult['error']) { echo json_encode(["error" => handleClaudeError($codeResult['error'], "AI Error (Code)")]); exit; }
$codeData = extractJSON($codeResult['content']);

if (!$metaData || !$abstractData || !$pptData || !$codeData) {
    echo json_encode([
        "error" => "Generation failed due to JSON parsing error.",
        "debug" => [
            "meta" => $metaResult['stop_reason'],
            "abstract" => $abstractResult['stop_reason'],
            "ppt" => $pptResult['stop_reason'],
            "code" => $codeResult['stop_reason']
        ],
        "raw" => $metaResult['content'] // Provide one of them for debugging
    ]);
    exit;
}

$finalResponse = [
    "title" => $title,
    "abstract" => $abstractData['abstract'] ?? "",
    "keywords" => $metaData['keywords'] ?? [],
    "chapters" => $metaData['chapters'] ?? [],
    "ppt" => $pptData['ppt'] ?? [],
    "code" => $codeData['code'] ?? ["files" => []]
];

if ($reportTemplatePath) $finalResponse['reportTemplatePath'] = $reportTemplatePath;
if ($pptTemplatePath) $finalResponse['pptTemplatePath'] = $pptTemplatePath;

echo json_encode($finalResponse);
?>
