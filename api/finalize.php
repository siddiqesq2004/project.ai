<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

error_reporting(0);
ini_set('display_errors', 0);
ob_start();

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(["error" => "No data provided or invalid JSON."]);
    exit;
}

if (!file_exists('uploads')) {
    mkdir('uploads', 0777, true);
}

if (!is_writable('uploads')) {
    echo json_encode(["error" => "The uploads directory is not writable. Check folder permissions (777)."]);
    exit;
}

$title = $data['title'];
$abstract = $data['abstract'];
$sections = $data['sections'];
$ppt = $data['ppt'];
$code = $data['code'];
$keywords = isset($data['keywords']) ? (is_array($data['keywords']) ? implode(", ", $data['keywords']) : $data['keywords']) : "";
$figures = isset($data['figures']) ? $data['figures'] : [];

function fetchImageBase64($url, $maxRetries = 1) {
    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3); // 3 seconds is plenty for quickchart
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $data && strlen($data) > 500) {
            return base64_encode($data);
        }
        usleep(50000); // 50ms delay
    }
    return null;
}

function renderHtmlFlowchart($diagramConfig) {
    preg_match_all('/"([^"]+)"\s*->\s*"([^"]+)"/', $diagramConfig, $matches);
    
    if (empty($matches[0])) {
        preg_match_all('/([a-zA-Z0-9_\s\-]+)\s*->\s*([a-zA-Z0-9_\s\-]+)/', $diagramConfig, $matches);
    }
    
    $nodes = [];
    if (!empty($matches[0])) {
        foreach ($matches[1] as $idx => $from) {
            $from = trim($from);
            $to = trim($matches[2][$idx]);
            
            if (!in_array($from, $nodes)) {
                $nodes[] = $from;
            }
            if (!in_array($to, $nodes)) {
                $nodes[] = $to;
            }
        }
    }
    
    if (empty($nodes)) {
        return "<div style='padding: 15px; border: 1px dashed #ccc; text-align: center;'>Architecture Flowchart could not be resolved.</div>";
    }
    
    $html = "<div style='margin: 20px auto; width: 100%; text-align: center; font-family: Arial, sans-serif; page-break-inside: avoid;'>";
    $html .= "<table align='center' style='margin: 0 auto; border-collapse: separate; border-spacing: 0 10px; width: auto;'>";
    
    $maxInRow = 4;
    $rows = array_chunk($nodes, $maxInRow);
    $totalRows = count($rows);
    
    foreach ($rows as $rIdx => $rowNodes) {
        $html .= "<tr>";
        foreach ($rowNodes as $nIdx => $nodeName) {
            $html .= "<td style='border: 2px solid #475569; background-color: #f8fafc; padding: 12px 20px; text-align: center; border-radius: 8px; font-weight: bold; font-size: 10.5pt; color: #1e293b; min-width: 120px; mso-line-height-rule: exactly; line-height: 1.3;'>" . htmlspecialchars($nodeName) . "</td>";
            
            if ($nIdx < count($rowNodes) - 1) {
                $html .= "<td style='width: 35px; text-align: center; font-size: 16pt; font-weight: bold; color: #ff2a5f;'>&rarr;</td>";
            }
        }
        $html .= "</tr>";
        
        if ($rIdx < $totalRows - 1) {
            $html .= "<tr>";
            $lastColIdx = count($rowNodes) - 1;
            for ($c = 0; $c < $lastColIdx * 2; $c++) {
                $html .= "<td></td>";
            }
            $html .= "<td style='height: 25px; text-align: center; font-size: 16pt; font-weight: bold; color: #ff2a5f;'>&darr;</td>";
            $html .= "</tr>";
        }
    }
    
    $html .= "</table>";
    $html .= "</div>";
    
    return $html;
}

// Define sequence of all figures in the document dynamically
$allFigures = [];
$figCounter = 1;
$runningPage = 1; // Sync with Table of Contents Chapter 1 start page

$archAdded = false;
$resultsAdded = false;

