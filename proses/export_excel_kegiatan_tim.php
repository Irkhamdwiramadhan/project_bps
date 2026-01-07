<?php
session_start();
require '../vendor/autoload.php'; 
include '../includes/koneksi.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Cek Login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: ../login.php');
    exit;
}

// 1. Ambil Parameter Filter
$filter_bulan = $_GET['bulan'] ?? '';
$filter_tahun = $_GET['tahun'] ?? '';
$filter_tim   = $_GET['tim'] ?? '';

// 2. Susun Query
$conditions = [];
$label_file = "Semua_Data";

if (!empty($filter_bulan)) {
    $conditions[] = "MONTH(k.batas_waktu) = " . intval($filter_bulan);
    $nama_bulan_arr = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
    $label_file = $nama_bulan_arr[$filter_bulan - 1];
}
if (!empty($filter_tahun)) {
    $conditions[] = "YEAR(k.batas_waktu) = " . intval($filter_tahun);
    $label_file .= "_" . $filter_tahun;
}
if (!empty($filter_tim)) {
    $conditions[] = "k.tim_id = " . intval($filter_tim);
}

$where_clause = "";
if (count($conditions) > 0) {
    $where_clause = "WHERE " . implode(" AND ", $conditions);
}

// ==========================================================================
// REVISI QUERY: MENGGUNAKAN GROUP_CONCAT UNTUK MENGAMBIL ANGGOTA
// ==========================================================================
$query = "SELECT 
            k.*, 
            t.nama_tim AS asal_kegiatan,
            GROUP_CONCAT(
                DISTINCT 
                CASE 
                    WHEN at.member_type = 'pegawai' THEN p.nama
                    WHEN at.member_type = 'mitra' THEN m.nama_lengkap
                END
                SEPARATOR ', '
            ) AS daftar_anggota
          FROM kegiatan k 
          LEFT JOIN tim t ON k.tim_id = t.id 
          -- Join ke tabel relasi anggota
          LEFT JOIN kegiatan_anggota ka ON k.id = ka.kegiatan_id
          LEFT JOIN anggota_tim at ON ka.anggota_id = at.id
          -- Join ke tabel Pegawai & Mitra
          LEFT JOIN pegawai p ON at.member_id = p.id AND at.member_type = 'pegawai'
          LEFT JOIN mitra m ON at.member_id = m.id AND at.member_type = 'mitra'
          
          $where_clause 
          GROUP BY k.id  -- Penting: Group by ID Kegiatan agar tidak duplikat
          ORDER BY k.batas_waktu ASC";

$result = mysqli_query($koneksi, $query);

// 3. Setup Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Kegiatan Tim');

// Styling
$styleHeader = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4A90E2']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];
$styleData = [
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true] // Wrap Text agar nama anggota rapi
];

// Header Kolom (Ditambah kolom ANGGOTA TIM)
$headers = ['NO', 'NAMA KEGIATAN', 'TIM', 'ANGGOTA TIM', 'TARGET', 'REALISASI', 'SATUAN', 'BATAS WAKTU', 'TGL REALISASI', 'KETERANGAN'];
$colLetters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];

foreach ($headers as $idx => $text) {
    $cell = $colLetters[$idx] . '1';
    $sheet->setCellValue($cell, $text);
}
$sheet->getStyle('A1:J1')->applyFromArray($styleHeader);

// Isi Data
$rowNum = 2;
$no = 1;

while ($row = mysqli_fetch_assoc($result)) {
    $sheet->setCellValue('A' . $rowNum, $no++);
    $sheet->setCellValue('B' . $rowNum, $row['nama_kegiatan']);
    $sheet->setCellValue('C' . $rowNum, $row['asal_kegiatan']);
    
    // Masukkan Daftar Anggota (Jika kosong strip)
    $anggota = !empty($row['daftar_anggota']) ? $row['daftar_anggota'] : '-';
    $sheet->setCellValue('D' . $rowNum, $anggota);
    
    $sheet->setCellValue('E' . $rowNum, $row['target']);
    $sheet->setCellValue('F' . $rowNum, $row['realisasi']);
    $sheet->setCellValue('G' . $rowNum, $row['satuan']);
    $sheet->setCellValue('H' . $rowNum, date('d-m-Y', strtotime($row['batas_waktu'])));
    
    $tgl_real = !empty($row['updated_at']) ? date('d-m-Y', strtotime($row['updated_at'])) : '-';
    $sheet->setCellValue('I' . $rowNum, $tgl_real);
    
    $sheet->setCellValue('J' . $rowNum, $row['keterangan']);

    // Apply Border & Style
    $sheet->getStyle("A$rowNum:J$rowNum")->applyFromArray($styleData);
    $rowNum++;
}

// Auto Size Columns
foreach ($colLetters as $col) {
    // Khusus kolom Anggota (D), jangan autosize terlalu lebar, set manual saja biar rapi (wrap text)
    if ($col == 'D') {
        $sheet->getColumnDimension($col)->setWidth(40);
    } elseif ($col == 'B') {
        $sheet->getColumnDimension($col)->setWidth(35);
    } else {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
}

// Output File
$filename = "Kegiatan_Tim_{$label_file}.xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>