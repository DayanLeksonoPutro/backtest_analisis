<?php
// Test the enhanced extraction function
include 'upload_enhanced.php';

$target_file = "ReportTester-5036666090.html";

// Load and convert UTF-16 encoded HTML file
$html = file_get_contents($target_file);

// Convert UTF-16 to UTF-8
if (substr($html, 0, 2) === "\xFF\xFE" || substr($html, 0, 2) === "\xFE\xFF") {
    $html = iconv("UTF-16", "UTF-8//IGNORE", $html);
}

libxml_use_internal_errors(true);
$doc = new DOMDocument();
$loaded = @$doc->loadHTML($html);

if (!$loaded) {
    echo "Error: Gagal mem-parsing HTML.";
    exit;
}

// Test the enhanced extraction function
$trades = extractTradesEnhanced($doc);

echo "<h2>Test Results</h2>";
echo "<p>Jumlah trades ditemukan: " . count($trades) . "</p>";

if (!empty($trades)) {
    echo "<h3>Sample Trades Data:</h3>";
    echo "<pre>";
    for ($i = 0; $i < min(3, count($trades)); $i++) {
        print_r($trades[$i]);
        echo "\n";
    }
    echo "</pre>";
    
    // Calculate Monthly Statistics
    $monthlyStats = calculateMonthlyStats($trades);
    
    echo "<h2>Monthly Stats</h2>";
    echo "<p>Jumlah bulan ditemukan: " . count($monthlyStats) . "</p>";
    
    if (!empty($monthlyStats)) {
        echo "<pre>";
        print_r(array_slice($monthlyStats, 0, 3, true)); // Show first 3 months
        echo "</pre>";
    }
} else {
    echo "<p style='color: red;'>No trades found!</p>";
}
?>