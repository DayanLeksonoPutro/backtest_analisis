# Backtest Analysis Tool - Enhanced Version

## Problem
The backtest analysis tool was not displaying results for new monthly report files (`ReportTester-5036666090.html`) due to a change in the report format structure.

## Root Cause
The new MetaTrader 5 report format uses a different table structure:
- Old format: Single table with "Trades" header containing "Order" and "Profit" columns
- New format: Separate "Deals" table with "Time" and "Profit" columns, and a different row structure

## Solution
Enhanced the extraction function to handle both old and new report formats:

1. **New Format Detection**: Look for tables containing a "Deals" row
2. **Header Identification**: Find the row with both "Time" and "Profit" columns
3. **Data Extraction**: Process all rows after the header, filtering out balance rows
4. **Backward Compatibility**: Fall back to old format detection if new format not found

## Key Improvements

### 1. Enhanced Error Handling
- File existence and readability checks
- UTF-16 encoding detection and conversion
- HTML parsing error reporting
- Empty data detection

### 2. Flexible Table Parsing
- Handles both `td` and `th` elements
- Processes tables with varying structures
- Filters out irrelevant data rows

### 3. Data Validation
- Ensures required columns (Time, Profit) are present
- Filters out balance entries in new format
- Validates numeric data parsing

## Files

- `upload_final.php` - Main upload script with enhanced extraction
- `direct_test.php` - Direct test script for verification
- `debug_new_report.php` - Diagnostic tool for new reports

## Usage

1. Use `upload_final.php` as the main processing script
2. For debugging new reports, run `debug_new_report.php`
3. Test functionality with `direct_test.php`

## Validation Results

The enhanced solution successfully processes the new report file:
- Extracts 246 trades
- Calculates monthly statistics for 6 months (Jan-Jun 2025)
- Displays all key metrics including profit factor, winrate, and Sharpe ratio

## Sample Output

```
Bulan Tahun | Profit Trade | Loss Trade | Jumlah Trade | Winrate | Net Profit
01/2025     | 1,441.67     | -904.16    | 42           | 26.19%  | 537.51
02/2025     | 963.45       | -602.67    | 32           | 28.13%  | 360.78
03/2025     | 1,443.76     | -803.56    | 40           | 30.00%  | 640.20
...
```