foreach ($sections as $index => $sec) {
    $chapterLower = strtolower($sec['heading']);
    
    $isArchChapter = (strpos($chapterLower, 'implement') !== false || strpos($chapterLower, 'design') !== false || strpos($chapterLower, 'arch') !== false || strpos($chapterLower, 'model') !== false);
    
    $isResultsChapter = (strpos($chapterLower, 'result') !== false || strpos($chapterLower, 'eval') !== false || strpos($chapterLower, 'analysis') !== false || strpos($chapterLower, 'finding') !== false);
    
    if ($isArchChapter && !$archAdded && isset($data['architectureDiagram']) && !empty($data['architectureDiagram'])) {
        $allFigures[] = [
            'id' => $figCounter++,
            'title' => $data['architectureDiagram']['title'],
            'page' => $runningPage, // Place it in this chapter
            'type' => 'arch'
        ];
        $archAdded = true;
    }
    
    if ($isResultsChapter && !$resultsAdded && !empty($figures)) {
        foreach ($figures as $fIdx => $fig) {
            $allFigures[] = [
                'id' => $figCounter++,
                'title' => $fig['title'],
                'page' => $runningPage, // Place it in this chapter
                'type' => 'result',
                'originalIndex' => $fIdx
            ];
        }
        $resultsAdded = true;
    }
    
    // Increment page counter based on content size
    $wordCount = str_word_count(strip_tags($sec['content']));
    $pages = ceil($wordCount / 300);
    if ($pages < 1) $pages = 1;
    $runningPage += $pages;
}

$timestamp = time();
$zipName = "project_$timestamp.zip";
$zipPath = "uploads/$zipName";

if (!class_exists('ZipArchive')) {
    ob_end_clean();
    echo json_encode(["error" => "The ZipArchive extension is missing on the server. Please install php-zip."]);
    exit;
}

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
    ob_end_clean();
    echo json_encode(["error" => "Cannot create ZIP file at $zipPath. Please check folder permissions."]);
    exit;
}

