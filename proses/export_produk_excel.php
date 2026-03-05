<?php
// Mencegah output sampah
ob_start();
session_start();

require '../vendor/autoload.php'; 
include '../includes/koneksi.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// =================================================================
// 1. TANGKAP FILTER
// =================================================================
$filter_berdasarkan = isset($_GET['filter_berdasarkan']) ? $_GET['filter_berdasarkan'] : 'pegawai_semua';
$pegawai_id = '';

if (strpos($filter_berdasarkan, 'pegawai_') === 0) {
    $pegawai_val = str_replace('pegawai_', '', $filter_berdasarkan);
    if ($pegawai_val !== 'semua') {
        $pegawai_id = intval($pegawai_val);
    }
}

$periode = isset($_GET['periode']) ? $_GET['periode'] : 'harian';
$tanggal_filter = isset($_GET['tanggal_filter']) ? $_GET['tanggal_filter'] : date('Y-m-d');
$tanggal_awal = isset($_GET['tanggal_awal']) ? $_GET['tanggal_awal'] : date('Y-m-01');
$tanggal_akhir = isset($_GET['tanggal_akhir']) ? $_GET['tanggal_akhir'] : date('Y-m-d');

// =================================================================
// 2. QUERY BUILDER
// =================================================================
$params = [];
$types = '';
$where_clause = " WHERE 1=1";

// Filter Pegawai
if (!empty($pegawai_id)) {
    $where_clause .= " AND s.pegawai_id = ?";
    $params[] = $pegawai_id;
    $types .= 'i';
}

// Filter Tanggal
switch ($periode) {
    case 'harian':
        if (!empty($tanggal_filter)) {
            $where_clause .= " AND DATE(s.date) = ?";
            $params[] = $tanggal_filter;
            $types .= 's';
        }
        $info_periode = "Harian: " . date('d-m-Y', strtotime($tanggal_filter));
        break;
    case 'mingguan':
    case 'bulanan':
        if (!empty($tanggal_awal) && !empty($tanggal_akhir)) {
            $where_clause .= " AND DATE(s.date) BETWEEN ? AND ?";
            $params[] = $tanggal_awal;
            $params[] = $tanggal_akhir;
            $types .= 'ss';
        }
        $info_periode = "Periode: " . date('d-m-Y', strtotime($tanggal_awal)) . " s/d " . date('d-m-Y', strtotime($tanggal_akhir));
        break;
    default:
        $info_periode = "Semua Waktu";
}

// =================================================================
// 3. LOGIKA UTAMA (PEMBEDA PRODUK VS TRANSAKSI)
// =================================================================

// Cek apakah user meminta filter PRODUK
if ($filter_berdasarkan === 'produk_semua') {
    
    // --- MODE 1: REKAP PRODUK ---
    $judul_sheet = "Rekap Produk Terjual";
    $sql = "SELECT 
                pr.name AS nama_produk, 
                SUM(si.qty) AS total_jumlah,
                SUM(si.qty * si.price) AS total_pendapatan,
                MIN(DATE(s.date)) as tanggal_awal_produk,
                MAX(DATE(s.date)) as tanggal_akhir_produk
            FROM sales_items si
            JOIN products pr ON si.product_id = pr.id
            JOIN sales s ON si.sale_id = s.id
            $where_clause
            GROUP BY pr.id, pr.name 
            ORDER BY total_jumlah DESC";

} else {
    
    // --- MODE 2: REKAP TRANSAKSI (PEGAWAI) ---
    // Logika: Jika bukan 'produk_semua', maka pasti Pegawai (Semua atau Spesifik)
    $judul_sheet = "Rekap Transaksi Penjualan";
    $sql = "SELECT s.id, s.date, p.nama AS nama_pegawai,
                   si.qty, si.price, pr.name AS nama_produk
            FROM sales s
            JOIN pegawai p ON s.pegawai_id = p.id
            JOIN sales_items si ON s.id = si.sale_id
            LEFT JOIN products pr ON si.product_id = pr.id
            $where_clause
            ORDER BY s.date DESC";
}

// Eksekusi Query
$stmt = $koneksi->prepare($sql);
$data = [];
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// =================================================================
// 4. BUAT EXCEL
// =================================================================
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Laporan');

