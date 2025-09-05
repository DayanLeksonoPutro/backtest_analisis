<?php
// Debug the HTML file
$target_file = "ReportTester-10005982837 ini.html";

// Load and convert UTF-16 encoded HTML file
$html = file_get_contents($target_file);
// Convert UTF-16 to UTF-8
$html = iconv("UTF-16", "UTF-8//IGNORE", $html);

// Save the converted HTML to see what we're working with
file_put_contents("converted.html", $html);

echo "HTML content length: " . strlen($html) . "\n";

// Try to load with DOMDocument
$doc = new DOMDocument();
$doc->loadHTML($html);

// Check if we can find any tables
$tables = $doc->getElementsByTagName('table');
echo "Number of tables found: " . $tables->length . "\n";

// Check each table
foreach ($tables as $i => $table) {
    echo "Table $i:\n";
    $textContent = $table->textContent;
    echo "Text content length: " . strlen($textContent) . "\n";
    echo "First 200 characters: " . substr($textContent, 0, 200) . "\n\n";
}
?>