// 1. Generate Report (Simplified DOC as HTML)
$reportContent = "<html>
<head>
<meta charset='utf-8'>
<style>
    body { font-family: 'Times New Roman', Times, serif; font-size: 12pt; line-height: 1.6; color: #000; }
    h1 { text-align: center; margin-top: 2in; font-size: 24pt; text-transform: uppercase; }
    h2 { text-align: center; margin-top: 0.5in; font-size: 18pt; text-transform: uppercase; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 30px; }
    h3 { text-align: center; font-weight: normal; font-size: 14pt; margin-bottom: 5px; }
    p { text-align: justify; text-indent: 0.5in; margin-top: 0; margin-bottom: 15px; }
    div.justify { text-align: justify; }
    img { max-width: 100%; height: auto; display: block; margin: 20px auto; }
    .page-break { page-break-before: always; clear: both; display: block; height: 0; }
    .toc-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    .toc-table th, .toc-table td { padding: 10px 0; border: none; font-size: 12pt; }
    .fig-caption { text-align: center; font-style: italic; margin-top: 10px; margin-bottom: 30px; font-weight: bold; }
    .chapter-content { margin-top: 20px; }
</style>
</head>
<body>
    <br><br><br>
    <h1>$title</h1>
    <br clear='all' style='mso-special-character:line-break;page-break-before:always'>

    <h2>ABSTRACT</h2>
    <div class='justify' style='text-indent: 0.5in;'>$abstract</div>";
if ($keywords) {
    $reportContent .= "<p style='font-style: italic; font-size: 10pt; text-align: left; text-indent: 0; margin-top: 20px;'><strong>Keywords:</strong> $keywords</p>";
}
$reportContent .= "
    <br clear='all' style='mso-special-character:line-break;page-break-before:always'>
    <h2>Table of Contents</h2>
    <br>
    <table class='toc-table'>
        <tr>
            <th style='text-align: left; width: 25%;'>CHAPTER NO.</th>
            <th style='text-align: left; width: 60%;'>TITLE</th>
            <th style='text-align: right; width: 15%;'>PAGE NO.</th>
        </tr>
        <tr><td></td><td><strong>ABSTRACT</strong></td><td style='text-align: right;'>iv</td></tr>
        <tr><td></td><td><strong>LIST OF CONTENTS</strong></td><td style='text-align: right;'>v</td></tr>
        <tr><td></td><td><strong>LIST OF FIGURES</strong></td><td style='text-align: right;'>vi</td></tr>";

$currentPage = 1;
$chapterNum = 1;
foreach ($sections as $sec) {
    $reportContent .= "<tr>
        <td><strong>$chapterNum.</strong></td>
        <td><strong>" . strtoupper($sec['heading']) . "</strong></td>
        <td style='text-align: right;'>$currentPage</td>
    </tr>";
    $chapterNum++;
    
    $wordCount = str_word_count(strip_tags($sec['content']));
    $pages = ceil($wordCount / 300);
    if ($pages < 1) $pages = 1;
    $currentPage += $pages;
}
$reportContent .= "</table>";

// List of Figures Page
$reportContent .= "
    <br clear='all' style='mso-special-character:line-break;page-break-before:always'>
    <h2>List of Figures</h2>
    <br>
    <table class='toc-table'>
        <tr>
            <th style='text-align: left; width: 25%;'>FIGURE NO.</th>
            <th style='text-align: left; width: 60%;'>TITLE</th>
            <th style='text-align: right; width: 15%;'>PAGE NO.</th>
        </tr>";
foreach ($allFigures as $fig) {
    $reportContent .= "<tr>
        <td>Figure " . $fig['id'] . "</td>
        <td>" . htmlspecialchars($fig['title']) . "</td>
        <td style='text-align: right;'>" . $fig['page'] . "</td>
    </tr>";
}
$reportContent .= "</table>";

$chapterNum = 1;
$architectureRendered = false;
$resultsRendered = false;

foreach ($sections as $sec) {
    // Robust Page Break for Word/HTML
    $reportContent .= "<br clear='all' style='mso-special-character:line-break;page-break-before:always'>";
    $reportContent .= "<br><br>";
    $reportContent .= "<h3 style='text-align: center; font-weight: normal; font-size: 14pt; margin-bottom: 5px;'>CHAPTER $chapterNum</h3>";
    $reportContent .= "<h2 style='text-align: center; text-transform: uppercase; font-size: 14pt; margin-top: 5px;'>" . strtoupper($sec['heading']) . "</h2>";
    $reportContent .= "<br>";

    $chapterLower = strtolower($sec['heading']);
    $isArchChapter = (strpos($chapterLower, 'implement') !== false || strpos($chapterLower, 'design') !== false || strpos($chapterLower, 'arch') !== false || strpos($chapterLower, 'model') !== false);
    $isResultsChapter = (strpos($chapterLower, 'result') !== false || strpos($chapterLower, 'eval') !== false || strpos($chapterLower, 'analysis') !== false || strpos($chapterLower, 'finding') !== false);

    $chapterContent = $sec['content'];
    
    // Split the content into paragraphs by "</p>"
    $paragraphs = explode("</p>", $chapterContent);
    // Clean up empty lines or whitespaces and restore closing tags
    $paragraphs = array_filter(array_map('trim', $paragraphs));
    $totalParagraphs = count($paragraphs);
    
    $reassembledContent = "";
    
    if ($isArchChapter && !$architectureRendered && isset($data['architectureDiagram']) && !empty($data['architectureDiagram'])) {
        // Architecture Chapter - Insert architecture diagram in the middle of the paragraphs
        $insertAt = ceil($totalParagraphs / 2);
        if ($insertAt < 1) $insertAt = 1;
        
        $arch = $data['architectureDiagram'];
        
        $seqId = 1;
        foreach ($allFigures as $af) {
            if ($af['type'] === 'arch') {
                $seqId = $af['id'];
                break;
            }
        }
        
        $flowchartHtml = renderHtmlFlowchart($arch['diagramConfig']);
        $archHtml = "<div style='margin: 15px 0; text-align: center; width: 100%; page-break-inside: avoid;'>";
        $archHtml .= $flowchartHtml;
        $archHtml .= "<p class='fig-caption' style='text-align: center; margin: 6px auto 0 auto; font-weight: bold; font-size: 9.5pt; max-width: 5.8in; line-height: 1.25;'>Figure " . $seqId . ": " . htmlspecialchars($arch['title']) . "</p>";
        $archHtml .= "</div>";
        
        $i = 0;
        foreach ($paragraphs as $para) {
            if (empty($para)) continue;
            $paraHtml = $para . "</p>";
            $reassembledContent .= $paraHtml;
            $i++;
            if ($i == $insertAt) {
                $reassembledContent .= $archHtml;
            }
        }
        $architectureRendered = true;
        
    } elseif ($isResultsChapter && !$resultsRendered && !empty($figures)) {
        // Results Chapter - Insert pairs of figures in between paragraphs
        $insertPoint1 = ceil($totalParagraphs / 3);
        if ($insertPoint1 < 1) $insertPoint1 = 1;
        
        $insertPoint2 = ceil(2 * $totalParagraphs / 3);
        if ($insertPoint2 <= $insertPoint1) $insertPoint2 = $insertPoint1 + 1;
        
        // Inline code to build a pair of figures
        $buildPairHtml = function($startIdx) use ($figures, $allFigures) {
            $figCount = count($figures);
            $pairHtml = "<div style='margin: 15px 0; width: 100%; text-align: center;'>";
            $pairHtml .= "<table align='center' style='width: 100%; border-collapse: collapse; margin: 0 auto 10px auto; page-break-inside: avoid;'><tr>";
            
            for ($j = 0; $j < 2; $j++) {
                $idx = $startIdx + $j;
                if ($idx < $figCount) {
                    $fig = $figures[$idx];
                    
                    $seqId = $idx + 2;
                    foreach ($allFigures as $af) {
                        if ($af['type'] === 'result' && $af['originalIndex'] === $idx) {
                            $seqId = $af['id'];
                            break;
                        }
                    }
                    
                    $pairHtml .= "<td align='center' style='width: 50%; padding: 5px; vertical-align: top; text-align: center;'>";
                    
                    if ($fig['type'] === 'ai' && isset($fig['chartConfig'])) {
                        $config = $fig['chartConfig'];
                        $chartUrl = "https://quickchart.io/chart?width=500&height=330&v=2.9.4&c=" . urlencode(json_encode($config));
                        
                        $base64 = fetchImageBase64($chartUrl);
                        if ($base64) {
                            $pairHtml .= "<img src='data:image/png;base64,$base64' width='270' height='180' style='width: 2.75in; height: 1.85in; border: 1px solid #ccc; display: block; margin: 0 auto;'>";
                        } else {
                            $pairHtml .= "<img src='$chartUrl' width='270' height='180' style='width: 2.75in; height: 1.85in; border: 1px solid #ccc; display: block; margin: 0 auto;'>";
                        }
                    } else {
                        $pairHtml .= "<div style='width: 2.75in; height: 1.85in; background: #f0f0f0; border: 1px dashed #999; margin: 0 auto; display: flex; align-items: center; justify-content: center; font-size: 10pt;'>[Figure " . $seqId . "]</div>";
                    }
                    $pairHtml .= "<p style='margin: 6px auto 0 auto; font-weight: bold; font-size: 8.5pt; max-width: 3.1in; line-height: 1.2;'>Figure " . $seqId . ": " . htmlspecialchars($fig['title']) . "</p>";
                    $pairHtml .= "</td>";
                } else {
                    $pairHtml .= "<td style='width: 50%;'></td>";
                }
            }
            $pairHtml .= "</tr></table></div>";
            return $pairHtml;
        };
        
        $pair1Html = $buildPairHtml(0);
        $pair2Html = (count($figures) > 2) ? $buildPairHtml(2) : "";
        
        $pair1Rendered = false;
        $pair2Rendered = false;
        
        $i = 0;
        foreach ($paragraphs as $para) {
            if (empty($para)) continue;
            $paraHtml = $para . "</p>";
            $reassembledContent .= $paraHtml;
            $i++;
            
            if ($i == $insertPoint1) {
                $reassembledContent .= $pair1Html;
                $pair1Rendered = true;
            }
            if ($i == $insertPoint2 && !empty($pair2Html)) {
                $reassembledContent .= $pair2Html;
                $pair2Rendered = true;
            }
        }
        
        // If they weren't rendered because paragraph count was too low
        if (!$pair1Rendered) {
            $reassembledContent .= $pair1Html;
        }
        if (!$pair2Rendered && !empty($pair2Html)) {
            $reassembledContent .= $pair2Html;
        }
        $resultsRendered = true;
        
    } else {
        // Standard Chapter or already rendered - print as is
        $reassembledContent = $chapterContent;
    }
    
    $reportContent .= "<div class='chapter-content'>$reassembledContent</div>";
    $chapterNum++;
}
$reportContent .= "</body></html>";
$zip->addFromString("Report.doc", $reportContent);

require_once 'pptx_generator.php';

// 2. Generate Presentation (Real .pptx file)
$pptxPath = null;
if (isset($ppt) && is_array($ppt) && !empty($ppt)) {
    $pptxName = "Presentation_$timestamp.pptx";
    $pptxPath = "uploads/$pptxName";
    if (generatePPTX($ppt, $title, $pptxPath)) {
        $zip->addFile($pptxPath, "Presentation.pptx");
    }
}

// 3. Generate Code Zip
$codeZipPath = "uploads/code_$timestamp.zip";
$codeZip = new ZipArchive();
if ($codeZip->open($codeZipPath, ZipArchive::CREATE) === TRUE) {
    if (isset($code['files']) && is_array($code['files'])) {
        foreach ($code['files'] as $file) {
            $codeZip->addFromString($file['filename'], $file['content']);
        }
    }
    $codeZip->close();
    $zip->addFile($codeZipPath, "Source_Code.zip");
}

$zip->close();

// Cleanup temp files
if (file_exists($codeZipPath)) unlink($codeZipPath);
if ($pptxPath && file_exists($pptxPath)) unlink($pptxPath);

// Clear any previous output (warnings, etc.)
if (ob_get_length()) ob_end_clean();

// Return the final zip
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="'.$zipName.'"');
header('Content-Length: ' . filesize($zipPath));
header('Pragma: no-cache');
header('Expires: 0');
readfile($zipPath);

// Cleanup
if (file_exists($zipPath)) unlink($zipPath);
exit;
?>