$styleHeader = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4A90E2']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];
$styleData = [
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
];

// Judul
$sheet->setCellValue('A1', strtoupper($judul_sheet));
$sheet->setCellValue('A2', $info_periode);
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1:A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$row = 4;

if ($filter_berdasarkan === 'produk_semua') {
    // --- LAYOUT PRODUK ---
    $sheet->mergeCells('A1:E1');
    $sheet->mergeCells('A2:E2');
    $headers = ['No', 'Nama Produk', 'Periode Terjual', 'Jumlah Terjual', 'Total Pendapatan (Rp)'];
    
    $col = 'A';
    foreach ($headers as $h) {
        $sheet->setCellValue($col . $row, $h);
        $sheet->getColumnDimension($col)->setAutoSize(true);
        $col++;
    }
    $sheet->getStyle("A$row:E$row")->applyFromArray($styleHeader);
    
    $row++;
    $no = 1;
    $total_all = 0;
    foreach ($data as $item) {
        $periode_str = ($item['tanggal_awal_produk'] == $item['tanggal_akhir_produk']) 
            ? date('d/m/Y', strtotime($item['tanggal_awal_produk'])) 
            : date('d/m', strtotime($item['tanggal_awal_produk'])) . ' - ' . date('d/m/Y', strtotime($item['tanggal_akhir_produk']));

        $sheet->setCellValue('A' . $row, $no++);
        $sheet->setCellValue('B' . $row, $item['nama_produk']);
        $sheet->setCellValue('C' . $row, $periode_str);
        $sheet->setCellValue('D' . $row, $item['total_jumlah']);
        $sheet->setCellValue('E' . $row, $item['total_pendapatan']);
        $total_all += $item['total_pendapatan'];
        $row++;
    }
    
    $sheet->setCellValue('D' . $row, 'TOTAL');
    $sheet->setCellValue('E' . $row, $total_all);
    $sheet->getStyle("A$row:E$row")->applyFromArray($styleData);
    $sheet->getStyle("D$row:E$row")->getFont()->setBold(true);
    $sheet->getStyle("E5:E$row")->getNumberFormat()->setFormatCode('#,##0');

} else {
    // --- LAYOUT TRANSAKSI ---
    $sheet->mergeCells('A1:H1');
    $sheet->mergeCells('A2:H2');
    $headers = ['No', 'ID Sales', 'Waktu', 'Pegawai', 'Produk', 'Qty', 'Harga (Rp)', 'Subtotal (Rp)'];
    
    $col = 'A';
    foreach ($headers as $h) {
        $sheet->setCellValue($col . $row, $h);
        $sheet->getColumnDimension($col)->setAutoSize(true);
        $col++;
    }
    $sheet->getStyle("A$row:H$row")->applyFromArray($styleHeader);

    $row++;
    $no = 1;
    $total_all = 0;
    foreach ($data as $item) {
        $subtotal = $item['qty'] * $item['price'];
        $sheet->setCellValue('A' . $row, $no++);
        $sheet->setCellValue('B' . $row, '#' . $item['id']);
        $sheet->setCellValue('C' . $row, date('d/m/Y H:i', strtotime($item['date'])));
        $sheet->setCellValue('D' . $row, $item['nama_pegawai']);
        $sheet->setCellValue('E' . $row, $item['nama_produk']);
        $sheet->setCellValue('F' . $row, $item['qty']);
        $sheet->setCellValue('G' . $row, $item['price']);
        $sheet->setCellValue('H' . $row, $subtotal);
        $total_all += $subtotal;
        $row++;
    }

    $sheet->setCellValue('G' . $row, 'TOTAL');
    $sheet->setCellValue('H' . $row, $total_all);
    
    $sheet->getStyle("A$row:H$row")->applyFromArray($styleData);
    $sheet->getStyle("G$row:H$row")->getFont()->setBold(true);
    $sheet->getStyle("G5:H$row")->getNumberFormat()->setFormatCode('#,##0');
}

// --- OUTPUT ---
if (ob_get_length()) ob_end_clean();
$filename = 'Laporan_' . str_replace(' ', '_', $judul_sheet) . '_' . date('Ymd_His') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>