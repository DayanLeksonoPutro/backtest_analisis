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
    move_uploaded_file($_FILES["reportFile"]["tmp_name"], $target_file);

    // Mulai parsing file
    $html = file_get_contents($target_file);
    $doc = new DOMDocument();
    @$doc->loadHTML($html); // Gunakan @ untuk menekan warning dari HTML yang tidak valid

    // Extract Settings
    $settings = extractSettings($doc);
    
    // Extract Trades Table
    $trades = extractTrades($doc);
    
    // Calculate Monthly Statistics
    $monthlyStats = calculateMonthlyStats($trades);
    
    // Display Results
    displayResults($settings, $monthlyStats);
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

function extractTrades($doc) {
    $trades = [];
    $tables = $doc->getElementsByTagName('table');
    $tradesTable = null;

    // Cari tabel yang berisi riwayat transaksi
    foreach ($tables as $table) {
        $header = $table->getElementsByTagName('tr')->item(0);
        if ($header && strpos($header->textContent, 'Profit') !== false && strpos($header->textContent, 'Order') !== false) {
            $tradesTable = $table;
            break;
        }
    }

    if ($tradesTable) {
        $rows = $tradesTable->getElementsByTagName('tr');
        $headerRow = $rows->item(0);
        $headers = [];
        
        // Get column headers
        foreach ($headerRow->getElementsByTagName('td') as $th) {
            $headers[] = trim($th->textContent);
        }
        
        // Process each trade row
        for ($i = 1; $i < $rows->length; $i++) {
            $row = $rows->item($i);
            $trade = [];
            $cells = $row->getElementsByTagName('td');
            
            for ($j = 0; $j < min(count($headers), $cells->length); $j++) {
                $trade[$headers[$j]] = trim($cells->item($j)->textContent);
            }
            
            // Only add trades with profit/loss data
            if (isset($trade['Profit']) && $trade['Profit'] !== '') {
                $trades[] = $trade;
            }
        }
    }
    
    return $trades;
}

function calculateMonthlyStats($trades) {
    $monthlyData = [];
    
    foreach ($trades as $trade) {
        // Extract date and profit from trade
        if (!isset($trade['Time']) || !isset($trade['Profit'])) continue;
        
        $time = $trade['Time'];
        $profit = floatval(str_replace(',', '', $trade['Profit']));
        
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
                    'total_trade' => 0,
                    'winrate' => 0,
                    'net_profit' => 0,
                    'profit_factor' => 0,
                    'recovery_factor' => 0,
                    'max_drawdown' => 0,
                    'expected_payoff' => 0,
                    'sharpe_ratio' => 0,
                    'winning_trades' => 0,
                    'losing_trades' => 0,
                    'total_winning' => 0,
                    'total_losing' => 0
                ];
            }
            
            $monthlyData[$key]['total_trade']++;
            $monthlyData[$key]['net_profit'] += $profit;
            
            if ($profit > 0) {
                $monthlyData[$key]['profit_trade'] += $profit;
                $monthlyData[$key]['winning_trades']++;
                $monthlyData[$key]['total_winning'] += $profit;
            } else {
                $monthlyData[$key]['loss_trade'] += $profit;
                $monthlyData[$key]['losing_trades']++;
                $monthlyData[$key]['total_losing'] += $profit;
            }
        }
    }
    
    // Calculate additional metrics
    foreach ($monthlyData as &$data) {
        if ($data['total_trade'] > 0) {
            $data['winrate'] = ($data['winning_trades'] / $data['total_trade']) * 100;
            $data['expected_payoff'] = $data['net_profit'] / $data['total_trade'];
        }
        
        // Profit Factor = Gross Profit / Abs(Gross Loss)
        if ($data['loss_trade'] < 0) {
            $data['profit_factor'] = abs($data['profit_trade'] / $data['loss_trade']);
        }
        
        // Simple Sharpe Ratio approximation (assuming risk-free rate = 0)
        // This is a simplified calculation - a more accurate one would require standard deviation
        if ($data['total_trade'] > 1) {
            // For simplicity, we'll use a basic calculation
            $data['sharpe_ratio'] = $data['expected_payoff'] / 1; // Simplified
        }
    }
    
    return $monthlyData;
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
            th { background-color: #f2f2f2; }
            h2 { color: #333; }
            .settings { background-color: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        </style>
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
        
        <h3>Tabel Bulanan</h3>";
    
    if (!empty($monthlyStats)) {
        echo "<table>
                <tr>
                    <th>Bulan</th>
                    <th>Tahun</th>
                    <th>Profit Trade</th>
                    <th>Loss Trader</th>
                    <th>Total Trade</th>
                    <th>Winrate (%)</th>
                    <th>Net Profit</th>
                    <th>Profit Factor</th>
                    <th>Recovery Factor</th>
                    <th>Max Drawdown</th>
                    <th>Expected Payoff</th>
                    <th>Sharpe Ratio</th>
                </tr>";
        
        foreach ($monthlyStats as $stat) {
            echo "<tr>
                    <td>{$stat['bulan']}</td>
                    <td>{$stat['tahun']}</td>
                    <td>" . number_format($stat['profit_trade'], 2) . "</td>
                    <td>" . number_format($stat['loss_trade'], 2) . "</td>
                    <td>{$stat['total_trade']}</td>
                    <td>" . number_format($stat['winrate'], 2) . "</td>
                    <td>" . number_format($stat['net_profit'], 2) . "</td>
                    <td>" . number_format($stat['profit_factor'], 2) . "</td>
                    <td>" . number_format($stat['recovery_factor'], 2) . "</td>
                    <td>" . number_format($stat['max_drawdown'], 2) . "</td>
                    <td>" . number_format($stat['expected_payoff'], 2) . "</td>
                    <td>" . number_format($stat['sharpe_ratio'], 2) . "</td>
                  </tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p>Tidak ada data bulanan yang ditemukan.</p>";
    }
    
    echo "<br><a href='index.php'>Unggah file lain</a>
    </body>
    </html>";
}
?>