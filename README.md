# Alat Analisis Laporan Backtest MT5

Aplikasi web berbasis PHP untuk menganalisis laporan backtest dari MetaTrader 5 dan menghasilkan statistik kinerja trading yang komprehensif.

## Fitur Utama

- Mendukung format laporan lama dan baru dari MetaTrader 5
- Ekstraksi data trades otomatis
- Perhitungan statistik bulanan:
  - Winrate
  - Profit Factor
  - Recovery Factor
  - Max Drawdown
  - Expected Payoff
  - Sharpe Ratio
- Tabel hasil yang dapat diurutkan
- Antarmuka pengguna yang ramah

## Persyaratan Sistem

- Server web dengan PHP 5.6 atau lebih tinggi
- Dukungan DOM extension untuk PHP
- Browser web modern (Chrome, Firefox, Edge, Safari)

## Instalasi

### Metode 1: Menggunakan XAMPP (Direkomendasikan untuk Windows)

1. Unduh dan instal [XAMPP](https://www.apachefriends.org/index.html)
2. Salin folder proyek ke direktori `htdocs` (biasanya `C:\xampp\htdocs\`)
3. Jalankan XAMPP Control Panel
4. Start layanan Apache
5. Akses aplikasi melalui browser di `http://localhost/nama_folder_proyek`

### Metode 2: Menggunakan Server PHP Built-in (Untuk Pengujian Cepat)

1. Buka terminal/command prompt
2. Navigasi ke direktori proyek:
   ```bash
   cd /path/ke/backtest_analisis
   ```
3. Jalankan server PHP built-in:
   ```bash
   php -S localhost:8000
   ```
4. Akses aplikasi di browser melalui `http://localhost:8000`

### Metode 3: Menggunakan LAMP Stack (Untuk Linux)

1. Instal LAMP stack:
   ```bash
   # Ubuntu/Debian
   sudo apt update
   sudo apt install apache2 php libapache2-mod-php
   
   # CentOS/RHEL
   sudo yum install httpd php php-mysql
   ```
2. Salin proyek ke direktori web server (biasanya `/var/www/html/`)
3. Restart Apache:
   ```bash
   sudo systemctl restart apache2  # Ubuntu/Debian
   sudo systemctl restart httpd    # CentOS/RHEL
   ```
4. Akses aplikasi melalui `http://localhost/nama_folder_proyek`

## Cara Menggunakan

1. Buka aplikasi di browser Anda
2. Klik tombol "Pilih File" untuk memilih laporan backtest (.htm atau .html)
3. Klik tombol "Analisis"
4. Lihat hasil analisis statistik bulanan yang ditampilkan dalam tabel yang dapat diurutkan

## Format Laporan yang Didukung

Aplikasi ini mendukung kedua format laporan MetaTrader 5:

### Format Lama
- Tabel tunggal dengan header "Trades"
- Kolom "Order" dan "Profit" dalam satu tabel

### Format Baru
- Tabel terpisah dengan header "Deals"
- Kolom "Time" dan "Profit" dalam struktur tabel yang berbeda

## Struktur File

- `index.php` - Halaman utama dengan form upload
- `upload_final.php` - Script utama untuk memproses file dan menampilkan hasil
- `ReportTester-5036666090.html` - Contoh laporan baru untuk pengujian

## Troubleshooting

### Masalah Umum

1. **File tidak terunggah**: Pastikan file berekstensi .htm atau .html
2. **Hasil kosong**: Periksa apakah file laporan berisi data trades
3. **Error parsing**: File mungkin korup atau dalam format yang tidak didukung

### Debugging

Untuk membantu debugging masalah dengan laporan baru:
1. Gunakan file `debug_new_report.php` untuk mendiagnosis format laporan
2. Periksa output untuk memastikan data trades diekstrak dengan benar

## Pengembangan Lebih Lanjut

Aplikasi ini dapat dikembangkan lebih lanjut dengan:
- Menambahkan ekspor ke format CSV/Excel
- Menambahkan visualisasi data dengan grafik
- Menyimpan hasil analisis ke database
- Menambahkan autentikasi pengguna

## Lisensi

Proyek ini merupakan perangkat lunak open-source.

## Kontribusi

Kontribusi sangat diterima! Silakan buat pull request atau laporkan issue di repository GitHub ini.