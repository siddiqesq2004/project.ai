<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");
set_time_limit(120);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

require_once __DIR__ . '/config.php';
$apiKey = CLAUDE_API_KEY;

$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);

$title = $input['title'] ?? '';
$domain = $input['domain'] ?? '';
$phase = $input['phase'] ?? 1;
$context = $input['context'] ?? '';
$email = $input['email'] ?? '';

require_once __DIR__ . '/db.php';
if (!JsonDB::hasCredits($email)) {
    echo json_encode(["error" => "You have reached your allocated student API credit limit ($3.00). Please contact Rademics for further process."]);
    exit;
}

if (empty($title) || empty($domain)) {
    echo json_encode(["error" => "Title and Domain are required."]);
    exit;
}

function callClaude($prompt, $apiKey, $email, $logAction, $maxRetries = 3) {
    $data = [
        "model" => "claude-sonnet-4-20250514",
        "max_tokens" => 4096,
        "messages" => [["role" => "user", "content" => $prompt]]
    ];

    $payload = json_encode($data);
    $headers = [
        'x-api-key: ' . $apiKey,
        'anthropic-version: 2023-06-01',
        'Content-Type: application/json'
    ];

    $lastError = "";
    $lastHttpCode = 0;
    $lastResponse = "";

    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_TIMEOUT, 90);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_errno($ch) ? curl_error($ch) : "";
        curl_close($ch);

        $lastHttpCode = $httpCode;
        $lastResponse = $response;

        // Success
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
                    "stop_reason" => $decoded['stop_reason'] ?? "",
                    "error" => ""
                ];
            }
        }

        // cURL-level error (network issue)
        if ($curlError) {
            $lastError = "cURL Error: $curlError";
        } else {
            $lastError = "HTTP $httpCode: $response";
        }

        // Retry on 429 (rate limit) or 529 (overloaded) or 500+ server errors
        $retryable = $curlError || $httpCode === 429 || $httpCode === 529 || $httpCode >= 500;
        if (!$retryable || $attempt === $maxRetries) {
            break;
        }

        // Exponential backoff: 2s, 4s, 8s
        $delay = pow(2, $attempt);
        sleep($delay);
    }

    return ["content" => "", "stop_reason" => "", "error" => $lastError];
}

if ($phase == 1) {
    $prompt = "You are an academic guide. For a project titled '$title' in the domain '$domain', identify 4-5 specific technical problems or research gaps that a student could address. 
    Return ONLY JSON: {\"explanation\": \"Detailed overview of why these problems matter...\", \"options\": [\"Problem 1: ...\", \"Problem 2: ...\", ...]}";
} else if ($phase == 2) {
    $problem = $input['problem'] ?? 'Standard academic approach';
    $prompt = "You are an academic guide. The student has identified this problem: '$problem' for their project '$title'. 
    Suggest 4-5 specific technical methodologies, algorithms, or frameworks they could use to solve it. 
    Return ONLY JSON: {\"explanation\": \"Detailed technical explanation of these approaches...\", \"options\": [\"Methodology 1: ...\", \"Methodology 2: ...\", ...]}";
} else if ($phase == 3) {
    $problem = $input['problem'] ?? '';
    $methodology = $input['methodology'] ?? '';
    $prompt = "You are an academic researcher. For a project titled '$title' in '$domain', solving '$problem' using '$methodology', generate a detailed results summary (300-400 words). 
    Include specific (simulated) metrics like accuracy, latency, or efficiency gains. 
    Also:
    1. Suggest 3-4 result graphs. For each, provide a 'chartConfig' (Chart.js v2). 
       IMPORTANT: Use ONLY standard chart types: 'line', 'bar', 'pie', 'doughnut', or 'radar'.
    2. Provide a 'diagramConfig' which is a DOT (Graphviz) string for a technical architecture.
       IMPORTANT: Style the nodes and edges beautifully to look like a premium vector flow diagram:
       - Use 'rankdir=LR;' for horizontal flow.
       - Enforce modern clean styling inside the digraph block:
         node [shape=box, style=\"filled,rounded\", fillcolor=\"#f8fafc\", color=\"#475569\", penwidth=2, fontname=\"Helvetica\", fontsize=10, margin=\"0.2,0.1\"];
         edge [color=\"#64748b\", penwidth=2, arrowhead=normal, arrowsize=0.8];
       - Example structure: digraph G { rankdir=LR; node [shape=box, style=\"filled,rounded\", fillcolor=\"#f8fafc\", color=\"#475569\", penwidth=2, fontname=\"Helvetica\", fontsize=10, margin=\"0.2,0.1\"]; edge [color=\"#64748b\", penwidth=2, arrowhead=normal, arrowsize=0.8]; \"Input\" -> \"Process\" -> \"Output\"; }
       - Avoid complex nested subgraphs. Always wrap node names in double quotes.
    Return ONLY JSON: {
        \"resultsText\": \"...\",
        \"suggestedFigures\": [
            {
                \"title\": \"Accuracy vs Epochs\", 
                \"chartConfig\": {...}
            }
        ],
        \"architectureDiagram\": {
            \"title\": \"System Architecture & Process Flow\",
            \"diagramConfig\": \"digraph G { rankdir=LR; \\\"Input Data\\\" -> \\\"Preprocessing\\\"; \\\"Preprocessing\\\" -> \\\"AI Model\\\"; \\\"AI Model\\\" -> \\\"Output Results\\\"; }\"
        }
    }";
} else {
    echo json_encode(["error" => "Invalid phase."]);
    exit;
}

$aiResponse = callClaude($prompt, $apiKey, $email, "Phase Data Simulation (Phase $phase)");
if ($aiResponse['error']) {
    echo json_encode(["error" => "AI Engine Connection Error: " . $aiResponse['error']]);
    exit;
}
$response = $aiResponse['content'];
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

$start = strpos($response, '{');
$end = strrpos($response, '}');

if ($start === false) {
    echo json_encode([
        "error" => "AI did not return valid JSON structure.",
        "stop_reason" => $aiResponse['stop_reason'],
        "raw_response" => $response
    ]);
    exit;
}

if ($end === false || $end <= $start) {
    $json = substr($response, $start);
} else {
    $json = substr($response, $start, ($end - $start) + 1);
}

$repaired = repairTruncatedJSON($json);
if (!$repaired) {
    echo json_encode([
        "error" => "Failed to repair truncated JSON.",
        "stop_reason" => $aiResponse['stop_reason'],
        "raw_response" => $response
    ]);
    exit;
}

$cleaned = preg_replace('/[\x00-\x1F\x7F]/', '', $repaired);
$decoded = json_decode($cleaned, true);
if ($decoded === null) {
    $decoded = json_decode(stripslashes($cleaned), true);
}

if ($decoded) {
    echo json_encode($decoded);
} else {
    echo json_encode([
        "error" => "Failed to parse AI JSON: " . json_last_error_msg(),
        "stop_reason" => $aiResponse['stop_reason'],
        "raw_response" => $response
    ]);
}
?>
