<?php
session_start();
require '../vendor/autoload.php'; 
include '../includes/koneksi.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;

// 1. Cek Login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: ../login.php');
    exit;
}

$id_pegawai = $_SESSION['user_id'] ?? 0;
$user_roles = $_SESSION['user_role'] ?? [];
$is_admin = in_array('super_admin', $user_roles) || in_array('admin_pegawai', $user_roles);

// 2. LOGIKA QUERY
if ($is_admin) {
    $where_clause = "1=1";
} else {
    $where_clause = "k.pegawai_id = '$id_pegawai'";
}

$query = "SELECT k.tanggal, k.jenis_kegiatan, k.uraian, p.nama AS nama_pegawai 
          FROM kegiatan_harian k
          JOIN pegawai p ON k.pegawai_id = p.id
          WHERE $where_clause
          ORDER BY k.tanggal DESC, p.nama ASC";

$result = mysqli_query($koneksi, $query);

// 3. SETUP EXCEL
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Laporan Kegiatan');

// --- STYLE DEFINITIONS ---
// Style Header Tabel (Biru Tua, Teks Putih, Bold)
$styleHeader = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
        'size' => 11,
        'name' => 'Arial'
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '1F4E78'], // Biru Profesional
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000'],
        ],
    ],
];

// Style Isi Tabel (Border Tipis, Align Top)
$styleData = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000'],
        ],
    ],
    'alignment' => [
        'vertical' => Alignment::VERTICAL_TOP, // Agar teks panjang enak dibaca dari atas
    ],
    'font' => [
        'name' => 'Arial',
        'size' => 10
    ]
];

// --- JUDUL LAPORAN (Row 1) ---
$sheet->mergeCells('A1:F1');
$sheet->setCellValue('A1', 'LAPORAN REKAPITULASI KEGIATAN HARIAN PEGAWAI');
$sheet->getStyle('A1')->applyFromArray([
    'font' => ['bold' => true, 'size' => 14, 'name' => 'Arial'],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);

// --- HEADER KOLOM (Row 3) ---
// Kita beri jarak 1 baris kosong di Row 2
$rowHeader = 3;
$headers = ['NO', 'NAMA PEGAWAI', 'BULAN', 'TANGGAL', 'JENIS KEGIATAN', 'URAIAN / DESKRIPSI'];
$colLetters = ['A', 'B', 'C', 'D', 'E', 'F'];

// Set Header Value
foreach ($headers as $idx => $text) {
    $cell = $colLetters[$idx] . $rowHeader;
    $sheet->setCellValue($cell, $text);
}

// Terapkan Style Header
$sheet->getStyle("A$rowHeader:F$rowHeader")->applyFromArray($styleHeader);
$sheet->getRowDimension($rowHeader)->setRowHeight(25); // Tinggi baris header

// --- ISI DATA ---
$rowNum = 4;
$no = 1;

$bulan_indo = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
    7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $timestamp = strtotime($row['tanggal']);
        $bulan_num = (int)date('m', $timestamp);
        $nama_bulan_row = $bulan_indo[$bulan_num];
        $tanggal_hari = date('d', $timestamp); // Ambil tanggal saja (01-31)

        $sheet->setCellValue('A' . $rowNum, $no++);
        $sheet->setCellValue('B' . $rowNum, $row['nama_pegawai']);
        $sheet->setCellValue('C' . $rowNum, $nama_bulan_row);
        $sheet->setCellValue('D' . $rowNum, $tanggal_hari);
        $sheet->setCellValue('E' . $rowNum, $row['jenis_kegiatan']);
        $sheet->setCellValue('F' . $rowNum, $row['uraian']);
        
        $rowNum++;
    }
}

// --- FINAL STYLING ---
$lastRow = $rowNum - 1;

if ($lastRow >= 4) {
    // 1. Terapkan border ke seluruh data
    $sheet->getStyle("A4:F$lastRow")->applyFromArray($styleData);

    // 2. Alignment Khusus Kolom Tertentu
    // Center: No (A), Bulan (C), Tanggal (D), Jenis (E)
    $sheet->getStyle("A4:A$lastRow")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("C4:E$lastRow")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    // Left: Nama (B), Uraian (F)
    $sheet->getStyle("B4:B$lastRow")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    $sheet->getStyle("F4:F$lastRow")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

    // 3. WRAP TEXT untuk Uraian (PENTING)
    $sheet->getStyle("F4:F$lastRow")->getAlignment()->setWrapText(true);
}

// --- ATUR LEBAR KOLOM ---
$sheet->getColumnDimension('A')->setWidth(5);   // No
$sheet->getColumnDimension('B')->setAutoSize(true); // Nama
$sheet->getColumnDimension('C')->setWidth(15);  // Bulan
$sheet->getColumnDimension('D')->setWidth(10);  // Tanggal
$sheet->getColumnDimension('E')->setWidth(20);  // Jenis
$sheet->getColumnDimension('F')->setWidth(60);  // Uraian (Fixed width agar wrap text bekerja rapi)

// 4. Output File
$filename = "Laporan_Kegiatan_" . date('Y-m-d_H-i') . ".xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>