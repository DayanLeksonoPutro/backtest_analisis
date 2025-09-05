<?php
// Final test with the new report format
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

// Extract Settings
$settings = extractSettings($doc);

// Extract Trades using the improved function
$trades = extractTradesFinal($doc);

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
    
    // Calculate Monthly Statistics
    $monthlyStats = calculateMonthlyStats($trades);
    
    echo "<h2>Debug: Monthly Stats</h2>";
    echo "<p>Jumlah bulan ditemukan: " . count($monthlyStats) . "</p>";
    
    if (!empty($monthlyStats)) {
        echo "<pre>";
        print_r(array_slice($monthlyStats, 0, 3, true)); // Show first 3 months
        echo "</pre>";
    }
    
    // Display Results
    displayResults($settings, $monthlyStats);
} else {
    echo "<p style='color: red;'>No trades found!</p>";
}

function extractSettings($doc) {
    $settings = [];
    
    // Look for settings in the document
    $elements = $doc->getElementsByTagName('*');
    foreach ($elements as $element) {
        $text = $element->textContent;
        if (strpos($text, 'Symbol:') !== false) {
            $parts = explode('Symbol:', $text);
            if (isset($parts[1])) {
                $settings['Symbol'] = trim(explode(' ', $parts[1])[0]);
            }
        } elseif (strpos($text, 'Period:') !== false) {
            $parts = explode('Period:', $text);
            if (isset($parts[1])) {
                $settings['Period'] = trim(explode(' ', $parts[1])[0]);
            }
        } elseif (strpos($text, 'Model:') !== false) {
            $parts = explode('Model:', $text);
            if (isset($parts[1])) {
                $settings['Model'] = trim(explode(' ', $parts[1])[0]);
            }
        } elseif (strpos($text, 'Initial deposit:') !== false) {
            $parts = explode('Initial deposit:', $text);
            if (isset($parts[1])) {
                $settings['Initial deposit'] = trim($parts[1]);
            }
        } elseif (strpos($text, 'Spread:') !== false) {
            $parts = explode('Spread:', $text);
            if (isset($parts[1])) {
                $settings['Spread'] = trim(explode(' ', $parts[1])[0]);
            }
        }
    }
    
    return $settings;
}

function extractTradesFinal($doc) {
    $trades = [];
    $tables = $doc->getElementsByTagName('table');
    
    // Look for the table containing "Deals"
    foreach ($tables as $tableIndex => $table) {
        $rows = $table->getElementsByTagName('tr');
        
        // Look for a row containing "Deals"
        $dealsRowIndex = -1;
        for ($i = 0; $i < $rows->length; $i++) {
            $rowText = $rows->item($i)->textContent;
            if (strpos($rowText, 'Deals') !== false) {
                $dealsRowIndex = $i;
                break;
            }
        }
        
        // If we found the Deals row, look for the header row after it
        if ($dealsRowIndex !== -1) {
            // Look for the header row (should contain Time and Profit)
            $headerRowIndex = -1;
            $headers = [];
            
            // Check the next few rows for headers
            for ($i = $dealsRowIndex + 1; $i < $rows->length; $i++) {
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
                    
                    // Filter out balance rows and only add trades with profit/loss data
                    if (isset($trade['Profit']) && $trade['Profit'] !== '' && 
                        isset($trade['Type']) && $trade['Type'] !== 'balance') {
                        $trades[] = $trade;
                    }
                }
                break; // Found the right table, no need to check others
            }
        }
    }
    
    return $trades;
}

