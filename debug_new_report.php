<?php
// Debug the new HTML report file that's not displaying properly
$target_file = "ReportTester-5036666090.html";

echo "<!DOCTYPE html>
<html>
<head>
    <title>Debug New Report Analysis</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .error { color: red; }
        .success { color: green; }
        .info { color: blue; }
        pre { background-color: #f5f5f5; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
    <h2>Debug Report Analysis for $target_file</h2>";

// Check if file exists
if (!file_exists($target_file)) {
    echo "<p class='error'>ERROR: File does not exist</p>";
    exit;
}

echo "<p>File exists: Yes</p>";
echo "<p>File size: " . filesize($target_file) . " bytes</p>";

// Check file readability
$html = file_get_contents($target_file);
if ($html === false) {
    echo "<p class='error'>ERROR: Cannot read file</p>";
    exit;
}

echo "<p>File read successfully: Yes</p>";

// Check encoding
$isUTF16 = (substr($html, 0, 2) === "\xFF\xFE" || substr($html, 0, 2) === "\xFE\xFF");
echo "<p>UTF-16 detected: " . ($isUTF16 ? "<span class='success'>Yes</span>" : "No") . "</p>";

if ($isUTF16) {
    $converted = iconv("UTF-16", "UTF-8//IGNORE", $html);
    if ($converted === false) {
        echo "<p class='error'>ERROR: Failed to convert UTF-16 to UTF-8</p>";
        exit;
    }
    $html = $converted;
    echo "<p>UTF-16 to UTF-8 conversion: <span class='success'>Successful</span></p>";
    
    // Save converted file for inspection
    file_put_contents("converted_new_report.html", $html);
    echo "<p>Converted file saved as 'converted_new_report.html' for inspection</p>";
}

// Check HTML structure
echo "<h3>HTML Structure Analysis</h3>";

// Parse with DOM
libxml_use_internal_errors(true);
$doc = new DOMDocument();
$loaded = @$doc->loadHTML($html);

if (!$loaded) {
    echo "<p class='error'>ERROR: Failed to parse HTML</p>";
    $errors = libxml_get_errors();
    foreach ($errors as $error) {
        echo "<p>Error: " . htmlspecialchars($error->message) . "</p>";
    }
    exit;
}

echo "<p>HTML parsed successfully: <span class='success'>Yes</span></p>";

// Analyze tables
$tables = $doc->getElementsByTagName('table');
echo "<p>Tables found: " . $tables->length . "</p>";

if ($tables->length == 0) {
    echo "<p class='error'>ERROR: No tables found in report</p>";
    echo "<h3>HTML Content Preview:</h3>";
    echo "<pre>" . htmlspecialchars(substr($html, 0, 1000)) . "</pre>";
    exit;
}

// Look for trades table specifically
$tradesTable = null;
$tradesTableIndex = -1;

foreach ($tables as $i => $table) {
    $header = $table->getElementsByTagName('tr')->item(0);
    if ($header) {
        $headerText = $header->textContent;
        echo "<p>Table $i header text: " . htmlspecialchars(substr($headerText, 0, 100)) . "...</p>";
        
        // Look for trades table with Profit and Order columns
        if (strpos($headerText, 'Profit') !== false && (strpos($headerText, 'Order') !== false || strpos($headerText, 'Time') !== false)) {
            $tradesTable = $table;
            $tradesTableIndex = $i;
            echo "<p class='success'>Found potential trades table at index $i</p>";
        }
    }
}

if (!$tradesTable) {
    echo "<p class='error'>ERROR: Could not locate trades table in report</p>";
    
    // Show all table contents for debugging
    echo "<h3>All Table Contents:</h3>";
    foreach ($tables as $i => $table) {
        echo "<h4>Table $i:</h4>";
        $textContent = $table->textContent;
        echo "<pre>" . htmlspecialchars(substr($textContent, 0, 500)) . "...</pre>";
    }
    exit;
}

// Validate trades table structure
$headerRow = $tradesTable->getElementsByTagName('tr')->item(0);
if (!$headerRow) {
    echo "<p class='error'>ERROR: Trades table has no header row</p>";
    exit;
}

// Check for required columns
$headers = [];
$headerCells = $headerRow->getElementsByTagName('td');
if ($headerCells->length == 0) {
    // Try th elements
    $headerCells = $headerRow->getElementsByTagName('th');
}

foreach ($headerCells as $cell) {
    $headers[] = trim($cell->textContent);
}

echo "<p>Headers found: " . implode(', ', array_map('htmlspecialchars', $headers)) . "</p>";

$hasTime = in_array('Time', $headers);
$hasProfit = in_array('Profit', $headers);

echo "<p>Time column found: " . ($hasTime ? "<span class='success'>Yes</span>" : "<span class='error'>No</span>") . "</p>";
echo "<p>Profit column found: " . ($hasProfit ? "<span class='success'>Yes</span>" : "<span class='error'>No</span>") . "</p>";

if (!$hasTime || !$hasProfit) {
    echo "<p class='error'>ERROR: Required columns (Time, Profit) not found in trades table</p>";
    exit;
}

// Show sample data rows
echo "<h3>Sample Data Rows:</h3>";
$rows = $tradesTable->getElementsByTagName('tr');
$maxRows = min(10, $rows->length);

for ($i = 0; $i < $maxRows; $i++) {
    $row = $rows->item($i);
    $cells = $row->getElementsByTagName('td');
    $cellData = [];
    
    foreach ($cells as $cell) {
        $cellData[] = trim($cell->textContent);
    }
    
    echo "<p>Row $i: " . implode(' | ', array_map('htmlspecialchars', $cellData)) . "</p>";
}

// Try to extract trades to see if it works
echo "<h3>Trade Extraction Test:</h3>";
$trades = [];

$rows = $tradesTable->getElementsByTagName('tr');
$headerRow = $rows->item(0);
$headers = [];

// Get column headers
$headerCells = $headerRow->getElementsByTagName('td');
if ($headerCells->length == 0) {
    $headerCells = $headerRow->getElementsByTagName('th');
}

foreach ($headerCells as $th) {
    $headers[] = trim($th->textContent);
}

echo "<p>Header mapping: " . json_encode($headers) . "</p>";

// Process each trade row
for ($i = 1; $i < min(6, $rows->length); $i++) {
    $row = $rows->item($i);
    $trade = [];
    $cells = $row->getElementsByTagName('td');
    
    if ($cells->length == 0) {
        // Try th elements
        $cells = $row->getElementsByTagName('th');
    }
    
    echo "<p>Row $i has " . $cells->length . " cells</p>";
    
    for ($j = 0; $j < min(count($headers), $cells->length); $j++) {
        $trade[$headers[$j]] = trim($cells->item($j)->textContent);
    }
    
    echo "<p>Trade data: " . json_encode($trade) . "</p>";
    
    // Only add trades with profit/loss data
    if (isset($trade['Profit']) && $trade['Profit'] !== '') {
        $trades[] = $trade;
        if (count($trades) >= 3) break; // Only show first 3 trades
    }
}

echo "<p>Successfully extracted " . count($trades) . " sample trades</p>";

if (count($trades) > 0) {
    echo "<p class='success'>SUCCESS: Report structure appears valid and can be processed!</p>";
    echo "<p><a href='index.php'>Return to upload form</a></p>";
} else {
    echo "<p class='error'>WARNING: No trades with Profit data found</p>";
}

echo "</body>
</html>";
?>