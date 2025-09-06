<?php
if (isset($_POST["submit"])) {
    $target_file = basename($_FILES["reportFile"]["name"]);
    $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

    // Pastikan file adalah .htm atau .html
    if ($file_type != "htm" && $file_type != "html") {
        echo "Maaf, hanya file .htm atau .html yang diizinkan.";
        exit;
    }

    // Pindahkan file yang diunggah
    if (!move_uploaded_file($_FILES["reportFile"]["tmp_name"], $target_file)) {
        echo "Error: Gagal mengunggah file.";
        exit;
    }

    // Mulai parsing file
    $html = file_get_contents($target_file);
    
    // Handle UTF-16 encoded files
    if (substr($html, 0, 2) === "\xFF\xFE" || substr($html, 0, 2) === "\xFE\xFF") {
        $html = iconv("UTF-16", "UTF-8//IGNORE", $html);
    }
    
    // Enhanced error handling
    if ($html === false) {
        echo "Error: Gagal membaca file.";
        exit;
    }
    
    if (empty($html)) {
        echo "Error: File kosong.";
        exit;
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
    
    // Extract Trades/Deals Table (enhanced to handle both formats)
    $trades = extractTradesEnhanced($doc);
    
    // Calculate Monthly Statistics
    $monthlyStats = calculateMonthlyStats($trades);
    
    // Display Results
    displayResults($settings, $monthlyStats);
}

function extractSettings($doc) {
    $settings = [];
    
    // Look for settings in table rows
    $tables = $doc->getElementsByTagName('table');
    
    foreach ($tables as $table) {
        $rows = $table->getElementsByTagName('tr');
        
        // Look for the "Settings" header
        $inSettingsSection = false;
        $currentKey = '';
        
        foreach ($rows as $row) {
            $cells = $row->getElementsByTagName('td');
            
            // Check if this row marks the start of the settings section
            if ($cells->length > 0) {
                $firstCell = $cells->item(0);
                if ($firstCell && strpos($firstCell->textContent, 'Settings') !== false) {
                    $inSettingsSection = true;
                    continue;
                }
                
                // Check if this row marks the end of the settings section
                if ($inSettingsSection && $cells->length > 0) {
                    $firstCell = $cells->item(0);
                    if ($firstCell && strpos($firstCell->textContent, 'Results') !== false) {
                        $inSettingsSection = false;
                        break;
                    }
                }
            }
            
            // Process settings rows
            if ($inSettingsSection && $cells->length >= 2) {
                $keyCell = $cells->item(0);
                $valueCell = $cells->item(1);
                
                if ($keyCell && $valueCell) {
                    $keyText = trim($keyCell->textContent);
                    $valueText = trim($valueCell->textContent);
                    
                    // Remove trailing colons from keys
                    $keyText = rtrim($keyText, ':');
                    
                    // If key is empty, this is a continuation of the previous setting
                    if (empty($keyText) && !empty($currentKey)) {
                        // Append to the previous value
                        $settings[$currentKey] .= "\n" . $valueText;
                    } else if (!empty($keyText)) {
                        // New setting
                        $settings[$keyText] = $valueText;
                        $currentKey = $keyText;
                    }
                }
            }
        }
    }
    
    // Fallback to the original method if no settings were found
    if (empty($settings)) {
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
    }
    
    return $settings;
}

function extractTradesEnhanced($doc) {
    $trades = [];
    $tables = $doc->getElementsByTagName('table');
    
    // First, try the new format (Deals table)
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
                return $trades; // Found the right table, return immediately
            }
        }
    }
    
    // If no Deals table found, try the old format (Trades/Orders table)
    foreach ($tables as $table) {
        $header = $table->getElementsByTagName('tr')->item(0);
        if ($header) {
            $headerText = $header->textContent;
            // Check for old format (Trades/Orders)
            if (strpos($headerText, 'Profit') !== false && strpos($headerText, 'Order') !== false) {
                $tradesTable = $table;
                
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
                    
                    // Filter out empty rows and only add trades with profit/loss data
                    if (isset($trade['Profit']) && $trade['Profit'] !== '') {
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
    // Calculate total trades and net profit for summary
    $totalTrades = 0;
    $totalNetProfit = 0;
    
    foreach ($monthlyStats as $stat) {
        $totalTrades += $stat['jumlah_trade'];
        $totalNetProfit += $stat['net_profit'];
    }
    
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Hasil Analisis Laporan Backtest</title>
        <style>
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background-color: #f5f7fa;
                color: #333;
                margin: 0;
                padding: 20px;
            }

            .container {
                max-width: 1200px;
                margin: 0 auto;
                background: white;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
                padding: 20px;
            }

            .header {
                background: linear-gradient(135deg, #1976D2, #2196F3);
                color: white;
                padding: 20px;
                border-radius: 6px;
                margin-bottom: 20px;
                text-align: center;
            }

            .settings-section {
                background-color: #E3F2FD;
                border-left: 4px solid #1976D2;
                padding: 15px;
                margin-bottom: 20px;
                border-radius: 0 4px 4px 0;
            }

            .results-summary {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 15px;
                margin: 20px 0;
            }

            .result-card {
                background: white;
                border-radius: 6px;
                padding: 15px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.05);
                border-top: 3px solid #1976D2;
                text-align: center;
            }

            .result-value {
                font-size: 1.5em;
                font-weight: bold;
                color: #1976D2;
                margin: 5px 0;
            }

            .result-label {
                font-size: 0.9em;
                color: #666;
            }

            table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
                background: white;
                border-radius: 6px;
                overflow: hidden;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }

            th {
                background-color: #1976D2;
                color: white;
                text-align: left;
                padding: 12px 15px;
                cursor: pointer;
                transition: background-color 0.3s;
            }

            th:hover {
                background-color: #0D47A1;
            }

            td {
                padding: 12px 15px;
                border-bottom: 1px solid #eee;
            }

            tr:nth-child(even) {
                background-color: #f9f9f9;
            }

            tr:hover {
                background-color: #E3F2FD;
            }

            .sort-asc::after {
                content: \" \\2191\";
            }

            .sort-desc::after {
                content: \" \\2193\";
            }

            a {
                color: #1976D2;
                text-decoration: none;
            }

            a:hover {
                text-decoration: underline;
            }
            
            .settings-table {
                width: 100%;
                border-collapse: collapse;
                background: white;
                border-radius: 4px;
                overflow: hidden;
            }
            
            .settings-table td {
                padding: 8px 12px;
                border-bottom: 1px solid #ddd;
            }
            
            .settings-table td:first-child {
                font-weight: bold;
                width: 30%;
                background-color: #f5f9ff;
            }
            
            .input-value {
                white-space: pre-line;
            }
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
        <div class='container'>
            <div class='header'>
                <h1>Hasil Analisis Laporan Backtest</h1>
            </div>
            
            <div class='settings-section'>
                <h3>Settings dari laporan backtest</h3>";
    
    if (!empty($settings)) {
        echo "<table class='settings-table'>";
        foreach ($settings as $key => $value) {
            // Special handling for Inputs to make it more readable
            if ($key === 'Inputs') {
                echo "<tr><td>$key</td><td class='input-value'>" . nl2br(htmlspecialchars($value)) . "</td></tr>";
            } else {
                echo "<tr><td>$key</td><td>" . htmlspecialchars($value) . "</td></tr>";
            }
        }
        echo "</table>";
    } else {
        echo "<p>Tidak ada pengaturan yang ditemukan.</p>";
    }
    
    echo "</div>
        
        <div class='results-summary'>
            <div class='result-card'>
                <div class='result-label'>Total Trades</div>
                <div class='result-value'>$totalTrades</div>
            </div>
            <div class='result-card'>
                <div class='result-label'>Net Profit</div>
                <div class='result-value'>" . number_format($totalNetProfit, 2) . "</div>
            </div>
        </div>
        
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
        </div>
    </body>
    </html>";
}
?>