function calculateMonthlyStats($trades) {
    $monthlyData = [];
    
    // First pass: collect basic data
    foreach ($trades as $trade) {
        // Extract date and profit from trade
        if (!isset($trade['Time']) || !isset($trade['Profit'])) continue;
        
        $time = $trade['Time'];
        // Remove commas from profit for proper float conversion
        $profitStr = str_replace(',', '', $trade['Profit']);
        $profit = floatval($profitStr);
        
        // Parse date to get month and year
        // Assuming format like "2023.05.15 14:30:00"
        $dateParts = explode(' ', $time);
        $date = $dateParts[0];
        $dateComponents = explode('.', $date);
        
        if (count($dateComponents) >= 2) {
            $year = $dateComponents[0];
            $month = $dateComponents[1];
            $key = $year . '-' . $month;
            
            if (!isset($monthlyData[$key])) {
                $monthlyData[$key] = [
                    'bulan' => $month,
                    'tahun' => $year,
                    'profit_trade' => 0,
                    'loss_trade' => 0,
                    'jumlah_trade' => 0,
                    'winrate' => 0,
                    'gross_profit' => 0,
                    'gross_loss' => 0,
                    'net_profit' => 0,
                    'profit_factor' => 0,
                    'recovery_factor' => 0,
                    'max_drawdown' => 0,
                    'expected_payoff' => 0,
                    'sharpe_ratio' => 0,
                    'winning_trades' => 0,
                    'losing_trades' => 0,
                    'total_winning' => 0,
                    'total_losing' => 0,
                    'equity_curve' => [], // For max drawdown calculation
                    'profits' => [] // For sharpe ratio calculation
                ];
            }
            
            $monthlyData[$key]['jumlah_trade']++;
            $monthlyData[$key]['net_profit'] += $profit;
            $monthlyData[$key]['profits'][] = $profit; // Store for Sharpe ratio
            
            // Update equity curve for max drawdown calculation
            if (empty($monthlyData[$key]['equity_curve'])) {
                $monthlyData[$key]['equity_curve'][] = $profit;
            } else {
                $monthlyData[$key]['equity_curve'][] = end($monthlyData[$key]['equity_curve']) + $profit;
            }
            
            if ($profit > 0) {
                $monthlyData[$key]['profit_trade'] += $profit;
                $monthlyData[$key]['gross_profit'] += $profit;
                $monthlyData[$key]['winning_trades']++;
                $monthlyData[$key]['total_winning'] += $profit;
            } else {
                $monthlyData[$key]['loss_trade'] += $profit;
                $monthlyData[$key]['gross_loss'] += $profit;
                $monthlyData[$key]['losing_trades']++;
                $monthlyData[$key]['total_losing'] += $profit;
            }
        }
    }
    
    // Second pass: calculate advanced metrics
    foreach ($monthlyData as &$data) {
        // Winrate calculation
        if ($data['jumlah_trade'] > 0) {
            $data['winrate'] = ($data['winning_trades'] / $data['jumlah_trade']) * 100;
        }
        
        // Profit Factor = Gross Profit / |Gross Loss|
        if ($data['gross_loss'] < 0) {
            $data['profit_factor'] = abs($data['gross_profit'] / $data['gross_loss']);
        } else {
            $data['profit_factor'] = 0;
        }
        
        // Expected Payoff = Net Profit / Jumlah Trade
        if ($data['jumlah_trade'] > 0) {
            $data['expected_payoff'] = $data['net_profit'] / $data['jumlah_trade'];
        }
        
        // Max Drawdown calculation
        $data['max_drawdown'] = calculateMaxDrawdown($data['equity_curve']);
        
        // Recovery Factor = Net Profit / Max Drawdown
        if ($data['max_drawdown'] != 0) {
            $data['recovery_factor'] = abs($data['net_profit'] / $data['max_drawdown']);
        } else {
            $data['recovery_factor'] = 0;
        }
        
        // Sharpe Ratio calculation (simplified with risk-free rate = 0)
        $data['sharpe_ratio'] = calculateSharpeRatio($data['profits']);
    }
    
    return $monthlyData;
}

// Helper function to calculate Max Drawdown
function calculateMaxDrawdown($equityCurve) {
    if (empty($equityCurve)) return 0;
    
    $peak = $equityCurve[0];
    $maxDrawdown = 0;
    
    foreach ($equityCurve as $equity) {
        if ($equity > $peak) {
            $peak = $equity;
        }
        
        $drawdown = $peak - $equity;
        if ($drawdown > $maxDrawdown) {
            $maxDrawdown = $drawdown;
        }
    }
    
    return $maxDrawdown;
}

// Helper function to calculate Sharpe Ratio
function calculateSharpeRatio($profits) {
    if (count($profits) < 2) return 0;
    
    $sum = array_sum($profits);
    $count = count($profits);
    $mean = $sum / $count;
    
    $sumSquares = 0;
    foreach ($profits as $profit) {
        $sumSquares += pow($profit - $mean, 2);
    }
    
    $stdDev = sqrt($sumSquares / ($count - 1));
    
    // Sharpe Ratio = (Mean return - Risk-free rate) / Standard deviation
    // Using risk-free rate = 0 for simplicity
    if ($stdDev != 0) {
        return $mean / $stdDev;
    } else {
        return 0;
    }
}

