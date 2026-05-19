<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");
set_time_limit(120);
ini_set('memory_limit', '256M');
ini_set('pcre.backtracking_limit', '10000000');
ini_set('pcre.recursion_limit', '10000000');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

require_once __DIR__ . '/config.php';
$apiKey = CLAUDE_API_KEY;

// We read from JSON input because frontend will send this as JSON
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);

$title = $input['title'] ?? '';
$domain = $input['domain'] ?? '';
$chapterName = $input['chapterName'] ?? '';
$email = $input['email'] ?? '';

require_once __DIR__ . '/db.php';
if (!JsonDB::hasCredits($email)) {
    echo json_encode(["error" => "You have reached your allocated student API credit limit ($3.00). Please contact Rademics for further process."]);
    exit;
}

$context = $input['context'] ?? 'General technical approach';

$systemPrompt = "You are an academic project expert. Write a technical chapter for a project titled '$title' ($domain).
Context: $context
Chapter Name: $chapterName

INSTRUCTIONS:
1. Write 5-6 comprehensive paragraphs of technical, academic content.
2. Format the content using only <p> tags.
3. Return ONLY a valid JSON object. Do not include any explanation or markdown formatting.
4. The JSON must have exactly two keys: 'heading' and 'content'.";

$userPrompt = "Generate the JSON for the chapter: $chapterName";

$data = [
    "model" => "claude-sonnet-4-20250514",
    "max_tokens" => 8192,
    "system" => $systemPrompt,
    "messages" => [
        ["role" => "user", "content" => $userPrompt]
    ]
];

$payload = json_encode($data);
$headers = [
    'x-api-key: ' . $apiKey,
    'anthropic-version: 2023-06-01',
    'Content-Type: application/json'
];

$maxRetries = 3;
$fullContent = "";
$stopReason = "unknown";
$lastHttpCode = 0;
$lastResponse = "";

for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_errno($ch) ? curl_error($ch) : "";
    curl_close($ch);

    $lastHttpCode = $httpCode;
    $lastResponse = $response;

    if ($httpCode === 200) {
        $decoded = json_decode($response, true);
        if ($decoded && isset($decoded['content'][0]['text'])) {
            $fullContent = $decoded['content'][0]['text'];
            $stopReason = $decoded['stop_reason'] ?? "";
            
            // Log usage
            if (isset($decoded['usage'])) {
                $in = $decoded['usage']['input_tokens'] ?? 0;
                $out = $decoded['usage']['output_tokens'] ?? 0;
                require_once __DIR__ . '/db.php';
                JsonDB::logUsage($email, "Generate Chapter: $chapterName", $in, $out);
            }
        }
        break;
    }

    $retryable = $curlError || $httpCode === 429 || $httpCode === 529 || $httpCode >= 500;
    if (!$retryable || $attempt === $maxRetries) {
        echo json_encode(["error" => "Claude API error. HTTP $httpCode (after $attempt attempts)", "raw" => substr($response, 0, 500)]);
        exit;
    }

    sleep(pow(2, $attempt));
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

$content = $fullContent;

// Find the JSON object
$start = strpos($content, '{');
$end = strrpos($content, '}');

if ($start === false) {
    echo json_encode([
        "error" => "No JSON found in AI response.",
        "stop_reason" => $stopReason,
        "raw_response" => substr($content, 0, 1000)
    ]);
    exit;
}

if ($end === false || $end <= $start) {
    $jsonString = substr($content, $start);
} else {
    $jsonString = substr($content, $start, ($end - $start) + 1);
}

// Repair the JSON in case of truncation
$repaired = repairTruncatedJSON($jsonString);
if (!$repaired) {
    echo json_encode([
        "error" => "Failed to repair truncated JSON.",
        "stop_reason" => $stopReason,
        "raw_response" => substr($content, 0, 1000)
    ]);
    exit;
}

// Remove control characters except for newline/tab/carriage return
$cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $repaired);

$parsedContent = json_decode($cleaned, true);
if ($parsedContent === null) {
    $parsedContent = json_decode(stripslashes($cleaned), true);
}

if ($parsedContent === null) {
    echo json_encode([
        "error" => "Failed to parse AI JSON: " . json_last_error_msg(),
        "stop_reason" => $stopReason,
        "raw_response" => substr($content, 0, 1000)
    ]);
    exit;
}

echo json_encode($parsedContent);
?>
