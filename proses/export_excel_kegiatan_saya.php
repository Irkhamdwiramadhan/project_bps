<?php
session_start();
require '../vendor/autoload.php'; // Pastikan path vendor benar
include '../includes/koneksi.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// 1. Cek Login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: ../login.php');
    exit;
}

// 2. Ambil Parameter Filter & ID Pegawai
$id_pegawai = $_SESSION['user_id'] ?? 0;
$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : date('m');
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date('Y');

// Nama Bulan untuk Judul File
$nama_bulan_str = date('F', mktime(0, 0, 0, $bulan, 10));

// 3. Query Data (Sesuai Permintaan: Nama, Tanggal, Jenis)
$query = "SELECT k.tanggal, k.jenis_kegiatan, p.nama AS nama_pegawai 
          FROM kegiatan_harian k
          JOIN pegawai p ON k.pegawai_id = p.id
          WHERE k.pegawai_id = ? 
          AND MONTH(k.tanggal) = ? AND YEAR(k.tanggal) = ?
          ORDER BY k.tanggal DESC, k.jam_mulai DESC";

$stmt = $koneksi->prepare($query);
$stmt->bind_param("iii", $id_pegawai, $bulan, $tahun);
$stmt->execute();
$result = $stmt->get_result();

// 4. Setup Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Laporan Kegiatan');

// --- Styling ---
$styleHeader = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']], // Warna Biru
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];

$styleData = [
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
];

// --- Header Kolom ---
$headers = ['NO', 'NAMA PEGAWAI', 'TANGGAL', 'JENIS KEGIATAN'];
$colLetters = ['A', 'B', 'C', 'D'];

// Set Header
foreach ($headers as $idx => $text) {
    $cell = $colLetters[$idx] . '1';
    $sheet->setCellValue($cell, $text);
}
$sheet->getStyle('A1:D1')->applyFromArray($styleHeader);

// --- Isi Data ---
$rowNum = 2;
$no = 1;

while ($row = $result->fetch_assoc()) {
    // Format Tanggal (Hanya Tanggal, Bulan, Tahun)
    $tanggal_formatted = date('d-m-Y', strtotime($row['tanggal']));

    $sheet->setCellValue('A' . $rowNum, $no++);
    $sheet->setCellValue('B' . $rowNum, $row['nama_pegawai']);
    $sheet->setCellValue('C' . $rowNum, $tanggal_formatted);
    $sheet->setCellValue('D' . $rowNum, $row['jenis_kegiatan']);

    // Apply Style per baris
    $sheet->getStyle("A$rowNum:D$rowNum")->applyFromArray($styleData);
    
    // Center alignment untuk No dan Tanggal
    $sheet->getStyle("A$rowNum")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("C$rowNum")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $rowNum++;
}

// Auto Size Columns
foreach ($colLetters as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// 5. Output File
$filename = "Kegiatan_Saya_{$nama_bulan_str}_{$tahun}.xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>