function displayResults($settings, $monthlyStats) {
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Hasil Analisis Laporan Backtest</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            table { border-collapse: collapse; width: 100%; margin: 20px 0; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; cursor: pointer; }
            th:hover { background-color: #ddd; }
            h2 { color: #333; }
            .settings { background-color: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
            .sortable { background-image: url('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAkAAAAJCAYAAADgkQYQAAAAJUlEQVR42mNgYGD4z8DAwMgABXAGKQYmwN8I1QzTFK8aBgYAJI0TjCK7jowAAAAASUVORK5CYII='); background-repeat: no-repeat; background-position: center right; padding-right: 15px; }
            .sort-asc::after { content: ' ↑'; }
            .sort-desc::after { content: ' ↓'; }
        </style>
        <script>
            function sortTable(columnIndex) {
                const table = document.getElementById('monthlyTable');
                const tbody = table.getElementsByTagName('tbody')[0];
                const rows = Array.from(tbody.getElementsByTagName('tr'));
                
                // Clear previous sort indicators
                const headers = table.getElementsByTagName('th');
                for (let i = 0; i < headers.length; i++) {
                    headers[i].classList.remove('sort-asc', 'sort-desc');
                }
                
                const currentSortColumn = table.getAttribute('data-sort-column');
                const isAscending = currentSortColumn != columnIndex || table.getAttribute('data-sort-order') !== 'asc';
                
                // Set new sort column and order
                table.setAttribute('data-sort-column', columnIndex);
                table.setAttribute('data-sort-order', isAscending ? 'asc' : 'desc');
                
                // Add sort indicator to current column
                headers[columnIndex].classList.add(isAscending ? 'sort-asc' : 'sort-desc');
                
                rows.sort((a, b) => {
                    const aText = a.getElementsByTagName('td')[columnIndex].textContent.trim();
                    const bText = b.getElementsByTagName('td')[columnIndex].textContent.trim();
                    
                    // Try to parse as numbers first
                    // Remove non-numeric characters except minus and decimal point
                    const aNumStr = aText.replace(/[^0-9.-]/g, '');
                    const bNumStr = bText.replace(/[^0-9.-]/g, '');
                    
                    const aNum = parseFloat(aNumStr);
                    const bNum = parseFloat(bNumStr);
                    
                    if (!isNaN(aNum) && !isNaN(bNum)) {
                        return isAscending ? aNum - bNum : bNum - aNum;
                    }
                    
                    // Special handling for Bulan Tahun column (MM/YYYY format)
                    if (columnIndex === 0) {
                        const [aMonth, aYear] = aText.split('/');
                        const [bMonth, bYear] = bText.split('/');
                        const aDate = new Date(aYear, aMonth - 1);
                        const bDate = new Date(bYear, bMonth - 1);
                        return isAscending ? aDate - bDate : bDate - aDate;
                    }
                    
                    // Otherwise sort as strings
                    return isAscending ? aText.localeCompare(bText) : bText.localeCompare(aText);
                });
                
                // Re-append sorted rows
                rows.forEach(row => tbody.appendChild(row));
            }
        </script>
    </head>
    <body>
        <h2>Hasil Analisis Laporan Backtest</h2>
        
        <div class='settings'>
            <h3>Settings</h3>";
    
    if (!empty($settings)) {
        echo "<table>";
        foreach ($settings as $key => $value) {
            echo "<tr><td><strong>$key</strong></td><td>$value</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p>Tidak ada pengaturan yang ditemukan.</p>";
    }
    
    echo "</div>
        
        <h3>Analisa Bulanan</h3>";
    
    if (!empty($monthlyStats)) {
        echo "<table id='monthlyTable' data-sort-order='none' data-sort-column='-1'>
                <thead>
                <tr>
                    <th onclick='sortTable(0)' class='sortable'>Bulan Tahun</th>
                    <th onclick='sortTable(1)' class='sortable'>Profit Trade</th>
                    <th onclick='sortTable(2)' class='sortable'>Loss Trade</th>
                    <th onclick='sortTable(3)' class='sortable'>Jumlah Trade</th>
                    <th onclick='sortTable(4)' class='sortable'>Winrate</th>
                    <th onclick='sortTable(5)' class='sortable'>Gross Profit</th>
                    <th onclick='sortTable(6)' class='sortable'>Gross Loss</th>
                    <th onclick='sortTable(7)' class='sortable'>Net Profit</th>
                    <th onclick='sortTable(8)' class='sortable'>Profit Factor</th>
                    <th onclick='sortTable(9)' class='sortable'>Recovery Factor</th>
                    <th onclick='sortTable(10)' class='sortable'>Max Drawdown</th>
                    <th onclick='sortTable(11)' class='sortable'>Expected Payoff</th>
                    <th onclick='sortTable(12)' class='sortable'>Sharpe Ratio</th>
                </tr>
                </thead>
                <tbody>";
        
        // Sort by year-month for consistent display
        uksort($monthlyStats, function($a, $b) {
            return strcmp($a, $b);
        });
        
        foreach ($monthlyStats as $key => $stat) {
            // Format Bulan Tahun as MM/YYYY
            $bulanTahun = $stat['bulan'] . '/' . $stat['tahun'];
            
            echo "<tr>
                    <td>{$bulanTahun}</td>
                    <td>" . number_format($stat['profit_trade'], 2) . "</td>
                    <td>" . number_format($stat['loss_trade'], 2) . "</td>
                    <td>{$stat['jumlah_trade']}</td>
                    <td>" . number_format($stat['winrate'], 2) . "%</td>
                    <td>" . number_format($stat['gross_profit'], 2) . "</td>
                    <td>" . number_format($stat['gross_loss'], 2) . "</td>
                    <td>" . number_format($stat['net_profit'], 2) . "</td>
                    <td>" . number_format($stat['profit_factor'], 2) . "</td>
                    <td>" . number_format($stat['recovery_factor'], 2) . "</td>
                    <td>" . number_format($stat['max_drawdown'], 2) . "</td>
                    <td>" . number_format($stat['expected_payoff'], 2) . "</td>
                    <td>" . number_format($stat['sharpe_ratio'], 2) . "</td>
                  </tr>";
        }
        
        echo "</tbody></table>";
    } else {
        echo "<p>Tidak ada data bulanan yang ditemukan. Pastikan file laporan berisi data trades.</p>";
    }
    
    echo "<br><a href='index.php'>Unggah file lain</a>
    </body>
    </html>";
}
?>