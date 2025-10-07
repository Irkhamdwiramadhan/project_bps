<?php
// proses/export_produk_excel.php

// 1. SETUP DAN DEPENDENCIES
// =========================================================================
// Asumsi file ini berada di dalam folder `pages`, sehingga `vendor` ada di level `../`
require_once '../vendor/autoload.php';
include '../includes/koneksi.php';

// Panggil kelas-kelas yang diperlukan dari PhpSpreadsheet
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

// Ambil dan validasi parameter dari URL
$filter_berdasarkan = isset($_GET['filter_berdasarkan']) ? $_GET['filter_berdasarkan'] : '';
$periode = isset($_GET['periode']) ? $_GET['periode'] : 'harian';
$tanggal_filter = isset($_GET['tanggal_filter']) ? $_GET['tanggal_filter'] : date('Y-m-d');
$tanggal_awal = isset($_GET['tanggal_awal']) ? $_GET['tanggal_awal'] : '';
$tanggal_akhir = isset($_GET['tanggal_akhir']) ? $_GET['tanggal_akhir'] : '';

// Pastikan export ini hanya dijalankan untuk mode rekap produk
if ($filter_berdasarkan !== 'produk_semua') {
    die("Akses tidak valid. Export ini hanya untuk rekap semua produk.");
}


// 2. MENGAMBIL SEMUA DATA YANG DIPERLUKAN
// =========================================================================
$params = [];
$types = '';
$sql = "SELECT 
            pr.name AS nama_produk, 
            SUM(si.qty) AS total_jumlah,
            MIN(DATE(s.date)) as tanggal_awal_produk,
            MAX(DATE(s.date)) as tanggal_akhir_produk
        FROM sales_items si
        JOIN products pr ON si.product_id = pr.id
        JOIN sales s ON si.sale_id = s.id
        WHERE 1=1";

// Tambahkan filter periode
switch ($periode) {
    case 'harian':
        if (!empty($tanggal_filter)) { $sql .= " AND DATE(s.date) = ?"; $params[] = $tanggal_filter; $types .= 's'; }
        break;
    case 'mingguan':
    case 'bulanan':
        if (!empty($tanggal_awal) && !empty($tanggal_akhir)) { $sql .= " AND DATE(s.date) BETWEEN ? AND ?"; $params[] = $tanggal_awal; $params[] = $tanggal_akhir; $types .= 'ss'; }
        break;
}
$sql .= " GROUP BY pr.id, pr.name ORDER BY pr.name ASC";

// Eksekusi kueri
$stmt = $koneksi->prepare($sql);
$data_produk = [];
if ($stmt) {
    if (!empty($params)) { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $result = $stmt->get_result();
    $data_produk = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}


// 3. MEMBUAT DOKUMEN EXCEL DENGAN PHPSPREADSHEET
// =========================================================================
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Judul Laporan
$sheet->mergeCells('A1:C1');
$sheet->setCellValue('A1', 'Laporan Rekapitulasi Semua Produk');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Membuat Header Tabel
$sheet->setCellValue('A3', 'Nama Produk');
$sheet->setCellValue('B3', 'Tanggal Transaksi');
$sheet->setCellValue('C3', 'Total Jumlah Terjual');

$header_style = [
    'font' => ['bold' => true],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'F0F0F0']]
];
$sheet->getStyle('A3:C3')->applyFromArray($header_style);

// Menentukan Style untuk baris data
$numericFormat = '#,##0';

// Proses dan cetak data ke dalam sheet
$row_num = 4;
if (!empty($data_produk)) {
    foreach ($data_produk as $row) {
        // Format rentang tanggal
        $tanggal_range = '';
        if ($row['tanggal_awal_produk'] == $row['tanggal_akhir_produk']) {
            $tanggal_range = date('d-m-Y', strtotime($row['tanggal_awal_produk']));
        } else {
            $tanggal_range = date('d-m-Y', strtotime($row['tanggal_awal_produk'])) . ' s/d ' . date('d-m-Y', strtotime($row['tanggal_akhir_produk']));
        }

        // Siapkan baris data
        $item_row_data = [
            $row['nama_produk'],
            $tanggal_range,
            $row['total_jumlah']
        ];

        // Tulis baris data item ke sheet
        $sheet->fromArray($item_row_data, NULL, "A{$row_num}");

        // Format angka dan alignment
        $sheet->getStyle("B{$row_num}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("C{$row_num}")->getNumberFormat()->setFormatCode($numericFormat);
        $sheet->getStyle("C{$row_num}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        
        $row_num++;
    }
} else {
    $sheet->mergeCells("A4:C4")->setCellValue('A4', 'Tidak ada data produk ditemukan untuk periode ini.');
    $sheet->getStyle("A4")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $row_num++;
}

// 4. PENYESUAIAN AKHIR & GAYA
// =========================================================================
$last_row = $row_num - 1;
if ($last_row >= 4) {
    // Terapkan border ke seluruh tabel
    $sheet->getStyle("A3:C{$last_row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
}

// Atur lebar kolom
$sheet->getColumnDimension('A')->setWidth(60); // Nama Produk
$sheet->getColumnDimension('B')->setWidth(30); // Tanggal Transaksi
$sheet->getColumnDimension('C')->setWidth(20); // Jumlah


// 5. OUTPUT FILE EXCEL KE BROWSER
// =========================================================================
$tahun_sekarang = date("Y");
$filename = 'laporan_rekap_produk_' . $tahun_sekarang . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>