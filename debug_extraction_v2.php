<?php
// Debug the extraction process - improved version
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
    $errors = libxml_get_errors();
    foreach ($errors as $error) {
        echo "<br>Error detail: " . htmlspecialchars($error->message);
    }
    exit;
}

// Debug: Check all tables
$tables = $doc->getElementsByTagName('table');
echo "<h2>Debug: All Tables</h2>";
echo "<p>Total tables found: " . $tables->length . "</p>";

foreach ($tables as $i => $table) {
    echo "<h3>Table $i Details</h3>";
    $rows = $table->getElementsByTagName('tr');
    echo "<p>Total rows in table: " . $rows->length . "</p>";
    
    // Show first few rows
    for ($j = 0; $j < min(10, $rows->length); $j++) {
        $row = $rows->item($j);
        $rowText = trim($row->textContent);
        echo "<p>Row $j: " . htmlspecialchars(substr($rowText, 0, 100)) . "...</p>";
        
        // Check if this row contains "Deals"
        if (strpos($rowText, 'Deals') !== false) {
            echo "<p style='color: green; font-weight: bold;'>Found 'Deals' in row $j!</p>";
        }
        
        // Check if this row contains both Time and Profit
        if (strpos($rowText, 'Time') !== false && strpos($rowText, 'Profit') !== false) {
            echo "<p style='color: blue; font-weight: bold;'>Found 'Time' and 'Profit' in row $j!</p>";
        }
    }
    echo "<hr>";
}

// Now test our improved extraction function
$trades = extractTradesImproved($doc);

echo "<h2>Debug: Extracted Trades</h2>";
echo "<p>Jumlah trades ditemukan: " . count($trades) . "</p>";

if (!empty($trades)) {
    echo "<h3>Sample Trades Data:</h3>";
    echo "<pre>";
    for ($i = 0; $i < min(5, count($trades)); $i++) {
        print_r($trades[$i]);
        echo "\n";
    }
    echo "</pre>";
} else {
    echo "<p style='color: red;'>No trades found!</p>";
}

function extractTradesImproved($doc) {
    $trades = [];
    $tables = $doc->getElementsByTagName('table');
    
    // Look for the table containing "Deals"
    foreach ($tables as $tableIndex => $table) {
        $rows = $table->getElementsByTagName('tr');
        
        // Look for a row containing "Deals"
        $dealsRowIndex = -1;
        for ($i = 0; $i < min(10, $rows->length); $i++) {
            $rowText = $rows->item($i)->textContent;
            if (strpos($rowText, 'Deals') !== false) {
                $dealsRowIndex = $i;
                break;
            }
        }
        
        // If we found the Deals row, look for the header row after it
        if ($dealsRowIndex !== -1) {
            echo "<p>Found Deals table at table $tableIndex, row $dealsRowIndex</p>";
            
            // Look for the header row (should contain Time and Profit)
            $headerRowIndex = -1;
            $headers = [];
            
            // Check the next few rows for headers
            for ($i = $dealsRowIndex + 1; $i < min($dealsRowIndex + 5, $rows->length); $i++) {
                $rowText = $rows->item($i)->textContent;
                if (strpos($rowText, 'Time') !== false && strpos($rowText, 'Profit') !== false) {
                    $headerRowIndex = $i;
                    
                    // Extract headers
                    $headerCells = $rows->item($i)->getElementsByTagName('td');
                    if ($headerCells->length == 0) {
                        $headerCells = $rows->item($i)->getElementsByTagName('th');
                    }
                    
                    foreach ($headerCells as $th) {
                        $headers[] = trim($th->textContent);
                    }
                    break;
                }
            }
            
            if ($headerRowIndex !== -1) {
                echo "<p>Found header row at row $headerRowIndex with headers: " . implode(', ', $headers) . "</p>";
                
                // Process data rows (everything after the header row)
                for ($i = $headerRowIndex + 1; $i < $rows->length; $i++) {
                    $row = $rows->item($i);
                    $trade = [];
                    $cells = $row->getElementsByTagName('td');
                    
                    // Handle case where cells might be th elements
                    if ($cells->length == 0) {
                        $cells = $row->getElementsByTagName('th');
                    }
                    
                    for ($j = 0; $j < min(count($headers), $cells->length); $j++) {
                        $trade[$headers[$j]] = trim($cells->item($j)->textContent);
                    }
                    
                    // Show first few trades for debugging
                    if ($i <= $headerRowIndex + 3) {
                        echo "<p>Trade " . ($i - $headerRowIndex) . ": ";
                        foreach ($trade as $key => $value) {
                            echo htmlspecialchars($key) . "=" . htmlspecialchars($value) . "; ";
                        }
                        echo "</p>";
                    }
                    
                    // Filter out balance rows and only add trades with profit/loss data
                    if (isset($trade['Profit']) && $trade['Profit'] !== '' && 
                        isset($trade['Type']) && $trade['Type'] !== 'balance') {
                        $trades[] = $trade;
                        if (count($trades) <= 5) {
                            echo "<p style='color: green;'>Added trade: Profit=" . htmlspecialchars($trade['Profit']) . "</p>";
                        }
                    } else if (isset($trade['Profit']) && $trade['Profit'] !== '') {
                        echo "<p style='color: orange;'>Skipped trade: Profit=" . htmlspecialchars($trade['Profit']) . ", Type=" . (isset($trade['Type']) ? htmlspecialchars($trade['Type']) : 'N/A') . "</p>";
                    }
                }
                break; // Found the right table, no need to check others
            }
        }
    }
    
    return $trades;
}
?>