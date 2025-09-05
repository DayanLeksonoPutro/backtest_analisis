<?php
// Debug the extraction process
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
    $header = $table->getElementsByTagName('tr')->item(0);
    if ($header) {
        $headerText = trim($header->textContent);
        echo "<h3>Table $i</h3>";
        echo "<p>Header text: " . htmlspecialchars(substr($headerText, 0, 100)) . "...</p>";
        
        // Check if this might be our trades table
        if (strpos($headerText, 'Deals') !== false) {
            echo "<p style='color: green; font-weight: bold;'>This might be the Deals table!</p>";
            
            // Show first few rows
            $rows = $table->getElementsByTagName('tr');
            for ($j = 0; $j < min(5, $rows->length); $j++) {
                $row = $rows->item($j);
                $rowText = trim($row->textContent);
                echo "<p>Row $j: " . htmlspecialchars(substr($rowText, 0, 100)) . "...</p>";
            }
        }
    }
}

// Now test our extraction function
$trades = extractTradesEnhanced($doc);

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

function extractTradesEnhanced($doc) {
    $trades = [];
    $tables = $doc->getElementsByTagName('table');
    $tradesTable = null;

    // Cari tabel yang berisi riwayat transaksi (baik Trades maupun Deals)
    foreach ($tables as $table) {
        $header = $table->getElementsByTagName('tr')->item(0);
        if ($header) {
            $headerText = $header->textContent;
            echo "<p>Checking table for trades: " . htmlspecialchars(substr($headerText, 0, 50)) . "...</p>";
            // Check for both old format (Trades/Orders) and new format (Deals)
            if ((strpos($headerText, 'Profit') !== false && strpos($headerText, 'Order') !== false) ||
                (strpos($headerText, 'Profit') !== false && strpos($headerText, 'Time') !== false && strpos($headerText, 'Deals') !== false)) {
                echo "<p style='color: blue;'>Match found!</p>";
                $tradesTable = $table;
                break;
            }
        }
    }

    if ($tradesTable) {
        echo "<p>Processing trades table...</p>";
        $rows = $tradesTable->getElementsByTagName('tr');
        $headerRow = $rows->item(0);
        $headers = [];
        
        // Get column headers (handle both td and th elements)
        $headerCells = $headerRow->getElementsByTagName('td');
        if ($headerCells->length == 0) {
            $headerCells = $headerRow->getElementsByTagName('th');
        }
        
        foreach ($headerCells as $th) {
            $headers[] = trim($th->textContent);
        }
        
        echo "<p>Headers found: " . implode(', ', $headers) . "</p>";
        
        // Process each trade row
        for ($i = 1; $i < $rows->length; $i++) {
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
            if ($i <= 3) {
                echo "<p>Trade $i: ";
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
    } else {
        echo "<p style='color: red;'>No trades table found!</p>";
    }
    
    return $trades;
}
?>